<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Ptn\Models\GambarLawatan;
use App\Modules\Ptn\Models\ItemSenaraiSemak;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\LogPenyegerakan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\TemplatSenaraiSemak;
use App\Modules\Ptn\Support\Geometri;
use App\Modules\Ptn\Support\Lalai;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PTN-03/05/06/07: recording a visit — from the PWA (offline, synced
 * later) or the web. Sync is idempotent on the device-generated
 * id_luar_talian (a retry returns the same record), and never loses data: a
 * visit whose schedule changed after download (reassigned, cancelled,
 * already done) is still stored, as status "konflik", for a supervisor to
 * accept or reject (acceptance #2).
 */
class LawatanService
{
    public function __construct(
        private LotRegistry $lots,
        private Geometri $geo,
        private DocumentStore $documents,
        private Parameters $parameters,
        private Penjadualan $penjadualan,
        private PenemuanService $penemuan,
        private LaporanService $laporan,
        private RisikoService $risiko,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{lawatan: RekodLawatan, status: string}
     */
    public function cipta(array $data, User $user, string $sumber = 'web'): array
    {
        if (! empty($data['id_luar_talian']) && ($existing = RekodLawatan::query()->where('id_luar_talian', $data['id_luar_talian'])->first())) {
            if ($existing->id_pegawai !== $user->id) {
                throw ValidationException::withMessages(['id_luar_talian' => __('ptn::ptn.id_luar_talian_wujud')]);
            }
            $this->log($user, $data['id_luar_talian'], $existing->id, 'lawatan', 'pendua');

            return ['lawatan' => $existing, 'status' => 'pendua'];
        }

        $jadual = null;
        if (! empty($data['id_jadual'])) {
            $jadual = JadualPemantauan::query()->find($data['id_jadual']);
            if ($jadual === null) {
                throw ValidationException::withMessages(['id_jadual' => __('ptn::ptn.jadual_tiada')]);
            }
        }
        $lotId = $jadual->lot_id ?? (string) ($data['lot_id'] ?? '');
        $lot = $lotId !== '' ? $this->lots->find($lotId, $user) : null;
        if ($lot === null) {
            throw ValidationException::withMessages(['lot_id' => __('ptn::ptn.lot_tiada')]);
        }

        $konflik = $jadual ? $this->konflik($jadual, $user, isset($data['versi_jadual']) ? (int) $data['versi_jadual'] : null) : null;
        $templat = $jadual->templat ?? $this->penjadualan->templatUntuk($lot['kategori_guna_tanah'], $jadual->jenis_pemeriksaan ?? 'rutin');

        $lawatan = DB::transaction(function () use ($data, $user, $sumber, $jadual, $lot, $konflik, $templat) {
            $lawatan = new RekodLawatan;
            $lawatan->forceFill([
                'no_rujukan' => 'PTN-'.now()->format('Y').'-'.str_pad((string) DB::selectOne("SELECT nextval('ptn_no_lawatan_seq') AS n")->n, 6, '0', STR_PAD_LEFT),
                'id_jadual' => $jadual?->id,
                'lot_id' => $lot['id'], 'kod_daerah' => $lot['kod_daerah'], 'kod_mukim' => $lot['kod_mukim'], 'klasifikasi' => $lot['klasifikasi'],
                'id_templat' => $templat?->id, 'versi_templat' => $templat?->versi,
                'tarikh_lawatan' => $data['tarikh_lawatan'] ?? now()->toDateString(),
                'masa_mula' => $data['masa_mula'] ?? now(),
                'id_pegawai' => $user->id,
                'status_lawatan' => $konflik ? 'konflik' : 'draf',
                'sebab_konflik' => $konflik,
                'id_luar_talian' => $data['id_luar_talian'] ?? null,
                'sumber' => $sumber,
                'disegerak_pada' => $sumber === 'pwa' ? now() : null,
                'sintetik' => (bool) ($data['sintetik'] ?? false),
            ]);
            $lawatan->save();
            $this->isi($lawatan, $data);
            if ($jadual && ! $konflik && $jadual->status_pelaksanaan !== 'dalam_pelaksanaan') {
                $jadual->forceFill(['status_pelaksanaan' => 'dalam_pelaksanaan'])->save();
            }

            return $lawatan;
        });
        if (! empty($data['id_luar_talian'])) {
            $this->log($user, $data['id_luar_talian'], $lawatan->id, 'lawatan', $konflik ? 'konflik' : 'diterima', $konflik ? ['sebab' => $konflik] : []);
        }

        return ['lawatan' => $lawatan->refresh(), 'status' => $konflik ? 'konflik' : 'diterima'];
    }

    /** @param array<string, mixed> $data */
    public function simpan(RekodLawatan $lawatan, array $data): RekodLawatan
    {
        if (! $lawatan->bolehDiubah()) {
            throw ValidationException::withMessages(['lawatan' => __('ptn::ptn.lawatan_tidak_boleh_diubah')]);
        }
        DB::transaction(fn () => $this->isi($lawatan, $data));

        return $lawatan->refresh();
    }

    /** @param array<string, mixed> $data */
    private function isi(RekodLawatan $lawatan, array $data): void
    {
        $fields = ['keadaan_tapak', 'ringkasan_penemuan', 'isu_pencerobohan', 'isu_penyelenggaraan', 'cadangan_tindakan', 'masa_tamat', 'ketepatan_gps_m', 'tarikh_lawatan', 'masa_mula'];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $lawatan->setAttribute($f, $data[$f]);
            }
        }
        if (array_key_exists('penyelenggaraan', $data) || array_key_exists('cadangan', $data)) {
            $lawatan->data_tambahan = [
                'penyelenggaraan' => array_values((array) ($data['penyelenggaraan'] ?? $lawatan->data_tambahan['penyelenggaraan'] ?? [])),
                'cadangan' => array_values((array) ($data['cadangan'] ?? $lawatan->data_tambahan['cadangan'] ?? [])),
            ];
        }
        $lawatan->save();
        if (isset($data['lat'], $data['lng']) && is_numeric($data['lat']) && is_numeric($data['lng'])) {
            $jarak = $this->geo->jarak($lawatan->lot_id, (float) $data['lat'], (float) $data['lng']);
            $tol = (float) $this->parameters->get('ptn.toleransi_lingkungan_m', 10);
            DB::update('UPDATE rekod_lawatan SET koordinat_lawatan = ST_SetSRID(ST_MakePoint(?, ?), 4326), jarak_dari_lot_m = ?, dalam_lingkungan = ? WHERE id = ?', [
                (float) $data['lng'], (float) $data['lat'], $jarak, $jarak === null ? null : $jarak <= $tol, $lawatan->id,
            ]);
        }
        if (isset($data['item']) && is_array($data['item'])) {
            $this->simpanItem($lawatan, $data['item']);
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function simpanItem(RekodLawatan $lawatan, array $items): void
    {
        $templat = $lawatan->id_templat ? TemplatSenaraiSemak::query()->find($lawatan->id_templat) : null;
        $def = collect($templat->senarai_item ?? [])->keyBy('kod');
        foreach ($items as $it) {
            $kod = (string) ($it['kod_item'] ?? '');
            $status = (string) ($it['status_item'] ?? '');
            if ($kod === '' || ! array_key_exists($status, Lalai::STATUS_ITEM)) {
                continue;
            }
            $d = $def->get($kod);
            $kategoriIsu = $status === 'baik' ? null : (string) ($it['kategori_isu'] ?? $d['kategori_isu'] ?? 'lain');
            if ($kategoriIsu !== null && ! array_key_exists($kategoriIsu, Lalai::KATEGORI_ISU)) {
                $kategoriIsu = 'lain';
            }
            $tahap = match ($status) {
                'kritikal' => 'kritikal',
                'isu' => in_array($it['tahap_keterukan'] ?? null, ['rendah', 'sederhana', 'tinggi'], true) ? $it['tahap_keterukan'] : 'sederhana',
                default => null,
            };
            ItemSenaraiSemak::query()->updateOrCreate(['id_lawatan' => $lawatan->id, 'kod_item' => $kod], [
                'id_templat_senarai_semak' => $templat?->id,
                'kategori_item' => (string) ($d['kategori'] ?? $it['kategori_item'] ?? 'keadaan_fizikal'),
                'label' => (string) ($d['label'] ?? $it['label'] ?? $kod),
                'status_item' => $status,
                'catatan' => isset($it['catatan']) ? mb_substr((string) $it['catatan'], 0, 2000) : null,
                'kategori_isu' => $kategoriIsu,
                'tahap_keterukan' => $tahap,
            ]);
        }
    }

    /** Why a synced visit conflicts with the server's schedule, or null. */
    public function konflik(JadualPemantauan $jadual, User $user, ?int $versi): ?string
    {
        if ($jadual->status_pelaksanaan === 'dibatalkan') {
            return (string) __('ptn::ptn.konflik.dibatalkan');
        }
        if ($jadual->id_pegawai_bertugas !== null && $jadual->id_pegawai_bertugas !== $user->id) {
            return (string) __('ptn::ptn.konflik.diagih_semula');
        }
        if (RekodLawatan::query()->withoutGlobalScope('access')->where('id_jadual', $jadual->id)->whereNotIn('status_lawatan', ['ditolak'])->exists()) {
            return (string) __('ptn::ptn.konflik.sudah_direkod');
        }
        if ($versi !== null && $versi !== $jadual->versi) {
            return (string) __('ptn::ptn.konflik.versi', ['a' => $versi, 'b' => $jadual->versi]);
        }

        return null;
    }

    /**
     * SRS-PTN-06/07: photo → DMS (gambar_tapak), geotag recorded once
     * (immutable), checked against the lot polygon; outside / no location →
     * flagged for supervisor review. EXIF GPS is used when the device gave no
     * position (gallery photo). Idempotent on id_luar_talian.
     *
     * @param  array<string, mixed>  $meta
     * @return array{gambar: GambarLawatan, status: string}
     */
    public function tambahGambar(RekodLawatan $lawatan, UploadedFile $file, array $meta, User $user): array
    {
        if (! empty($meta['id_luar_talian']) && ($g = GambarLawatan::query()->where('id_luar_talian', $meta['id_luar_talian'])->first())) {
            $this->log($user, (string) $meta['id_luar_talian'], $lawatan->id, 'gambar', 'pendua');

            return ['gambar' => $g, 'status' => 'pendua'];
        }
        if ($lawatan->dikunci() || ! in_array($lawatan->status_lawatan, ['draf', 'dikembalikan', 'konflik'], true)) {
            throw ValidationException::withMessages(['lawatan' => __('ptn::ptn.lawatan_tidak_boleh_diubah')]);
        }
        $sumber = ($meta['sumber_gambar'] ?? 'kamera') === 'galeri' ? 'galeri' : 'kamera';
        $lat = isset($meta['lat']) && is_numeric($meta['lat']) ? (float) $meta['lat'] : null;
        $lng = isset($meta['lng']) && is_numeric($meta['lng']) ? (float) $meta['lng'] : null;
        $time = ! empty($meta['tarikh_masa']) ? Carbon::parse((string) $meta['tarikh_masa']) : null;
        if ($lat === null || $lng === null) {
            [$lat, $lng, $exifTime] = $this->exif($file);
            $time ??= $exifTime;
        }
        $thumb = $this->lakaranKecil($file);

        $docId = $this->documents->store($file, 'rekod_lawatan', $lawatan->id, 'gambar_tapak', $user, $lawatan->klasifikasi, [
            'no_rujukan_lawatan' => $lawatan->no_rujukan, 'lat' => $lat, 'lng' => $lng, 'tarikh_masa' => $time?->toIso8601String(),
            'arah_kamera' => $meta['arah_kamera'] ?? null, 'sumber_gambar' => $sumber,
        ]);
        $jarak = ($lat !== null && $lng !== null) ? $this->geo->jarak($lawatan->lot_id, $lat, $lng) : null;
        $tol = (float) $this->parameters->get('ptn.toleransi_lingkungan_m', 10);
        $status = match (true) {
            $lat === null || $lng === null => 'tiada_koordinat',
            $jarak !== null && $jarak <= $tol => 'dalam',
            default => 'luar',
        };

        $g = new GambarLawatan;
        $g->forceFill([
            'id_lawatan' => $lawatan->id, 'laluan_simpanan' => $docId, 'tarikh_masa' => $time ?? now(),
            'arah_kamera' => isset($meta['arah_kamera']) && is_numeric($meta['arah_kamera']) ? fmod((float) $meta['arah_kamera'] + 360, 360) : null,
            'ketepatan_m' => isset($meta['ketepatan_m']) && is_numeric($meta['ketepatan_m']) ? (float) $meta['ketepatan_m'] : null,
            'sumber_gambar' => $sumber, 'status_pengesahan_lingkungan' => $status, 'jarak_dari_lot_m' => $jarak,
            'perlu_semakan' => $status !== 'dalam',
            'tag_rekod_tanah' => $lawatan->lot_id, 'tag_lapisan_gis' => 'ptn.pemantauan',
            'kod_item' => $meta['kod_item'] ?? null, 'jenis' => in_array($meta['jenis'] ?? null, ['sebelum', 'selepas'], true) ? $meta['jenis'] : 'umum',
            'kapsyen' => isset($meta['kapsyen']) ? mb_substr((string) $meta['kapsyen'], 0, 255) : null,
            'nama_fail' => mb_substr($file->getClientOriginalName(), 0, 255), 'imej_kecil' => $thumb,
            'id_luar_talian' => $meta['id_luar_talian'] ?? null,
        ])->save();
        if ($lat !== null && $lng !== null) {
            // Set once at insert time; the trigger forbids any later change.
            DB::update('UPDATE gambar_lawatan SET koordinat_gambar = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ? AND koordinat_gambar IS NULL', [$lng, $lat, $g->id]);
        }
        if (! empty($meta['id_luar_talian'])) {
            $this->log($user, (string) $meta['id_luar_talian'], $lawatan->id, 'gambar', 'diterima', ['lingkungan' => $status]);
        }

        return ['gambar' => $g->refresh(), 'status' => 'diterima'];
    }

    /** Supervisor decision on a flagged photo (SRS-PTN-07). */
    public function semakGambar(GambarLawatan $g, string $keputusan, ?string $catatan, User $by): GambarLawatan
    {
        $g->forceFill(['keputusan_semakan' => $keputusan, 'catatan_semakan' => $catatan, 'id_penyemak' => $by->id, 'tarikh_semakan' => now()])->save();

        return $g;
    }

    /**
     * Submit for validation: checklist complete, minimum photos, then
     * findings → issues/complaints/notifications, report generated and sent
     * to MOD-WFE, risk recomputed.
     *
     * @return array{lawatan: RekodLawatan, amaran: list<string>}
     */
    public function hantar(RekodLawatan $lawatan, User $user): array
    {
        if ($lawatan->status_lawatan === 'konflik') {
            throw ValidationException::withMessages(['lawatan' => __('ptn::ptn.konflik_belum_selesai')]);
        }
        if (! in_array($lawatan->status_lawatan, ['draf', 'dikembalikan'], true)) {
            throw ValidationException::withMessages(['lawatan' => __('ptn::ptn.lawatan_tidak_boleh_diubah')]);
        }
        $templat = $lawatan->id_templat ? TemplatSenaraiSemak::query()->find($lawatan->id_templat) : null;
        $answered = $lawatan->item()->pluck('kod_item')->all();
        $missing = array_values(array_diff(array_column($templat->senarai_item ?? [], 'kod'), $answered));
        if ($missing !== []) {
            throw ValidationException::withMessages(['item' => __('ptn::ptn.item_belum_diisi', ['senarai' => implode(', ', $missing)])]);
        }
        $min = (int) $this->parameters->get('ptn.gambar_minimum', 1);
        if ($lawatan->gambar()->count() < $min) {
            throw ValidationException::withMessages(['gambar' => __('ptn::ptn.gambar_minimum', ['n' => $min])]);
        }
        if ($lawatan->keadaan_tapak === null) {
            throw ValidationException::withMessages(['keadaan_tapak' => __('ptn::ptn.keadaan_wajib')]);
        }

        $lawatan->forceFill([
            'status_lawatan' => 'dihantar', 'status_pengesahan' => 'menunggu',
            'masa_tamat' => $lawatan->masa_tamat ?? now(), 'disegerak_pada' => $lawatan->sumber === 'pwa' ? now() : $lawatan->disegerak_pada,
        ])->save();

        $amaran = $this->penemuan->proses($lawatan, $user);
        $this->risiko->kira([$lawatan->lot_id]);
        $laporan = $this->laporan->jana($lawatan, $user);
        if ($laporan->id_aliran === null) {
            $amaran[] = (string) __('ptn::ptn.aliran_tiada');
        }
        if ($lawatan->id_luar_talian) {
            $this->log($user, $lawatan->id_luar_talian, $lawatan->id, 'hantar', 'diterima');
        }

        return ['lawatan' => $lawatan->refresh(), 'amaran' => $amaran];
    }

    /** Supervisor resolves a sync conflict: accept (becomes a draft, unlinked from a cancelled/reassigned schedule) or reject (kept, not counted). */
    public function selesaikanKonflik(RekodLawatan $lawatan, string $keputusan, ?string $catatan, User $by): RekodLawatan
    {
        if ($lawatan->status_lawatan !== 'konflik') {
            throw ValidationException::withMessages(['lawatan' => __('ptn::ptn.bukan_konflik')]);
        }
        if ($keputusan === 'terima') {
            $jadual = $lawatan->jadual;
            $keep = $jadual && $jadual->terbuka() && in_array($jadual->id_pegawai_bertugas, [null, $lawatan->id_pegawai], true) && ! RekodLawatan::query()->withoutGlobalScope('access')->where('id_jadual', $jadual->id)->where('id', '!=', $lawatan->id)->whereNotIn('status_lawatan', ['ditolak'])->exists();
            $lawatan->forceFill(['status_lawatan' => 'draf', 'id_jadual' => $keep ? $lawatan->id_jadual : null, 'sebab_konflik' => trim(($lawatan->sebab_konflik ?? '').' — diterima: '.($catatan ?? ''))])->save();
        } else {
            $lawatan->forceFill(['status_lawatan' => 'ditolak', 'sebab_konflik' => trim(($lawatan->sebab_konflik ?? '').' — ditolak: '.($catatan ?? ''))])->save();
        }
        if ($lawatan->id_luar_talian) {
            $this->log($by, $lawatan->id_luar_talian, $lawatan->id, 'lawatan', $keputusan === 'terima' ? 'diterima' : 'ditolak', ['penyelesaian_konflik' => true]);
        }

        return $lawatan;
    }

    /** @param array<string, mixed> $butiran */
    private function log(User $user, string $idLuar, ?string $lawatanId, string $jenis, string $status, array $butiran = []): void
    {
        $l = new LogPenyegerakan;
        $l->forceFill(['id_pengguna' => $user->id, 'id_luar_talian' => $idLuar, 'id_lawatan' => $lawatanId, 'jenis' => $jenis, 'status' => $status, 'butiran' => $butiran])->save();
    }

    /** @return array{0: float|null, 1: float|null, 2: Carbon|null} */
    private function exif(UploadedFile $file): array
    {
        if (! function_exists('exif_read_data')) {
            return [null, null, null];
        }
        $data = @exif_read_data($file->getRealPath());
        if (! is_array($data)) {
            return [null, null, null];
        }
        $time = isset($data['DateTimeOriginal']) ? (Carbon::createFromFormat('Y:m:d H:i:s', (string) $data['DateTimeOriginal']) ?: null) : null;
        if (! isset($data['GPSLatitude'], $data['GPSLongitude'])) {
            return [null, null, $time];
        }
        $conv = function ($parts, $ref): float {
            $f = fn ($v) => str_contains((string) $v, '/') ? ((float) explode('/', (string) $v)[0]) / max(1.0, (float) explode('/', (string) $v)[1]) : (float) $v;
            $deg = $f($parts[0] ?? 0) + $f($parts[1] ?? 0) / 60 + $f($parts[2] ?? 0) / 3600;

            return in_array($ref, ['S', 'W'], true) ? -$deg : $deg;
        };

        return [$conv($data['GPSLatitude'], $data['GPSLatitudeRef'] ?? 'N'), $conv($data['GPSLongitude'], $data['GPSLongitudeRef'] ?? 'E'), $time];
    }

    private function lakaranKecil(UploadedFile $file): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $raw = @file_get_contents($file->getRealPath());
        $img = $raw === false ? false : @imagecreatefromstring($raw);
        if ($img === false) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, 480 / max($w, $h, 1));
        $t = imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        if ($t === false) {
            return null;
        }
        ob_start();
        imagejpeg($t, null, 70);

        return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
    }
}
