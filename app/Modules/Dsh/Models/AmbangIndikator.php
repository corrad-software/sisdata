<?php

namespace App\Modules\Dsh\Models;

/**
 * @property int $id
 * @property string $kod_indikator
 * @property string $arah
 * @property string $nilai_hijau
 * @property string $nilai_kuning
 * @property string|null $nilai_merah
 * @property int|null $dikemas_kini_oleh
 */
class AmbangIndikator extends DshModel
{
    protected $table = 'dsh_ambang_indikator';
}
