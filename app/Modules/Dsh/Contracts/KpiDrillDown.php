<?php

namespace App\Modules\Dsh\Contracts;

use App\Models\User;

/**
 * Optional on a KpiProvider: the records behind a KPI value (or behind one
 * of its `pecahan` slices), so the dashboard can drill down KPI → list →
 * record in at most three clicks (SRS-DSH-11). Rows must respect the same
 * access scope as compute(); each row carries the frontend path of its
 * record (e.g. the Profil Hartanah 360°).
 */
interface KpiDrillDown
{
    /**
     * @param  array<string, mixed>  $filters  same keys as KpiProvider::compute()
     * @param  string|null  $slice  a `pecahan[].kod` value, or null for the whole KPI
     * @return array{lajur: list<array{kod: string, label: string}>, baris: list<array{pautan: string|null, sel: array<string, scalar|null>}>, jumlah: int}
     */
    public function records(User $user, array $filters, ?string $slice, int $page = 1, int $perPage = 25): array;
}
