<?php

namespace App\Modules\Lap\Models;

use Illuminate\Support\Carbon;

/**
 * Scheduled report distribution (SRS-LAP-06).
 *
 * @property int $id
 * @property string $nama
 * @property string|null $id_definisi
 * @property string|null $kod_laporan
 * @property string $kekerapan harian | mingguan | bulanan | suku_tahun
 * @property string $masa_jalan HH:MM
 * @property int|null $hari_jalan
 * @property string $format
 * @property array<string, mixed>|null $parameter
 * @property list<int> $senarai_penerima
 * @property int $id_pencipta
 * @property bool $status_aktif
 * @property Carbon|null $jalan_berikutnya
 * @property Carbon|null $jalan_terakhir
 * @property string|null $status_terakhir
 */
class JadualPenjanaan extends LapModel
{
    public const KEKERAPAN = ['harian', 'mingguan', 'bulanan', 'suku_tahun'];

    protected $table = 'lap_jadual_penjanaan';

    protected function casts(): array
    {
        return ['parameter' => 'array', 'senarai_penerima' => 'array', 'status_aktif' => 'boolean', 'jalan_berikutnya' => 'datetime', 'jalan_terakhir' => 'datetime'];
    }

    /** Next run strictly after $after (Asia/Kuala_Lumpur app time). */
    public function nextRun(?Carbon $after = null): Carbon
    {
        $after ??= Carbon::now();
        [$h, $m] = array_map('intval', explode(':', $this->masa_jalan.':00'));
        $day = $this->hari_jalan;
        $at = fn (Carbon $d) => $d->copy()->setTime($h, $m);

        switch ($this->kekerapan) {
            case 'harian':
                $next = $at($after);
                if ($next->lessThanOrEqualTo($after)) {
                    $next->addDay();
                }

                return $next;
            case 'mingguan':
                $dow = max(1, min(7, $day ?? 1)); // 1 = Isnin
                $next = $at($after);
                while ($next->dayOfWeekIso !== $dow || $next->lessThanOrEqualTo($after)) {
                    $next->addDay();
                }

                return $next;
            default:
                $dom = max(1, min(28, $day ?? 1));
                $step = $this->kekerapan === 'suku_tahun' ? 3 : 1;
                $next = $at($after->copy()->startOfMonth()->day($dom));
                if ($step === 3) {
                    // Quarter starts: Jan, Apr, Jul, Oct.
                    $next->month(intdiv($next->month - 1, 3) * 3 + 1);
                }
                while ($next->lessThanOrEqualTo($after)) {
                    $next->addMonthsNoOverflow($step);
                }

                return $next;
        }
    }
}
