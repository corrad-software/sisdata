<?php

namespace App\Modules\Ptn\Kpi;

use App\Models\User;

/** KPI: open monitoring findings, by severity (SRS-PTN-24). */
class PenemuanTerbuka extends PtnKpi
{
    public function key(): string
    {
        return 'ptn.penemuan_terbuka';
    }

    public function label(): string
    {
        return 'Penemuan pemantauan terbuka';
    }

    public function compute(User $user, array $filters = []): array
    {
        $d = $this->analitik->isu($user, $this->f($filters) + ['status_isu' => null]);
        $open = array_sum(array_column($d['mengikut_keterukan'], 'terbuka'));

        return [
            'nilai' => $open, 'unit' => 'penemuan',
            'pecahan' => array_map(fn ($r) => ['label' => (string) $r['label'], 'nilai' => $r['terbuka'], 'kod' => 'tahap:'.$r['kod']], $d['mengikut_keterukan']),
            'pautan' => '/admin/ptn/isu?terbuka=1',
        ];
    }
}
