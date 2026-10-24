<?php

namespace App\Modules\Kes\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Services\Rujukan;
use App\Modules\Kes\Support\Akses;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base of MOD-KES's executive-dashboard KPIs (SRS-KES-22, MOD-DSH). Counts
 * only — no case numbers or parties — starting from the scoped case query
 * (daerah / mukim / klasifikasi / panel). Rahsia: needs the explicit
 * `kes.rahsia.lihat` grant. Filters: kod_daerah, tahun.
 */
abstract class KesKpi implements KpiProvider
{
    public function __construct(protected Akses $akses, protected Rujukan $ref) {}

    public function domain(): string
    {
        return 'risiko';
    }

    public function permission(): string
    {
        return 'kes.rahsia.lihat';
    }

    /**
     * @param  array{kod_daerah?: string, tahun?: int, bahagian?: string}  $filters
     * @return Builder<KesMahkamah>
     */
    protected function kes(User $user, array $filters): Builder
    {
        return KesMahkamah::query()->bolehDilihat($user)
            ->when($filters['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kes_mahkamah.kod_daerah', $v));
    }
}
