<?php

namespace App\Modules\Ntf\Contracts;

/**
 * Registry of scheduled reminder sources (SRS-NTF-07). Call from boot():
 *
 *     app(Reminders::class)->register(LeaseExpiringReminder::class);
 */
interface Reminders
{
    /** @param class-string<ReminderSource> $source */
    public function register(string $source): void;
}
