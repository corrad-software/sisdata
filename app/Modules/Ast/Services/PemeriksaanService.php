<?php

namespace App\Modules\Ast\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetGambar;
use App\Modules\Ast\Models\AsetPemeriksaan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Dms\Contracts\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asset inspections and photos (SRS-AST-05, 12, 20, 21). The field app (PWA,
 * same offline design as MOD-PTN's) records inspections offline and syncs
 * them here; every write is idempotent on the device-generated
 * id_luar_talian, so a retried sync never duplicates a record. Photos go to
 * DMS (category gambar_tapak, owner aset_fizikal) with their geotag; the
 * asset's last-inspection date and condition follow the newest inspection.
 */
class PemeriksaanService
{
    public function __construct(private AsetService $aset, private PenyelenggaraanService $penyelenggaraan, private DocumentStore $documents, private Parameters $parameters) {}

    /**
     * @param  array<string, mixed>  $d  validated
     * @return array{pemeriksaan: AsetPemeriksaan, status: string}
     */
    public function rekod(AsetFizikal $a, array $d, User $user, string $kaedah = 'manual'): array
    {
        if (! empty($d['id_luar_talian']) && ($p = AsetPemeriksaan::query()->withoutGlobalScope('access')->where('id_luar_talian', $d['id_luar_talian'])->first())) {
            return ['pemeriksaan' => $p, 'status' => 'pendua'];
        }
        if ($a->status_arkib) {
            throw ValidationException::withMessages(['id_aset' => __('ast::ast.aset_diarkib')]);
        }

        return DB::transaction(function () use ($a, $d, $user, $kaedah) {
            $tarikh = Carbon::parse($d['tarikh_pemeriksaan'] ?? now());
            $lat = isset($d['latitud']) && is_numeric($d['latitud']) ? (float) $d['latitud'] : null;
            $lng = isset($d['longitud']) && is_numeric($d['longitud']) ? (float) $d['longitud'] : null;
            $p = new AsetPemeriksaan;
            $p->forceFill([
                'id_aset' => $a->id, 'tarikh_pemeriksaan' => $tarikh->toDateString(), 'id_pegawai' => $user->id, 'kaedah' => $kaedah,
                'status_kondisi_dilapor' => $d['status_kondisi_dilapor'], 'isu_dikenalpasti' => $d['isu_dikenalpasti'] ?? null,
                'tindakan_disyorkan' => $d['tindakan_disyorkan'] ?? null, 'senarai_semak' => array_values((array) ($d['senarai_semak'] ?? [])),
                'latitud' => $lat, 'longitud' => $lng, 'ketepatan_gps_m' => $d['ketepatan_gps_m'] ?? null, 'jarak_dari_aset_m' => $lat !== null && $lng !== null ? $this->jarak($a, $lat, $lng) : null,
                'masa_mula' => $d['masa_mula'] ?? null, 'masa_tamat' => $d['masa_tamat'] ?? now(), 'status_penyegerakan' => $kaedah === 'pwa' ? 'disegerak' : 'dalam_talian',
                'id_luar_talian' => $d['id_luar_talian'] ?? null, 'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
                'sintetik' => (bool) ($d['sintetik'] ?? false),
            ])->save();

            // SRS-AST-21 / flow step 6: last inspection date + condition follow the NEWEST inspection only.
            if ($a->tarikh_pemeriksaan_terakhir === null || $tarikh->greaterThanOrEqualTo($a->tarikh_pemeriksaan_terakhir)) {
                $a->forceFill(['tarikh_pemeriksaan_terakhir' => $tarikh->toDateString()])->save();
                $this->aset->ubahKondisi($a, (string) $d['status_kondisi_dilapor'], $user, 'Pemeriksaan '.Lalai::KAEDAH_PEMERIKSAAN[$kaedah].': '.mb_substr((string) ($d['isu_dikenalpasti'] ?? 'tiada isu'), 0, 200), $p->id, $tarikh);
            }
            if (! empty($d['cipta_kerja']) && $user->can('ast.penyelenggaraan.cipta')) {
                $this->penyelenggaraan->ciptaKerja($a, [
                    'jenis_kerja' => $d['status_kondisi_dilapor'] === 'kritikal' ? 'kecemasan' : 'pembaikan',
                    'jenis_penyelenggaraan' => $d['jenis_penyelenggaraan'] ?? 'bangunan',
                    'keterangan' => 'Daripada pemeriksaan '.$tarikh->format('d/m/Y').': '.($d['tindakan_disyorkan'] ?? $d['isu_dikenalpasti'] ?? ''),
                    'keutamaan' => $d['status_kondisi_dilapor'] === 'kritikal' ? 'kritikal' : 'sederhana', 'id_pemeriksaan' => $p->id,
                ], $user, 'pemeriksaan');
            }

            return ['pemeriksaan' => $p->refresh(), 'status' => 'diterima'];
        });
    }

    /**
     * SRS-AST-12: dated, geotagged photo → DMS; idempotent on id_luar_talian.
     *
     * @param  array<string, mixed>  $meta
     * @return array{gambar: AsetGambar, status: string}
     */
    public function tambahGambar(AsetFizikal $a, UploadedFile $file, array $meta, User $user, ?AsetPemeriksaan $p = null, ?RekodPenyelenggaraan $k = null): array
    {
        if (! empty($meta['id_luar_talian']) && ($g = AsetGambar::query()->withoutGlobalScope('access')->where('id_luar_talian', $meta['id_luar_talian'])->first())) {
            return ['gambar' => $g, 'status' => 'pendua'];
        }
        $lat = isset($meta['latitud']) && is_numeric($meta['latitud']) ? (float) $meta['latitud'] : null;
        $lng = isset($meta['longitud']) && is_numeric($meta['longitud']) ? (float) $meta['longitud'] : null;
        $time = ! empty($meta['tarikh_gambar']) ? Carbon::parse((string) $meta['tarikh_gambar']) : null;
        if ($lat === null || $lng === null) {
            [$lat, $lng, $exifTime] = $this->exif($file);
            $time ??= $exifTime;
        }
        $kategori = $k !== null ? (in_array($meta['kategori_gambar'] ?? null, ['sebelum', 'selepas'], true) ? $meta['kategori_gambar'] : 'selepas') : ($p !== null ? 'pemeriksaan' : (string) ($meta['kategori_gambar'] ?? 'umum'));
        $docId = $this->documents->store($file, 'aset_fizikal', $a->id, 'gambar_tapak', $user, $a->klasifikasi, [
            'no_rujukan_aset' => $a->no_rujukan_aset, 'lat' => $lat, 'lng' => $lng, 'tarikh_masa' => ($time ?? now())->toIso8601String(), 'kategori_gambar' => $kategori,
            'pautan_induk' => '/admin/ast/aset/'.$a->id,
        ]);
        $g = new AsetGambar;
        $g->forceFill([
            'id_aset' => $a->id, 'id_pemeriksaan' => $p?->id, 'id_rekod_penyelenggaraan' => $k?->id, 'laluan_fail' => $docId, 'tarikh_gambar' => $time ?? now(),
            'latitud' => $lat, 'longitud' => $lng, 'ketepatan_m' => isset($meta['ketepatan_m']) && is_numeric($meta['ketepatan_m']) ? (float) $meta['ketepatan_m'] : null,
            'id_pegawai_rakam' => $user->id, 'kategori_gambar' => array_key_exists($kategori, Lalai::KATEGORI_GAMBAR) ? $kategori : 'umum',
            'keterangan' => isset($meta['keterangan']) ? mb_substr((string) $meta['keterangan'], 0, 255) : null, 'nama_fail' => mb_substr($file->getClientOriginalName(), 0, 255),
            'id_luar_talian' => $meta['id_luar_talian'] ?? null, 'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
        ])->save();
        if ($k !== null) {
            $k->forceFill([$g->kategori_gambar === 'sebelum' ? 'id_gambar_sebelum' : 'id_gambar_selepas' => $g->id])->save();
        }

        return ['gambar' => $g, 'status' => 'diterima'];
    }

    /** Metres between the reported position and the asset point (or its lot polygon when the asset has no point). */
    public function jarak(AsetFizikal $a, float $lat, float $lng): ?float
    {
        $r = DB::selectOne('SELECT COALESCE(
                (SELECT ST_Distance(a.koordinat::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) FROM aset_fizikal a WHERE a.id = ? AND a.koordinat IS NOT NULL),
                (SELECT ST_Distance(ds.geometri::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) FROM data_spatial ds WHERE ds.lot_id = ? AND ds.semasa LIMIT 1)
            ) AS m', [$lng, $lat, $a->id, $lng, $lat, $a->lot_id]);

        return $r && $r->m !== null ? round((float) $r->m, 2) : null;
    }

    public function diLokasi(?float $jarak): ?bool
    {
        return $jarak === null ? null : $jarak <= (float) $this->parameters->get('ast.toleransi_lokasi_m', 150);
    }

    /** @return array{0: float|null, 1: float|null, 2: Carbon|null} */
    private function exif(UploadedFile $file): array
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            return [null, null, null];
        }
        $e = @exif_read_data($file->getRealPath());
        if (! is_array($e)) {
            return [null, null, null];
        }
        $time = isset($e['DateTimeOriginal']) ? Carbon::createFromFormat('Y:m:d H:i:s', (string) $e['DateTimeOriginal']) ?: null : null;
        if (! isset($e['GPSLatitude'], $e['GPSLongitude'], $e['GPSLatitudeRef'], $e['GPSLongitudeRef'])) {
            return [null, null, $time];
        }
        $dms = function (array $parts): float {
            $f = array_map(function ($p) {
                [$n, $d] = array_pad(explode('/', (string) $p), 2, '1');

                return (float) $d === 0.0 ? 0.0 : (float) $n / (float) $d;
            }, $parts);

            return ($f[0] ?? 0) + ($f[1] ?? 0) / 60 + ($f[2] ?? 0) / 3600;
        };
        $lat = $dms((array) $e['GPSLatitude']) * ($e['GPSLatitudeRef'] === 'S' ? -1 : 1);
        $lng = $dms((array) $e['GPSLongitude']) * ($e['GPSLongitudeRef'] === 'W' ? -1 : 1);

        return [$lat, $lng, $time];
    }
}
