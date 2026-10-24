<?php

namespace App\Modules\Adu\Kpi;

use App\Models\User;

/** KPI: % of closed complaints resolved within SLA (by category). */
class AduanDalamSla extends AduanKpi
{
    public function key(): string
    {
        return 'adu.dalam_sla';
    }

    public function label(): string
    {
        return (string) __('adu::adu.kpi.dalam_sla');
    }

    public function domain(): string
    {
        return 'pematuhan';
    }

    public function compute(User $user, array $filters = []): array
    {
        $f = $this->penapis($filters);
        $sum = $this->analitik->untuk($user)->ringkasan($f);

        return [
            'nilai' => $sum['peratus_sla_dipenuhi'],
            'unit' => '%',
            'sasaran' => 90.0,
            'pecahan' => array_values(array_filter(array_map(
                fn ($r) => $r['peratus_dipenuhi'] === null ? null : ['label' => (string) $r['label'], 'nilai' => $r['peratus_dipenuhi'], 'kod' => 'kategori:'.$r['kod']],
                $this->analitik->untuk($user)->prestasiSla($f, 'kategori'),
            ))),
            'pautan' => '/admin/adu/laporan?tab=sla',
        ];
    }
}
