<?php

namespace App\Modules\Cki\Integration\Kpi;

use App\Models\User;
use App\Modules\Cki\Support\Wang;

/** KPI: penalties (denda) incurred on the year's bills (RM), by district. */
class Penalti extends CkiKpi
{
    public function key(): string
    {
        return 'cki.penalti';
    }

    public function label(): string
    {
        return __('cki::cki.kpi.penalti');
    }

    public function domain(): string
    {
        return 'risiko';
    }

    public function compute(User $user, array $filters = []): array
    {
        $rows = $this->bills($user, $filters)->where('cukai.amaun_denda', '>', 0)->groupBy('cukai.kod_daerah')
            ->selectRaw('cukai.kod_daerah as k, sum(cukai.amaun_denda)::text as t')->toBase()->pluck('t', 'k');
        $total = Wang::add('0', ...$rows->map(fn ($v) => (string) $v)->values()->all());

        return [
            'nilai' => Wang::float($total), 'unit' => 'RM',
            'pecahan' => $rows->map(fn ($v, $k) => ['label' => (string) $k, 'nilai' => Wang::float((string) $v), 'kod' => (string) $k])->values()->all(),
            'pautan' => '/admin/cki/laporan?jenis=penalti',
        ];
    }
}
