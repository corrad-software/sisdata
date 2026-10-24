<?php

namespace App\Modules\Kes\Reminders;

use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Support\Kod;
use App\Modules\Kes\Support\Penerima;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/**
 * SRS-KES-10 / SRS-NTF-07 "tarikh prosiding kes mahkamah": 14, 7 and 1 days
 * before every scheduled hearing, mention, document filing… to the case
 * officer and the Ketua Bahagian. Content carries only the internal case
 * reference and the date (never the court number or parties — Rahsia).
 */
class PeringatanProsiding implements ReminderSource
{
    public const TYPE = 'kes.peringatan_prosiding';

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
        $rows = Prosiding::query()->with(['kes.pegawai'])->where('status', 'dijadualkan')
            ->whereIn('jenis_prosiding', Kod::PROSIDING_DIPERINGAT)
            ->whereDate('tarikh_prosiding', $today->addDays($daysBefore)->toDateString())->get();
        foreach ($rows as $p) {
            $kes = $p->kes;
            if ($kes === null || $kes->status_arkib || $kes->terminal()) {
                continue;
            }
            yield new Reminder('kes_prosiding:'.$p->id, $this->penerima->pegawaiDanKetua($kes), [
                'no_rujukan' => $kes->no_rujukan,
                'jenis' => Kod::JENIS_PROSIDING[$p->jenis_prosiding] ?? $p->jenis_prosiding,
                'tarikh' => $p->tarikh_prosiding->format('d/m/Y H:i'),
                'baki_hari' => $daysBefore,
            ], '/admin/kes/kes/'.$kes->id.'?tab=prosiding');
        }
    }
}
