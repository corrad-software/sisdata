<?php

namespace Tests\Modules\Ntf\Support;

use App\Models\User;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/** A lease module's reminder, as G3's MOD-PJK would write it. */
class LeaseExpiryReminder implements ReminderSource
{
    /** @var array<int, array{id: int, user: User, lot: string}> days-until-expiry => lease */
    public static array $leases = [];

    public function type(): string
    {
        return 'pjk.pajakan_tamat';
    }

    public function defaultDays(): array
    {
        return [30, 7];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        foreach (self::$leases as $days => $lease) {
            if ($days === $daysBefore) {
                yield new Reminder('kontrak_pajakan:'.$lease['id'], $lease['user'], [
                    'no_lot' => $lease['lot'],
                    'tarikh_tamat' => $today->addDays($days)->format('d/m/Y'),
                ], '/admin/pjk/kontrak/'.$lease['id']);
            }
        }
    }
}
