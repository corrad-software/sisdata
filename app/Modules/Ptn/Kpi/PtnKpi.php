<?php

namespace App\Modules\Ptn\Kpi;

use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Ptn\Services\Analitik;

/** Base of MOD-PTN KPIs for MOD-DSH; figures come from scoped models (viewer's district scope applies). */
abstract class PtnKpi implements KpiProvider
{
    public function __construct(protected Analitik $analitik) {}

    public function domain(): string
    {
        return 'risiko';
    }

    public function permission(): string
    {
        return 'ptn.laporan.lihat';
    }

    /**
     * @param  array{kod_daerah?: string, tahun?: int, bahagian?: string}  $filters
     * @return array<string, mixed>
     */
    protected function f(array $filters): array
    {
        return array_filter(['kod_daerah' => $filters['kod_daerah'] ?? null, 'tahun' => $filters['tahun'] ?? null]);
    }
}
