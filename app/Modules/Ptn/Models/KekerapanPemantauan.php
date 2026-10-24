<?php

namespace App\Modules\Ptn\Models;

/**
 * SRS-PTN-02: visits per year by lot category and risk level.
 *
 * @property int $id
 * @property string $kategori_lot
 * @property string $tahap_risiko
 * @property int $kekerapan_setahun
 * @property bool $aktif
 */
class KekerapanPemantauan extends PtnModel
{
    protected $table = 'kekerapan_pemantauan';

    protected $casts = ['aktif' => 'boolean', 'kekerapan_setahun' => 'integer'];
}
