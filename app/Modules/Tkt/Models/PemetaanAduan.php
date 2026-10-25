<?php

namespace App\Modules\Tkt\Models;

/**
 * SRS-TKT-15: configurable mapping between MOD-TKT values and the Sistem
 * Aduan berpusat MAIS (jenis: kategori | keterukan | status | medan).
 *
 * @property int $id
 * @property string $jenis
 * @property string $nilai_tempatan
 * @property string $nilai_berpusat
 * @property string $arah
 * @property bool $aktif
 */
class PemetaanAduan extends TktModel
{
    protected $table = 'tkt_pemetaan_aduan';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
