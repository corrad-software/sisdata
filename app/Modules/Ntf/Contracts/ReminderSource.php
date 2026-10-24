<?php

namespace App\Modules\Ntf\Contracts;

use Carbon\CarbonImmutable;

/**
 * A module's scheduled reminder (SRS-NTF-06/07): tax due, lease expiring,
 * court date, monitoring visit, asset maintenance, task near SLA… Register
 * the class with Reminders::register() from the module's provider boot().
 *
 * MOD-NTF runs every source once a day (`ntf:peringatan`). For each day
 * offset configured on the type (ntf_jenis.hari_peringatan, editable by
 * admins; defaultDays() seeds it) it asks the source what is due exactly that
 * many days from today and sends each item, deduplicated per key + offset.
 */
interface ReminderSource
{
    /** Registered notification type code used for these reminders. */
    public function type(): string;

    /**
     * Default reminder offsets in days before the due date, e.g. [30, 14, 7, 1].
     *
     * @return list<int>
     */
    public function defaultDays(): array;

    /**
     * @return iterable<Reminder>
     */
    public function due(int $daysBefore, CarbonImmutable $today): iterable;
}
