<?php

namespace App\Modules\Per\Integration;

use App\Models\User;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\Saluran;
use App\Modules\Per\Services\Payments5a;
use Carbon\CarbonImmutable;

/**
 * SRS-PER-04: remind Bahagian Kewangan (the type's admin-configured
 * recipient rule — e.g. role pegawai_kewangan) and the responsible officer
 * before a 5A notice's payment deadline, while it is not fully paid.
 */
class Notis5aReminder implements ReminderSource
{
    public const TYPE = 'per.notis_5a_tarikh_akhir';

    public function __construct(private Payments5a $payments) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [30, 14, 7, 3, 1];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $date = $today->addDays($daysBefore)->toDateString();
        $channel = Saluran::query()->where('kod_saluran', 'pemberimilikan_5a')->value('id');

        $cases = Pemerolehan::query()->withoutGlobalScope('access')->with(['saluran', 'pegawai'])
            ->where('id_saluran', $channel)
            ->whereIn('status_pemerolehan', Pemerolehan::AKTIF)
            ->where('butiran_saluran->tarikh_akhir_bayaran', $date)
            ->get();

        foreach ($cases as $case) {
            $summary = $this->payments->summary($case);
            if ($summary['lengkap']) {
                continue;
            }
            /** @var list<User> $to */
            $to = $case->pegawai ? [$case->pegawai] : [];

            yield new Reminder('pemerolehan:'.$case->id, $to, [
                'no_kes' => $case->no_kes,
                'no_notis_5a' => $case->butiran_saluran['no_notis_5a'] ?? '-',
                'tarikh_akhir_bayaran' => $date,
                'baki_hari' => $daysBefore,
                'baki_tertunggak' => $summary['baki_tertunggak'],
            ], $case->pautan());
        }
    }
}
