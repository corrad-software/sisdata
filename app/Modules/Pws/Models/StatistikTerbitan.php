<?php

namespace App\Modules\Pws\Models;

use Illuminate\Support\Carbon;

/**
 * A snapshot of aggregate public statistics. Only aggregates, never a
 * record-level row; visible on the portal only once approved in MOD-WFE.
 *
 * @property int $id
 * @property string $tajuk
 * @property string $tempoh
 * @property array<string, mixed> $data
 * @property string $status
 * @property int|null $dicipta_oleh
 * @property int|null $diluluskan_oleh
 * @property string|null $id_aliran
 * @property Carbon|null $tarikh_terbit
 * @property Carbon $created_at
 */
class StatistikTerbitan extends PwsModel
{
    protected $table = 'pws_statistik_terbitan';

    protected function casts(): array
    {
        return ['data' => 'array', 'tarikh_terbit' => 'datetime'];
    }
}
