<?php

namespace App\Modules\Pro\Contracts;

use App\Models\User;

/**
 * Optional on a ProfileSection: monetary events of a lot for the revenue/cost
 * chart of the Profil Hartanah 360° (SRS-PRO-28). MOD-PRO buckets them by
 * month and year and computes the net return; modules only report events.
 *
 * jenis: hasil_sewaan (income) | kos_cukai | kos_penyelenggaraan | kos_guaman
 * (costs). Other jenis values are shown but not counted in pulangan_bersih.
 * Return only what $user may see (rental and legal amounts are Sulit/Rahsia).
 */
interface FinancialSeries
{
    public const HASIL = ['hasil_sewaan'];

    public const KOS = ['kos_cukai', 'kos_penyelenggaraan', 'kos_guaman'];

    /**
     * @return list<array{tarikh: string, jenis: string, amaun: float, keterangan?: string, link?: string}>
     */
    public function financialSeries(string $lotId, User $user): array;
}
