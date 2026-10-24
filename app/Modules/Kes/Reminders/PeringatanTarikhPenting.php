<?php

namespace App\Modules\Kes\Reminders;

use App\Modules\Kes\Models\TarikhPenting;
use App\Modules\Kes\Support\Kod;
use App\Modules\Kes\Support\Penerima;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/** SRS-KES-10: document filing / appeal / compliance deadlines, 14, 7 and 1 days ahead. */
class PeringatanTarikhPenting implements ReminderSource
{
    public const TYPE = 'kes.peringatan_tarikh_penting';

    public function __construct(private Penerima $penerima) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [14, 7, 1];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $rows = TarikhPenting::query()->with(['kes.pegawai'])->where('status', 'belum')
            ->whereDate('tarikh', $today->addDays($daysBefore)->toDateString())->get();
        foreach ($rows as $t) {
            $kes = $t->kes;
            if ($kes === null || $kes->status_arkib) {
                continue;
            }
            yield new Reminder('kes_tarikh:'.$t->id, $this->penerima->pegawaiDanKetua($kes), [
                'no_rujukan' => $kes->no_rujukan,
                'jenis' => Kod::JENIS_TARIKH[$t->jenis] ?? $t->jenis,
                'tarikh' => $t->tarikh->format('d/m/Y'),
                'baki_hari' => $daysBefore,
            ], '/admin/kes/kes/'.$kes->id.'?tab=prosiding');
        }
    }
}
