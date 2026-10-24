<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Models\PanelJurukur;
use App\Modules\Tsu\Models\SebutHargaTawaran;
use App\Modules\Tsu\Models\SebutHargaUkur;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\AliranTsu;
use App\Modules\Tsu\Support\Makluman;
use App\Modules\Tsu\Support\Penomboran;
use App\Modules\Tsu\Support\Wang;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * SRS-TSU-08..10: quotation invitation to panel surveyors → offers → comparison
 * table (price, completion time, experience) → recommendation + justification →
 * authorised officer's approval (MOD-WFE) → appointment with licence number and
 * letter of acceptance. Segregation of duties: the urusan registrar may not
 * evaluate, and neither registrar nor evaluator may approve. Offer prices are
 * encrypted and hidden until the closing date.
 */
class SebutHargaService
{
    public function __construct(
        private WorkflowEngine $workflow,
        private Parameters $parameters,
        private DocumentStore $documents,
        private AuditLogger $audit,
        private Makluman $makluman,
        private UrusanService $urusan,
    ) {}

    /**
     * @param  array{tarikh_tutup: string, skop_kerja: string, jurukur_ids: list<int>}  $data
     */
    public function create(UrusanTanah $urusan, array $data, User $user): SebutHargaUkur
    {
        $this->urusan->assertOpen($urusan);
        $min = (int) $this->parameters->get('tsu.minimum_jurukur_dijemput', 3);
        $ids = array_values(array_unique(array_map('intval', $data['jurukur_ids'])));
        $panel = PanelJurukur::query()->whereIn('id', $ids)->get();
        if ($panel->count() !== count($ids) || $panel->contains(fn (PanelJurukur $p) => ! $p->layak())) {
            throw ValidationException::withMessages(['jurukur_ids' => [__('tsu::tsu.sebutharga.jurukur_tidak_layak')]]);
        }
        if (count($ids) < $min) {
            throw ValidationException::withMessages(['jurukur_ids' => [__('tsu::tsu.sebutharga.minimum', ['n' => $min])]]);
        }
        $tutup = Carbon::parse($data['tarikh_tutup']);
        if ($tutup->lte(now())) {
            throw ValidationException::withMessages(['tarikh_tutup' => [__('tsu::tsu.sebutharga.tarikh_tutup')]]);
        }

        $sh = DB::transaction(function () use ($urusan, $data, $user, $ids, $tutup) {
            $sh = new SebutHargaUkur;
            $sh->forceFill([
                'urusan_id' => $urusan->id, 'no_jemputan' => Penomboran::next('TSU-SH', 4), 'tarikh_jemputan' => today(), 'tarikh_tutup' => $tutup,
                'skop_kerja' => $data['skop_kerja'], 'status' => 'dibuka', 'id_pencipta' => $user->id,
            ])->save();
            foreach ($ids as $id) {
                SebutHargaTawaran::query()->create(['sebutharga_id' => $sh->id, 'jurukur_id' => $id, 'status' => 'dijemput']);
            }

            return $sh;
        });

        // SRS-TSU-08 "penjanaan dokumen jemputan": the invitation is rendered and kept in MOD-DMS.
        try {
            $path = tempnam(sys_get_temp_dir(), 'tsu-sh').'.pdf';
            file_put_contents($path, $this->pdf($sh->load('tawaran.jurukur', 'urusan')));
            $docId = $this->documents->store(new UploadedFile($path, str_replace('/', '-', $sh->no_jemputan).'.pdf', 'application/pdf', null, true), 'urusan_tanah', $urusan->id, 'surat_rasmi', $user, 'dalaman', ['pautan_induk' => '/admin/tsu/urusan/'.$urusan->id]);
            $sh->forceFill(['id_dokumen_jemputan' => $docId])->save();
            UrusanDokumen::query()->create(['urusan_id' => $urusan->id, 'id_dokumen_dms' => $docId, 'kategori_dokumen' => 'surat_jemputan', 'nama_fail' => $sh->no_jemputan.'.pdf', 'tarikh_dokumen' => today(), 'tarikh_muat_naik' => now(), 'id_pemuat_naik' => $user->id]);
            @unlink($path);
        } catch (Throwable $e) {
            report($e);
        }

        return $sh->refresh();
    }

    public function pdf(SebutHargaUkur $sh): string
    {
        $rows = '';
        foreach ($sh->tawaran as $i => $t) {
            $rows .= '<tr><td>'.($i + 1).'</td><td>'.e($t->jurukur->nama_firma).'</td><td>'.e($t->jurukur->no_lesen_jurukur).'</td></tr>';
        }
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #999;padding:4px}</style></head><body>'
            .'<h2>Jemputan Sebut Harga Kerja Ukur</h2><p>No. jemputan: <b>'.e($sh->no_jemputan).'</b><br>Rujukan urusan: '.e($sh->urusan->no_rujukan_mais).'<br>Tarikh jemputan: '.$sh->tarikh_jemputan->format('d/m/Y')
            .'<br>Tarikh tutup: <b>'.$sh->tarikh_tutup->format('d/m/Y H:i').'</b></p><h3>Skop kerja</h3><p>'.nl2br(e($sh->skop_kerja)).'</p>'
            .'<h3>Senarai jurukur berlesen dijemput</h3><table><tr><th>Bil.</th><th>Firma</th><th>No. lesen</th></tr>'.$rows.'</table>'
            .'<p>Tawaran hendaklah menyatakan harga (RM), tempoh siap (hari) dan pengalaman berkaitan. Tawaran selepas tarikh tutup tidak diterima.</p></body></html>';

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    /** Record one received offer (before the closing date only). */
    public function recordOffer(SebutHargaTawaran $t, array $data, User $user): SebutHargaTawaran
    {
        $sh = $t->sebutharga;
        if ($sh->status !== 'dibuka' || $sh->ditutup()) {
            throw ValidationException::withMessages(['harga_tawaran' => [__('tsu::tsu.sebutharga.sudah_tutup')]]);
        }
        $t->forceFill([
            'status' => 'diterima', 'harga_tawaran' => Wang::norm((string) $data['harga_tawaran']), 'tempoh_siap_hari' => (int) $data['tempoh_siap_hari'],
            'pengalaman_tahun' => $data['pengalaman_tahun'] ?? $t->jurukur->pengalaman_tahun, 'tarikh_terima' => now(),
        ])->save();

        return $t->refresh();
    }

    /**
     * SRS-TSU-09 comparison table. Prices exist only after the closing date
     * (403 before). Score = weighted price / time / experience (configurable).
     *
     * @return list<array<string, mixed>>
     */
    public function comparison(SebutHargaUkur $sh, User $user): array
    {
        if (! $sh->ditutup()) {
            throw new HttpException(403, __('tsu::tsu.sebutharga.terkawal'));
        }
        $w = (array) $this->parameters->get('tsu.pemberat_sebutharga', ['harga' => 60, 'tempoh' => 25, 'pengalaman' => 15]);
        $offers = $sh->tawaran->load('jurukur')->filter(fn (SebutHargaTawaran $t) => $t->status === 'diterima' && $t->harga_tawaran !== null);
        $minHarga = $offers->min(fn ($t) => (float) $t->harga_tawaran) ?: 1;
        $minTempoh = $offers->min(fn ($t) => (int) $t->tempoh_siap_hari) ?: 1;
        $maxPengalaman = $offers->max(fn ($t) => (int) $t->pengalaman_tahun) ?: 1;
        $rows = [];
        foreach ($sh->tawaran as $t) {
            $ok = $t->status === 'diterima' && $t->harga_tawaran !== null;
            $markah = $ok ? round(
                ($minHarga / max(0.01, (float) $t->harga_tawaran)) * (float) ($w['harga'] ?? 60)
                + ($minTempoh / max(1, (int) $t->tempoh_siap_hari)) * (float) ($w['tempoh'] ?? 25)
                + ((int) $t->pengalaman_tahun / $maxPengalaman) * (float) ($w['pengalaman'] ?? 15), 2) : null;
            $rows[] = [
                'id' => $t->id, 'jurukur_id' => $t->jurukur_id, 'nama_firma' => $t->jurukur->nama_firma, 'no_lesen_jurukur' => $t->jurukur->no_lesen_jurukur,
                'status' => $t->status, 'harga_tawaran' => $ok ? Wang::norm($t->harga_tawaran) : null, 'tempoh_siap_hari' => $t->tempoh_siap_hari,
                'pengalaman_tahun' => $t->pengalaman_tahun, 'markah' => $t->markah !== null ? (float) $t->markah : $markah, 'markah_dikira' => $markah,
                'catatan_penilaian' => $t->catatan_penilaian, 'indikator_disyorkan' => $t->indikator_disyorkan,
            ];
        }
        usort($rows, fn ($a, $b) => ($b['markah'] ?? -1) <=> ($a['markah'] ?? -1));
        foreach ($rows as $i => $r) {
            $rows[$i]['kedudukan'] = $r['markah'] !== null ? $i + 1 : null;
        }
        $this->audit->record('lihat', 'tsu', 'sebutharga_ukur', $sh->id, meta: ['jadual_perbandingan' => true, 'klasifikasi' => 'sulit']);

        return $rows;
    }

    /**
     * Recommendation + justification, then the approval flow (SRS-TSU-09).
     *
     * @param  array{id_tawaran_disyorkan: int, justifikasi_pemilihan: string, catatan?: array<int|string, string|null>}  $data
     */
    public function evaluate(SebutHargaUkur $sh, array $data, User $user): SebutHargaUkur
    {
        $urusan = $sh->urusan;
        $this->urusan->assertOpen($urusan);
        if (! $sh->ditutup() || ! in_array($sh->status, ['dibuka', 'ditutup', 'dinilai', 'ditolak'], true)) {
            throw ValidationException::withMessages(['status' => [__('tsu::tsu.sebutharga.belum_tutup')]]);
        }
        if ($urusan->id_pendaftar === $user->id) {
            throw ValidationException::withMessages(['id_penilai' => [__('tsu::tsu.sebutharga.pengasingan_penilai')]]);
        }
        $rows = collect($this->comparison($sh, $user))->keyBy('id');
        $chosen = $rows->get((int) $data['id_tawaran_disyorkan']);
        if ($chosen === null || $chosen['harga_tawaran'] === null) {
            throw ValidationException::withMessages(['id_tawaran_disyorkan' => [__('tsu::tsu.sebutharga.tawaran_tidak_sah')]]);
        }

        DB::transaction(function () use ($sh, $data, $user, $rows) {
            foreach ($sh->tawaran as $t) {
                $t->forceFill([
                    'markah' => $rows->get($t->id)['markah_dikira'] ?? null,
                    'catatan_penilaian' => $data['catatan'][$t->id] ?? $t->catatan_penilaian,
                    'indikator_disyorkan' => $t->id === (int) $data['id_tawaran_disyorkan'],
                ])->save();
                if ($t->status === 'dijemput') {
                    $t->forceFill(['status' => 'tidak_respons'])->save();
                }
            }
            $sh->forceFill(['status' => 'dinilai', 'id_tawaran_disyorkan' => (int) $data['id_tawaran_disyorkan'], 'justifikasi_pemilihan' => $data['justifikasi_pemilihan'], 'id_penilai' => $user->id])->save();
        });

        try {
            $instance = $this->workflow->start(AliranTsu::LANTIKAN, AliranTsu::SUBJEK_SEBUTHARGA, $sh->id, $user, [
                'kod_daerah' => $urusan->kod_daerah, 'nilai' => (float) $chosen['harga_tawaran'], 'pautan' => '/admin/tsu/urusan/'.$urusan->id.'?tab=ukur',
            ], 'Lantikan jurukur '.$chosen['nama_firma'].' — '.$sh->no_jemputan);
        } catch (InvalidArgumentException) {
            throw new HttpException(422, __('tsu::tsu.aliran_tiada', ['kod' => AliranTsu::LANTIKAN]));
        }
        $sh->forceFill(['status' => 'menunggu_kelulusan', 'id_aliran_kerja' => $instance])->save();

        return $sh->refresh();
    }

    public function onApproval(SebutHargaUkur $sh, string $outcome, ?int $decidedBy, ?string $remarks): void
    {
        if ($sh->status !== 'menunggu_kelulusan') {
            return;
        }
        $urusan = $sh->urusan;
        // Segregation of duties: neither the registrar nor the evaluator may approve the appointment.
        if ($outcome === 'lulus' && $decidedBy !== null && in_array($decidedBy, [$urusan->id_pendaftar, $sh->id_penilai], true)) {
            $outcome = 'tolak';
            $remarks = (string) __('tsu::tsu.sebutharga.pengasingan_pelulus');
        }
        DB::transaction(function () use ($sh, $outcome, $decidedBy, $remarks, $urusan) {
            UrusanKeputusan::query()->create(['urusan_id' => $urusan->id, 'konteks' => 'sebutharga', 'jenis_keputusan' => $outcome, 'justifikasi' => $remarks, 'id_pelulus' => $decidedBy, 'id_aliran' => $sh->id_aliran_kerja]);
            $sh->forceFill([
                'status' => match ($outcome) {
                    'lulus' => 'diluluskan', 'tolak' => 'ditolak', default => 'dinilai'
                },
                'id_pelulus' => $decidedBy, 'tarikh_kelulusan' => now(),
            ])->save();
        });
        $this->makluman->hantar('tsu.keputusan_kelulusan', $urusan->pegawai, ['no_rujukan' => $urusan->no_rujukan_mais, 'peringkat' => 'Lantikan jurukur '.$sh->no_jemputan, 'keputusan' => (string) __('tsu::tsu.keputusan.'.$outcome)], '/admin/tsu/urusan/'.$urusan->id.'?tab=ukur');
    }

    /**
     * SRS-TSU-10: appointment of the approved surveyor.
     *
     * @param  array{no_surat_setuju_terima: string, tarikh_surat_setuju_terima?: string|null, nilai_kontrak?: string|null, tarikh_mula: string, tempoh_siap_hari?: int|null, skop_kerja?: string|null}  $data
     */
    public function appoint(SebutHargaUkur $sh, array $data, User $user): LantikanJurukur
    {
        $urusan = $sh->urusan;
        $this->urusan->assertOpen($urusan);
        if ($sh->status !== 'diluluskan') {
            throw ValidationException::withMessages(['status' => [__('tsu::tsu.sebutharga.belum_lulus')]]);
        }
        if (LantikanJurukur::query()->where('sebutharga_id', $sh->id)->exists()) {
            throw ValidationException::withMessages(['status' => [__('tsu::tsu.sebutharga.sudah_dilantik')]]);
        }
        $t = SebutHargaTawaran::query()->with('jurukur')->findOrFail($sh->id_tawaran_disyorkan);
        $mula = Carbon::parse($data['tarikh_mula']);
        $tempoh = (int) ($data['tempoh_siap_hari'] ?? $t->tempoh_siap_hari ?? 60);
        $l = new LantikanJurukur;
        $l->forceFill([
            'urusan_id' => $urusan->id, 'sebutharga_id' => $sh->id, 'jurukur_id' => $t->jurukur_id,
            'no_surat_setuju_terima' => $data['no_surat_setuju_terima'], 'tarikh_surat_setuju_terima' => $data['tarikh_surat_setuju_terima'] ?? today()->toDateString(),
            'nilai_kontrak' => Wang::norm($data['nilai_kontrak'] ?? $t->harga_tawaran), 'tarikh_mula' => $mula->toDateString(), 'tempoh_siap_hari' => $tempoh,
            'tarikh_jangka_siap' => $mula->copy()->addDays($tempoh)->toDateString(), 'skop_kerja' => $data['skop_kerja'] ?? $sh->skop_kerja,
            'status' => 'aktif', 'id_pencipta' => $user->id,
        ])->save();

        return $l->refresh();
    }
}
