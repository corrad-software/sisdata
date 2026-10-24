<?php

namespace App\Modules\Cki\Integration\Kpi;

use App\Models\User;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Support\Akses;
use App\Modules\Dsh\Contracts\KpiProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Shared scoping for MOD-CKI's executive-dashboard KPIs (MOD-DSH): the user's district scope + kod_daerah / tahun filters. */
abstract class CkiKpi implements KpiProvider
{
    public function __construct(protected Akses $akses) {}

    public function permission(): string
    {
        return 'cki.laporan.lihat';
    }

    /**
     * @param  array{kod_daerah?: string, tahun?: int, bahagian?: string}  $filters
     * @return Builder<Cukai>
     */
    protected function bills(User $user, array $filters, bool $year = true): Builder
    {
        return $this->akses->cukai($user)->whereNotNull('cukai.obligasi_id')
            ->when(! empty($filters['kod_daerah']), fn ($q) => $q->where('cukai.kod_daerah', $filters['kod_daerah']))
            ->when($year, fn ($q) => $q->where('cukai.tahun_taksiran', (int) ($filters['tahun'] ?? Carbon::today()->format('Y'))));
    }

    protected function tahun(array $filters): int
    {
        return (int) ($filters['tahun'] ?? Carbon::today()->format('Y'));
    }
}
