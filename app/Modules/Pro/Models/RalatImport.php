<?php

namespace App\Modules\Pro\Models;

/**
 * SRS-PRO-17: one rejection reason for an import row.
 *
 * @property int $id
 * @property string $id_sesi
 * @property int $no_baris
 * @property string|null $medan
 * @property string|null $nilai_diberi
 * @property string $kod_ralat
 * @property string $sebab_penolakan
 */
class RalatImport extends ProModel
{
    protected $table = 'ralat_import';

    public $timestamps = false;
}
