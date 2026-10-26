<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Pro\Models\DataSpatial;
use App\Modules\Pro\Models\LotTanah;

/** JSON shape of a lot for the UI / API, with names resolved from MOD-REF and Sulit/Rahsia fields masked. */
class LotPresenter
{
    public function __construct(private RujukanInduk $ref, private Klasifikasi $klasifikasi) {}

    /** @return array<string, mixed> */
    public function summary(LotTanah $lot, User $user): array
    {
        $spatial = $lot->relationLoaded('spatialSemasa') ? $lot->spatialSemasa : $lot->spatialSemasa()->first();

        $row = [
            'id' => $lot->id,
            'no_lot' => $lot->no_lot,
            'nama_hartanah' => $lot->nama_hartanah,
            'kod_negeri' => $lot->kod_negeri,
            'kod_daerah' => $lot->kod_daerah,
            'nama_daerah' => $this->ref->namaDaerah($lot->kod_daerah),
            'kod_mukim' => $lot->kod_mukim,
            'nama_mukim' => $this->ref->namaMukim($lot->kod_mukim),
            'keluasan' => $lot->keluasan !== null ? (float) $lot->keluasan : null,
            'unit_keluasan' => $lot->unit_keluasan,
            'kategori_guna_tanah' => $lot->kategori_guna_tanah,
            'nama_kategori_guna_tanah' => $this->ref->namaKategori($lot->kategori_guna_tanah),
            'zoning' => $lot->zoning,
            'bahagian_pemilik' => $lot->bahagian_pemilik,
            'status_lot' => $lot->status_lot,
            'klasifikasi' => $lot->klasifikasi,
            'versi_rekod' => $lot->versi_rekod,
            'id_data_owner' => $lot->id_data_owner,
            'id_data_steward' => $lot->id_data_steward,
            'sintetik' => $lot->sintetik,
            'tag' => $lot->tag ?? [],
            'spatial' => $this->spatialFlag($spatial),
            'dikemas_kini_pada' => $lot->updated_at?->toIso8601String(),
        ];

        return $this->klasifikasi->mask('lot_tanah', $row, $user);
    }

    /** @return array<string, mixed> */
    public function detail(LotTanah $lot, User $user): array
    {
        $lot->loadMissing(['spatialSemasa', 'steward:id,name', 'owner:id,name']);

        $row = [
            ...$this->summary($lot, $user),
            'syarat_nyata' => $lot->syarat_nyata,
            'catatan' => $lot->catatan,
            'id_data_owner' => $lot->id_data_owner,
            'nama_data_owner' => $lot->owner?->name,
            'nama_data_steward' => $lot->steward?->name,
            'salasilah' => [
                'sumber_sistem' => $lot->sumber_sistem,
                'fail_asal' => $lot->fail_asal,
                'kunci_asal' => $lot->kunci_asal,
                'id_migrasi_larian' => $lot->id_migrasi_larian,
                'id_sesi_import' => $lot->id_sesi_import,
            ],
            'id_lot_gabung' => $lot->id_lot_gabung,
            'diarkib_pada' => $lot->diarkib_pada?->toIso8601String(),
            'dicipta_pada' => $lot->created_at?->toIso8601String(),
        ];

        return $this->klasifikasi->mask('lot_tanah', $row, $user);
    }

    /** @return array{status: string, disahkan: bool, label: string, keluasan_dikira: float|null, peratus_beza_keluasan: float|null}|array{status: string, disahkan: bool, label: string} */
    public function spatialFlag(?DataSpatial $spatial): array
    {
        if ($spatial === null) {
            return ['status' => 'tiada_geometri', 'disahkan' => false, 'label' => (string) __('pro::pro.status_spatial.tiada_geometri')];
        }

        return [
            'status' => $spatial->status_pengesahan,
            'disahkan' => $spatial->disahkan(),
            'label' => (string) __('pro::pro.status_spatial.'.$spatial->status_pengesahan),
            'keluasan_dikira' => $spatial->keluasan_dikira !== null ? (float) $spatial->keluasan_dikira : null,
            'peratus_beza_keluasan' => $spatial->peratus_beza_keluasan !== null ? (float) $spatial->peratus_beza_keluasan : null,
        ];
    }
}
