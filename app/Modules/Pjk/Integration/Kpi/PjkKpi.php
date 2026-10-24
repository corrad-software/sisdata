<?php

namespace App\Modules\Pjk\Integration\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Support\Akses;
use Illuminate\Database\Eloquent\Builder;

/** Shared scoping for MOD-PJK's executive-dashboard KPIs (MOD-DSH). */
abstract class PjkKpi implements KpiProvider
{
    public function __construct(protected Akses $akses) {}

    public function permission(): string
    {
        return 'pjk.laporan.lihat';
    }

    /**
     * @param  array{kod_daerah?: string, tahun?: int, bahagian?: string}  $filters
     * @return Builder<Kontrak>
     */
    protected function contracts(User $user, array $filters): Builder
    {
        return $this->akses->kontrak($user)
            ->when(! empty($filters['kod_daerah']), fn ($q) => $q->where('kontrak_pajakan.kod_daerah', $filters['kod_daerah']))
            ->when(! empty($filters['bahagian']), fn ($q) => $q->where('kontrak_pajakan.bahagian', $filters['bahagian']));
    }

    /**
     * @param  array{kod_daerah?: string, tahun?: int, bahagian?: string}  $filters
     * @return Builder<PremisAset>
     */
    protected function premises(User $user, array $filters): Builder
    {
        return $this->akses->premis($user)
            ->when(! empty($filters['kod_daerah']), fn ($q) => $q->where('premis_aset.kod_daerah', $filters['kod_daerah']))
            ->when(! empty($filters['bahagian']), fn ($q) => $q->where('premis_aset.bahagian', $filters['bahagian']));
    }
}
