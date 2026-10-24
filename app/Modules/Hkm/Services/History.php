<?php

namespace App\Modules\Hkm\Services;

use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\HakMilikVersi;
use App\Modules\Hkm\Models\KronologiPemilikan;
use App\Modules\Hkm\Models\SejarahStatusTanah;

/**
 * Writes the append-only history of a title: status history (SRS-HKM-09),
 * ownership chronology (SRS-HKM-10) and versions with before/after
 * (SRS-HKM-19, SRS-DAT-04). Only ever inserts.
 */
class History
{
    /**
     * @param  array{tarikh_berkuat_kuasa?: string|null, sebab: string, id_dokumen_rujukan?: string|null, no_rujukan_dokumen?: string|null, id_pegawai?: int|null, id_pelulus?: int|null, id_perubahan?: string|null}  $meta
     */
    public function status(HakMilik $title, ?string $before, string $after, array $meta): SejarahStatusTanah
    {
        return SejarahStatusTanah::query()->create([
            'lot_id' => $title->lot_id,
            'hak_milik_id' => $title->id,
            'status_sebelum' => $before,
            'status_selepas' => $after,
            'tarikh_berkuat_kuasa' => $meta['tarikh_berkuat_kuasa'] ?? today()->toDateString(),
            'sebab_perubahan' => $meta['sebab'],
            'id_dokumen_rujukan' => $meta['id_dokumen_rujukan'] ?? null,
            'no_rujukan_dokumen' => $meta['no_rujukan_dokumen'] ?? null,
            'id_pegawai' => $meta['id_pegawai'] ?? null,
            'id_pelulus' => $meta['id_pelulus'] ?? null,
            'id_perubahan' => $meta['id_perubahan'] ?? null,
            'tarikh_masa_rekod' => now(),
        ]);
    }

    /**
     * @param  array{tarikh?: string|null, sebab: string, sumber?: string, id_pemerolehan?: string|null, id_dokumen_rujukan?: string|null, id_pegawai?: int|null, id_perubahan?: string|null}  $meta
     */
    public function owner(HakMilik $title, ?string $before, string $after, array $meta): KronologiPemilikan
    {
        $next = (int) KronologiPemilikan::query()->where('hak_milik_id', $title->id)->max('susunan_kronologi') + 1;

        return KronologiPemilikan::query()->create([
            'hak_milik_id' => $title->id,
            'lot_id' => $title->lot_id,
            'pemilik_sebelum' => $before,
            'pemilik_selepas' => $after,
            'tarikh_pertukaran' => $meta['tarikh'] ?? null,
            'sebab_pertukaran' => $meta['sebab'],
            'sumber' => $meta['sumber'] ?? 'manual',
            'id_pemerolehan' => $meta['id_pemerolehan'] ?? null,
            'id_dokumen_rujukan' => $meta['id_dokumen_rujukan'] ?? null,
            'id_pegawai' => $meta['id_pegawai'] ?? null,
            'id_perubahan' => $meta['id_perubahan'] ?? null,
            'susunan_kronologi' => $next,
        ]);
    }

    /**
     * Record the title's current state as version $title->versi_rekod.
     *
     * @param  array<string, mixed>|null  $before  snapshot before the change (null for the first version)
     * @param  array<string, mixed>  $extra  extra after-state (e.g. heirs / encumbrance payload)
     * @param  array{justifikasi?: string|null, id_dokumen_rujukan?: string|null, no_rujukan_dokumen?: string|null, id_perubahan?: string|null, id_pengguna?: int|null, id_pelulus?: int|null}  $meta
     */
    public function version(HakMilik $title, string $kind, ?array $before, array $meta, array $extra = []): HakMilikVersi
    {
        $after = $title->snapshot() + $extra;
        $changed = [];
        foreach ($after as $field => $value) {
            if ($before === null || ! array_key_exists($field, $before) || (string) json_encode($before[$field]) !== (string) json_encode($value)) {
                $changed[] = $field;
            }
        }

        return HakMilikVersi::query()->create([
            'hak_milik_id' => $title->id,
            'versi' => $title->versi_rekod,
            'jenis_perubahan' => $kind,
            'data_sebelum' => $before,
            'data_selepas' => $after,
            'medan_berubah' => $changed,
            'justifikasi' => $meta['justifikasi'] ?? null,
            'id_dokumen_rujukan' => $meta['id_dokumen_rujukan'] ?? null,
            'no_rujukan_dokumen' => $meta['no_rujukan_dokumen'] ?? null,
            'id_perubahan' => $meta['id_perubahan'] ?? null,
            'id_pengguna' => $meta['id_pengguna'] ?? null,
            'id_pelulus' => $meta['id_pelulus'] ?? null,
        ]);
    }
}
