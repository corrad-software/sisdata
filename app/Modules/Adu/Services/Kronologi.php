<?php

namespace App\Modules\Adu\Services;

use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\KronologiAduan;

/** SRS-ADU-18: the only writer of the immutable complaint timeline. */
class Kronologi
{
    /** @param array<string, mixed> $data */
    public function rekod(
        Aduan $aduan,
        string $jenis,
        ?string $keterangan = null,
        ?User $oleh = null,
        ?string $sebelum = null,
        ?string $selepas = null,
        array $data = [],
        bool $komunikasi = false,
        ?string $idDokumen = null,
    ): KronologiAduan {
        return KronologiAduan::query()->create([
            'aduan_id' => $aduan->id,
            'no_rujukan_aduan' => $aduan->no_rujukan_aduan,
            'tarikh_masa' => now(),
            'jenis_tindakan' => $jenis,
            'keterangan_tindakan' => $keterangan,
            'id_pegawai' => $oleh?->id,
            'status_sebelum' => $sebelum,
            'status_selepas' => $selepas,
            'id_dokumen_berkaitan' => $idDokumen,
            'komunikasi_pengadu' => $komunikasi,
            'data' => $data === [] ? null : $data,
        ]);
    }
}
