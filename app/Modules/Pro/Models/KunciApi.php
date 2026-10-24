<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-29: policy of one scoped API key (Sanctum token).
 *
 * @property int $id
 * @property int $id_token
 * @property int $id_pemilik
 * @property string $sistem_pemanggil
 * @property array<mixed> $skop
 * @property int $had_kadar_seminit
 * @property Carbon|null $tamat_pada
 * @property Carbon|null $dibatalkan_pada
 * @property int|null $dibatalkan_oleh
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KunciApi extends ProModel
{
    protected $table = 'kunci_api_pro';

    protected function casts(): array
    {
        return [
            'skop' => 'array',
            'tamat_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }
}
