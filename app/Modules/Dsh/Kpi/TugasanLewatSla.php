<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: open tasks already past their SLA deadline (lower is better). */
class TugasanLewatSla extends TugasanKpi
{
    public function key(): string
    {
        return 'wfe.tugasan_lewat_sla';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.tugasan_lewat_sla');
    }

    public function domain(): string
    {
        return 'pematuhan';
    }

    protected function condition(User $user, array $filters): string
    {
        return self::TERBUKA.' AND t.tarikh_akhir_sla < now()';
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->taskWhere($user, $filters);
        $rows = $this->select("SELECT i.modul AS k, count(*) AS n FROM wf_tugasan t JOIN wf_instans i ON i.id = t.id_instans WHERE {$where} AND {$this->condition($user, $filters)} GROUP BY 1 ORDER BY 2 DESC", $b);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'tugasan',
            'pecahan' => $this->modulSlices($rows),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
