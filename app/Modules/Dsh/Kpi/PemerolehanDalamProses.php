<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: acquisition cases still in process, by channel. */
class PemerolehanDalamProses extends PemerolehanKpi
{
    public function key(): string
    {
        return 'per.dalam_proses';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.per_dalam_proses');
    }

    public function domain(): string
    {
        return 'operasi';
    }

    protected function condition(array $filters): string
    {
        return $this->inProcess();
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->caseWhere($user, $filters);
        $rows = $this->select("SELECT p.id_saluran AS k, count(*) AS n FROM pemerolehan p WHERE {$where} AND {$this->condition($filters)} GROUP BY 1 ORDER BY 2 DESC", $b);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'kes',
            'pecahan' => $this->slices($rows, $this->channels(), 'saluran'),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
