<?php

namespace App\Modules\Adu\Kpi;

use App\Models\User;

/** KPI: open complaints, broken down by category and district. */
class AduanTerbuka extends AduanKpi
{
    public function key(): string
    {
        return 'adu.aduan_terbuka';
    }

    public function label(): string
    {
        return (string) __('adu::adu.kpi.aduan_terbuka');
    }

    public function domain(): string
    {
        return 'risiko';
    }

    public function compute(User $user, array $filters = []): array
    {
        $f = $this->penapis($filters);
        $sum = $this->analitik->untuk($user)->ringkasan($f);

        return [
            'nilai' => $sum['aktif'],
            'unit' => 'aduan',
            'trend' => $this->trendDiterima($user, $f),
            'pecahan' => [
                ...array_map(fn ($r) => ['label' => (string) $r['label'], 'nilai' => $r['aktif'], 'kod' => 'kategori:'.$r['kod']], $sum['mengikut_kategori']),
                ...array_map(fn ($r) => ['label' => (string) $r['label'], 'nilai' => $r['aktif'], 'kod' => 'daerah:'.$r['kod']], $sum['mengikut_daerah']),
            ],
            'pautan' => '/admin/adu/aduan?status=terbuka',
        ];
    }
}
