<?php

namespace App\Modules\Kba\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $id_artikel
 * @property int $nombor_versi
 * @property string $tajuk
 * @property string $kandungan
 * @property int|null $id_pengubah
 * @property string|null $sebab_perubahan
 * @property Carbon $cap_masa
 */
class VersiArtikel extends KbaModel
{
    protected $table = 'kba_versi_artikel';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['cap_masa' => 'datetime'];
    }
}
