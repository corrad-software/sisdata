<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: open approval / follow-up tasks (outstanding actions, SRS-DSH-02), by module. */
class TugasanTertunggak extends TugasanKpi
{
    public function key(): string
    {
        return 'wfe.tugasan_tertunggak';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.tugasan_tertunggak');
    }

    protected function condition(User $user, array $filters): string
    {
        return self::TERBUKA;
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->taskWhere($user, $filters);
        $rows = $this->select("SELECT i.modul AS k, count(*) AS n FROM wf_tugasan t JOIN wf_instans i ON i.id = t.id_instans WHERE {$where} AND ".self::TERBUKA.' GROUP BY 1 ORDER BY 2 DESC', $b);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'tugasan',
            'pecahan' => $this->modulSlices($rows),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
