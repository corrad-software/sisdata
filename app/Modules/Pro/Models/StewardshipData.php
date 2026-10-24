<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-11: data owner / steward register entry per data component.
 *
 * @property int $id
 * @property string $komponen_data
 * @property string $entiti_berkaitan
 * @property string $jawatan_data_owner
 * @property string $bahagian_data_owner
 * @property int|null $id_data_owner
 * @property string $jawatan_data_steward
 * @property string $bahagian_data_steward
 * @property int|null $id_data_steward
 * @property string|null $bahagian_pemilik
 * @property string|null $kod_daerah
 * @property string $peranan_tanggungjawab
 * @property Carbon $tarikh_berkuat_kuasa
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StewardshipData extends ProModel
{
    protected $table = 'daftar_stewardship_data';

    protected function casts(): array
    {
        return [
            'tarikh_berkuat_kuasa' => 'date',
        ];
    }
}
