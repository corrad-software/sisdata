<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Ptn\Models\GambarLawatan;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\LaporanLawatan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Support\AliranPtn;
use App\Modules\Ptn\Support\Geometri;
use App\Modules\Ptn\Support\Lalai;
use App\Modules\Ptn\Support\Rujukan;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * SRS-PTN-09/10, acceptance #4: the visit report is generated automatically
 * from the form and photos as a frozen snapshot (kandungan + SHA-256), sent
 * to MOD-WFE for validation, rendered to PDF in a standard format (location
 * map, findings, tagged photos, recommendations) and stored in DMS on
 * approval — then locked by a database trigger. Corrections are addenda
 * linked to the original report, validated the same way.
 */
class LaporanService
{
    public function __construct(
        private WorkflowEngine $workflow,
        private DocumentStore $documents,
        private LotRegistry $lots,
        private Geometri $geo,
        private Rujukan $ref,
        private Notifier $notifier,
        private AuditLogger $audit,
    ) {}

    public function jana(RekodLawatan $lawatan, User $user, string $jenis = 'asal', ?LaporanLawatan $asal = null, ?string $catatan = null): LaporanLawatan
    {
        $kandungan = $jenis === 'asal' ? $this->snapshot($lawatan) : [
            'no_rujukan' => $lawatan->no_rujukan, 'pembetulan' => $catatan, 'id_laporan_asal' => $asal?->id,
            'tarikh_laporan_asal' => $asal?->tarikh_sah?->toIso8601String(), 'lot' => $asal?->kandungan['lot'] ?? null,
        ];
        $laporan = new LaporanLawatan;
        $laporan->forceFill([
            'id_lawatan' => $lawatan->id, 'kod_daerah' => $lawatan->kod_daerah, 'kod_mukim' => $lawatan->kod_mukim, 'klasifikasi' => $lawatan->klasifikasi,
            'jenis' => $jenis, 'id_laporan_asal' => $asal?->id, 'format' => 'pdf', 'kandungan' => $kandungan,
            'cincangan_kandungan' => self::cincang($kandungan), 'catatan_pembetulan' => $catatan,
            'status' => 'menunggu', 'tarikh_jana' => now(), 'id_pencipta' => $user->id,
        ])->save();
        $this->mulaPengesahan($laporan, $user);

        return $laporan->refresh();
    }

    /**
     * SHA-256 of the snapshot in canonical form (keys sorted recursively), so
     * it can be re-verified from the stored jsonb at any time.
     *
     * @param  array<mixed>  $kandungan
     */
    public static function cincang(array $kandungan): string
    {
        $sort = function (array $a) use (&$sort): array {
            if (! array_is_list($a)) {
                ksort($a);
            }

            return array_map(fn ($v) => is_array($v) ? $sort($v) : (is_float($v) && floor($v) === $v ? (int) $v : $v), $a);
        };

        return hash('sha256', (string) json_encode($sort($kandungan), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function mulaPengesahan(LaporanLawatan $laporan, User $user): void
    {
        if ($laporan->id_aliran !== null || $laporan->status_kunci) {
            return;
        }
        try {
            $id = $this->workflow->start(AliranPtn::LAPORAN, AliranPtn::SUBJEK_LAPORAN, $laporan->id, $user, [
                'kod_daerah' => $laporan->kod_daerah, 'jenis' => $laporan->jenis,
                'ada_kritikal' => collect($laporan->kandungan['isu'] ?? [])->contains(fn ($i) => ($i['tahap_keterukan'] ?? '') === 'kritikal'),
                'pautan' => '/admin/ptn/lawatan/'.$laporan->id_lawatan,
            ], ($laporan->jenis === 'asal' ? 'Pengesahan laporan lawatan ' : 'Pengesahan laporan tambahan ').($laporan->kandungan['no_rujukan'] ?? ''));
            $laporan->forceFill(['id_aliran' => $id])->save();
        } catch (InvalidArgumentException $e) {
            report($e);
        }
    }

    /** SRS-PTN-10: correction of a locked report = an addendum linked to it. */
    public function tambahan(LaporanLawatan $asal, string $catatan, User $user): LaporanLawatan
    {
        if (! $asal->status_kunci) {
            throw ValidationException::withMessages(['laporan' => __('ptn::ptn.tambahan_hanya_dikunci')]);
        }
        $lawatan = RekodLawatan::query()->withoutGlobalScope('access')->findOrFail($asal->id_lawatan);

        return $this->jana($lawatan, $user, 'tambahan', $asal->jenis === 'asal' ? $asal : ($asal->id_laporan_asal ? LaporanLawatan::query()->withoutGlobalScope('access')->find($asal->id_laporan_asal) : $asal), $catatan);
    }

    /** WorkflowCompleted for ptn.pengesahan_laporan. */
    public function keputusan(LaporanLawatan $laporan, string $outcome, ?int $decidedBy, ?string $remarks): void
    {
        if ($laporan->status_kunci) {
            return;
        }
        $lawatan = RekodLawatan::query()->withoutGlobalScope('access')->find($laporan->id_lawatan);
        $approver = $decidedBy ? User::query()->find($decidedBy) : null;
        if ($outcome !== 'lulus') {
            $laporan->forceFill(['status' => 'ditolak', 'catatan_pembetulan' => trim(($laporan->catatan_pembetulan ?? '').' Ditolak: '.($remarks ?? ''))])->save();
            if ($lawatan && $laporan->jenis === 'asal' && ! $lawatan->dikunci()) {
                $lawatan->forceFill(['status_lawatan' => 'dikembalikan', 'status_pengesahan' => 'ditolak'])->save();
                if ($lawatan->pegawai) {
                    $this->notifier->send('ptn.laporan_dikembalikan', $lawatan->pegawai, ['no_rujukan' => $lawatan->no_rujukan, 'ulasan' => mb_substr((string) $remarks, 0, 200)], '/admin/ptn/lapangan');
                }
            }

            return;
        }

        DB::transaction(function () use ($laporan, $lawatan, $approver, $decidedBy) {
            $docId = null;
            $uploader = $approver ?? $lawatan?->pegawai;
            if ($uploader !== null) {
                $path = tempnam(sys_get_temp_dir(), 'ptnlap');
                file_put_contents($path, $this->pdf($laporan, $approver));
                $name = 'Laporan-'.($laporan->kandungan['no_rujukan'] ?? $laporan->id).($laporan->jenis === 'tambahan' ? '-tambahan' : '').'.pdf';
                try {
                    $docId = $this->documents->store(new UploadedFile($path, $name, 'application/pdf', null, true), 'rekod_lawatan', $laporan->id_lawatan, 'laporan', $uploader, $laporan->klasifikasi, [
                        'no_rujukan_lawatan' => $laporan->kandungan['no_rujukan'] ?? null, 'jenis_laporan' => $laporan->jenis, 'cincangan_kandungan' => $laporan->cincangan_kandungan,
                    ]);
                } catch (DocumentRejected $e) {
                    // The locked snapshot stays authoritative; the PDF can be re-rendered from it.
                    report($e);
                } finally {
                    @unlink($path);
                }
            }
            $laporan->forceFill([
                'status' => 'disahkan', 'tarikh_sah' => now(), 'id_pengesah' => $decidedBy, 'id_dokumen_dms' => $docId, 'status_kunci' => true,
            ])->save();
            if ($lawatan && $laporan->jenis === 'asal' && ! $lawatan->dikunci()) {
                $lawatan->forceFill(['status_lawatan' => 'disahkan', 'status_pengesahan' => 'disahkan', 'id_pegawai_pengesah' => $decidedBy, 'tarikh_disahkan' => now()])->save();
                if ($lawatan->jadual && $lawatan->jadual->terbuka()) {
                    $lawatan->jadual->forceFill(['status_pelaksanaan' => 'selesai', 'versi' => $lawatan->jadual->versi + 1])->save();
                }
            }
        });
    }

    /** @return array<string, mixed> */
    public function snapshot(RekodLawatan $lawatan): array
    {
        $lawatan->loadMissing(['pegawai', 'item']);
        $lot = $this->lots->find($lawatan->lot_id);
        $pt = DB::selectOne('SELECT ST_Y(koordinat_lawatan) AS lat, ST_X(koordinat_lawatan) AS lng FROM rekod_lawatan WHERE id = ?', [$lawatan->id]);
        $photos = GambarLawatan::query()->denganKoordinat()->where('id_lawatan', $lawatan->id)->orderBy('id')->get();
        $issues = IsuPemantauan::query()->withoutGlobalScope('access')->where('id_lawatan', $lawatan->id)->orderBy('no_rujukan')->get();
        $points = [];
        if ($pt && $pt->lat !== null) {
            $points[] = ['lat' => (float) $pt->lat, 'lng' => (float) $pt->lng, 'luar' => $lawatan->dalam_lingkungan === false, 'label' => 'L'];
        }
        foreach ($photos as $i => $g) {
            if ($g->lat !== null) {
                $points[] = ['lat' => (float) $g->lat, 'lng' => (float) $g->lng, 'luar' => $g->status_pengesahan_lingkungan === 'luar', 'label' => 'G'.($i + 1)];
            }
        }

        return [
            'no_rujukan' => $lawatan->no_rujukan,
            'lot' => $lot ? ['id' => $lot['id'], 'no_lot' => $lot['no_lot'], 'mukim' => $this->ref->namaMukim($lot['kod_mukim']), 'daerah' => $this->ref->namaDaerah($lot['kod_daerah']), 'kategori_guna_tanah' => $lot['kategori_guna_tanah'], 'keluasan' => $lot['keluasan'], 'unit_keluasan' => $lot['unit_keluasan']] : null,
            'lawatan' => [
                'tarikh' => $lawatan->tarikh_lawatan->toDateString(), 'masa_mula' => $lawatan->masa_mula?->toIso8601String(), 'masa_tamat' => $lawatan->masa_tamat?->toIso8601String(),
                'pegawai' => $lawatan->pegawai?->name, 'keadaan_tapak' => $lawatan->keadaan_tapak, 'ringkasan_penemuan' => $lawatan->ringkasan_penemuan,
                'isu_pencerobohan' => $lawatan->isu_pencerobohan, 'isu_penyelenggaraan' => $lawatan->isu_penyelenggaraan, 'cadangan_tindakan' => $lawatan->cadangan_tindakan,
                'lat' => $pt?->lat !== null ? (float) $pt->lat : null, 'lng' => $pt?->lng !== null ? (float) $pt->lng : null,
                'jarak_dari_lot_m' => $lawatan->jarak_dari_lot_m !== null ? (float) $lawatan->jarak_dari_lot_m : null, 'dalam_lingkungan' => $lawatan->dalam_lingkungan,
                'templat' => $lawatan->templat?->nama, 'versi_templat' => $lawatan->versi_templat, 'sumber' => $lawatan->sumber,
            ],
            'item' => $lawatan->item->map(fn ($i) => ['kod_item' => $i->kod_item, 'kategori_item' => $i->kategori_item, 'label' => $i->label, 'status_item' => $i->status_item, 'catatan' => $i->catatan])->all(),
            'gambar' => $photos->values()->map(fn (GambarLawatan $g, int $i) => [
                'id' => $g->id, 'label' => 'G'.($i + 1), 'kod_item' => $g->kod_item, 'jenis' => $g->jenis, 'kapsyen' => $g->kapsyen,
                'tarikh_masa' => $g->tarikh_masa?->toIso8601String(), 'lat' => $g->lat, 'lng' => $g->lng, 'arah_kamera' => $g->arah_kamera !== null ? (float) $g->arah_kamera : null,
                'sumber_gambar' => $g->sumber_gambar, 'lingkungan' => $g->status_pengesahan_lingkungan, 'jarak_dari_lot_m' => $g->jarak_dari_lot_m !== null ? (float) $g->jarak_dari_lot_m : null,
                'perlu_semakan' => $g->perlu_semakan, 'keputusan_semakan' => $g->keputusan_semakan, 'id_dokumen' => $g->laluan_simpanan,
            ])->all(),
            'isu' => $issues->map(fn (IsuPemantauan $i) => ['no_rujukan' => $i->no_rujukan, 'kategori_isu' => $i->kategori_isu, 'tahap_keterukan' => $i->tahap_keterukan, 'keterangan' => $i->keterangan, 'id_aduan_berkaitan' => $i->id_aduan_berkaitan])->all(),
            'penyelenggaraan' => $lawatan->data_tambahan['penyelenggaraan'] ?? [],
            'cadangan' => $lawatan->data_tambahan['cadangan'] ?? [],
            'peta_svg' => $this->geo->svg($lawatan->lot_id, $points),
        ];
    }

    public function pdf(LaporanLawatan $laporan, ?User $pengesah = null): string
    {
        return Pdf::loadHTML($this->html($laporan, $pengesah))->setPaper('a4', 'portrait')->output();
    }

    public function html(LaporanLawatan $laporan, ?User $pengesah = null): string
    {
        $k = $laporan->kandungan;
        $draf = ! $laporan->status_kunci && $laporan->status !== 'disahkan' && $pengesah === null;
        $css = 'body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#18181b}h1{font-size:14px;margin:0}h2{font-size:11px;margin:12px 0 4px;border-bottom:1px solid #d4d4d8}'
            .'table{width:100%;border-collapse:collapse}th,td{border:1px solid #d4d4d8;padding:3px;text-align:left;vertical-align:top}th{background:#f4f4f5;width:28%}'
            .'.draf{color:#dc2626;font-weight:bold;font-size:12px}.foto{display:inline-block;width:46%;margin:0 2% 8px 0;vertical-align:top}.foto img{width:100%}.luar{color:#dc2626}.kecil{color:#52525b;font-size:8px}';
        $h = '<!doctype html><html lang="ms"><head><meta charset="utf-8"><style>'.$css.'</style></head><body>';
        $h .= '<h1>'.($laporan->jenis === 'tambahan' ? 'LAPORAN TAMBAHAN (PEMBETULAN) — ' : 'LAPORAN LAWATAN PEMANTAUAN TANAH — ').e((string) ($k['no_rujukan'] ?? '')).'</h1>';
        $h .= '<p class="kecil">Majlis Agama Islam Selangor · SISDATA MOD-PTN · Dijana '.$laporan->tarikh_jana->format('d/m/Y H:i').' · Cincangan kandungan '.substr($laporan->cincangan_kandungan, 0, 16).'…</p>';
        if ($draf) {
            $h .= '<p class="draf">DRAF — BELUM DISAHKAN</p>';
        }
        $lot = $k['lot'] ?? [];
        if ($laporan->jenis === 'tambahan') {
            $h .= '<h2>Pembetulan</h2><p>'.nl2br(e((string) ($k['pembetulan'] ?? ''))).'</p><p class="kecil">Merujuk laporan asal '.e((string) ($k['id_laporan_asal'] ?? '')).' disahkan '.e((string) ($k['tarikh_laporan_asal'] ?? '')).'. Laporan asal tidak dipinda.</p>';
        } else {
            $l = $k['lawatan'] ?? [];
            $row = fn (string $a, $b) => '<tr><th>'.e($a).'</th><td>'.e($b === null || $b === '' ? '—' : (string) $b).'</td></tr>';
            $h .= '<h2>Maklumat lot dan lawatan</h2><table>'
                .$row('No. lot', $lot['no_lot'] ?? null).$row('Mukim / Daerah', ($lot['mukim'] ?? '—').' / '.($lot['daerah'] ?? '—'))
                .$row('Kategori guna tanah', $lot['kategori_guna_tanah'] ?? null).$row('Tarikh lawatan', $l['tarikh'] ?? null)
                .$row('Pegawai', $l['pegawai'] ?? null).$row('Keadaan tapak', Lalai::KEADAAN_TAPAK[$l['keadaan_tapak'] ?? ''] ?? null)
                .$row('Koordinat lawatan', isset($l['lat']) ? sprintf('%.6f, %.6f', $l['lat'], $l['lng']) : null)
                .$row('Dalam sempadan lot', $l['dalam_lingkungan'] === null ? null : ($l['dalam_lingkungan'] ? 'Ya' : 'Tidak ('.($l['jarak_dari_lot_m'] ?? '?').' m dari sempadan)'))
                .$row('Senarai semak', ($l['templat'] ?? '—').' v'.($l['versi_templat'] ?? '1'))
                .'</table>';
            if (! empty($k['peta_svg'])) {
                $h .= '<h2>Peta lokasi</h2><img src="data:image/svg+xml;base64,'.base64_encode((string) $k['peta_svg']).'" width="420" height="300"/><p class="kecil">Poligon biru = sempadan lot; titik hijau = dalam sempadan, merah = di luar sempadan (L = lokasi lawatan, G = gambar).</p>';
            }
            $h .= '<h2>Senarai semak pemeriksaan</h2><table><tr><th>Item</th><th style="width:12%">Status</th><th>Catatan</th></tr>';
            foreach ($k['item'] ?? [] as $i) {
                $h .= '<tr><td>'.e($i['kod_item'].' · '.$i['label']).'</td><td>'.e(Lalai::STATUS_ITEM[$i['status_item']] ?? $i['status_item']).'</td><td>'.e((string) ($i['catatan'] ?? '')).'</td></tr>';
            }
            $h .= '</table><h2>Penemuan</h2>';
            if (($k['isu'] ?? []) === []) {
                $h .= '<p>Tiada penemuan isu.</p>';
            } else {
                $h .= '<table><tr><th>No.</th><th>Kategori</th><th>Tahap</th><th>Keterangan</th></tr>';
                foreach ($k['isu'] as $i) {
                    $h .= '<tr><td>'.e($i['no_rujukan']).'</td><td>'.e(Lalai::KATEGORI_ISU[$i['kategori_isu']] ?? $i['kategori_isu']).'</td><td>'.e(Lalai::TAHAP_KETERUKAN[$i['tahap_keterukan']] ?? $i['tahap_keterukan']).'</td><td>'.e($i['keterangan']).($i['id_aduan_berkaitan'] ? ' (aduan dibuka)' : '').'</td></tr>';
                }
                $h .= '</table>';
            }
            $h .= '<p><b>Ringkasan:</b> '.e((string) ($l['ringkasan_penemuan'] ?? '—')).'</p>';
            $h .= '<p><b>Isu pencerobohan:</b> '.e((string) ($l['isu_pencerobohan'] ?? '—')).' · <b>Isu penyelenggaraan:</b> '.e((string) ($l['isu_penyelenggaraan'] ?? '—')).'</p>';
            $h .= '<h2>Syor tindakan</h2><p>'.nl2br(e((string) ($l['cadangan_tindakan'] ?? '—'))).'</p>';
            $thumbs = GambarLawatan::query()->whereIn('id', array_column($k['gambar'] ?? [], 'id'))->pluck('imej_kecil', 'id');
            $h .= '<h2>Gambar bertanda</h2>';
            foreach ($k['gambar'] ?? [] as $g) {
                $img = $thumbs[$g['id']] ?? null;
                $h .= '<div class="foto">'.($img ? '<img src="'.e((string) $img).'"/>' : '<p>[gambar dalam DMS]</p>')
                    .'<div class="kecil"><b>'.e($g['label']).'</b> '.e((string) ($g['kapsyen'] ?? '')).' · '.e((string) ($g['tarikh_masa'] ?? '—'))
                    .'<br>'.($g['lat'] !== null ? sprintf('%.6f, %.6f', $g['lat'], $g['lng']) : 'tiada koordinat').($g['arah_kamera'] !== null ? ' · arah '.round($g['arah_kamera']).'°' : '')
                    .' · '.($g['sumber_gambar'] === 'galeri' ? 'GALERI' : 'kamera aplikasi')
                    .' · <span class="'.($g['lingkungan'] === 'dalam' ? '' : 'luar').'">'.e(['dalam' => 'dalam sempadan', 'luar' => 'DI LUAR SEMPADAN ('.($g['jarak_dari_lot_m'] ?? '?').' m)', 'tiada_koordinat' => 'TIADA KOORDINAT'][$g['lingkungan']] ?? $g['lingkungan']).'</span></div></div>';
            }
        }
        $h .= '<h2>Pengesahan</h2><p>'.($laporan->status_kunci || $pengesah ? 'Disahkan oleh '.e((string) ($pengesah->name ?? $laporan->pengesah->name ?? '—')).' pada '.e(($laporan->tarikh_sah ?? now())->format('d/m/Y H:i')).'. Laporan ini dikunci daripada pindaan; pembetulan melalui laporan tambahan.' : 'Menunggu pengesahan pegawai pengesah.').'</p>';

        return $h.'</body></html>';
    }

    /** Download (audited): the DMS copy once locked, otherwise a DRAF rendering. */
    public function muatTurunUrl(LaporanLawatan $laporan, User $user): ?string
    {
        $this->audit->record('muat_turun', 'ptn', 'laporan_lawatan', $laporan->id, meta: ['klasifikasi' => $laporan->klasifikasi]);

        return $laporan->id_dokumen_dms ? $this->documents->temporaryUrl($laporan->id_dokumen_dms, $user) : null;
    }
}
