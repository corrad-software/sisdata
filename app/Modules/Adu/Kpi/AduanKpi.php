<?php

namespace App\Modules\Adu\Kpi;

use App\Models\User;
use App\Modules\Adu\Services\Analitik;
use App\Modules\Dsh\Contracts\KpiProvider;

/**
 * Base of MOD-ADU's dashboard KPIs (MOD-DSH). Figures come from Analitik,
 * which starts from the scoped Aduan query — so the viewer's district scope
 * applies. Filters: kod_daerah, tahun.
 */
abstract class AduanKpi implements KpiProvider
{
    public function __construct(protected Analitik $analitik) {}

    public function domain(): string
    {
        return 'operasi';
    }

    public function permission(): string
    {
        return 'adu.laporan.lihat';
    }

    /**
     * @param  array{kod_daerah?: string, tahun?: int, bahagian?: string}  $filters
     * @return array<string, mixed>
     */
    protected function penapis(array $filters): array
    {
        $f = [];
        if (! empty($filters['kod_daerah'])) {
            $f['kod_daerah'] = $filters['kod_daerah'];
        }
        if (! empty($filters['tahun'])) {
            $f['dari'] = $filters['tahun'].'-01-01';
            $f['hingga'] = $filters['tahun'].'-12-31';
        }

        return $f;
    }

    /** @return list<array{tempoh: string, nilai: int}> */
    protected function trendDiterima(User $user, array $f): array
    {
        return array_map(fn ($r) => ['tempoh' => $r['bulan'], 'nilai' => $r['diterima']], $this->analitik->untuk($user)->trend($f, 12));
    }

    abstract public function compute(User $user, array $filters = []): array;
}
