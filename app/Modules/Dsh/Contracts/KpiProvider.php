<?php

namespace App\Modules\Dsh\Contracts;

use App\Models\User;

/**
 * One KPI on the executive dashboard (MOD-DSH). Modules own the meaning and
 * the query of their KPIs; MOD-DSH lays them out, filters, trends and drills
 * down. Values must respect the user's access scope (a district officer sees
 * district figures, management sees the state) — SRS-RBC-08.
 */
interface KpiProvider
{
    /** Stable key, e.g. "pjk.kadar_kutipan". */
    public function key(): string;

    public function label(): string;

    /** Dashboard domain: portfolio | hasil | risiko | operasi | pematuhan. */
    public function domain(): string;

    /** Permission needed to see this KPI. */
    public function permission(): string;

    /**
     * @param  array{kod_daerah?: string, tahun?: int, bahagian?: string}  $filters
     * @return array{nilai: float|int|null, unit: string, sasaran?: float|null, trend?: list<array{tempoh: string, nilai: float|int}>, pecahan?: list<array{label: string, nilai: float|int, kod?: string}>, pautan?: string}
     */
    public function compute(User $user, array $filters = []): array;
}
