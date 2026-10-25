<?php

namespace App\Modules\Lap\Models;

use Illuminate\Support\Carbon;

/**
 * Executive insight summary (SRS-LAP-20).
 *
 * @property int $id
 * @property string $tempoh
 * @property array<string, list<string>|null>|null $skop
 * @property list<array<string, mixed>> $isu_utama
 * @property list<array<string, mixed>> $risiko
 * @property list<array<string, mixed>> $tindakan_segera
 * @property array<string, mixed>|null $statistik
 * @property string $sumber
 * @property int|null $id_pengguna
 * @property Carbon $tarikh_jana
 */
class InsightEksekutif extends LapModel
{
    protected $table = 'lap_insight_eksekutif';

    protected function casts(): array
    {
        return ['skop' => 'array', 'isu_utama' => 'array', 'risiko' => 'array', 'tindakan_segera' => 'array', 'statistik' => 'array', 'tarikh_jana' => 'datetime'];
    }
}
