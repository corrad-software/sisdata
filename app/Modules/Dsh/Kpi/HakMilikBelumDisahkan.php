<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: titles in force not yet verified against the Land Office record (lower is better). */
class HakMilikBelumDisahkan extends HakMilikKpi
{
    public function key(): string
    {
        return 'hkm.belum_disahkan';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.belum_disahkan');
    }

    public function domain(): string
    {
        return 'pematuhan';
    }

    protected function drillCondition(): string
    {
        return $this->inForce().' AND coalesce(h.disahkan, false) = false';
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->titleWhere($user, $filters);
        $rows = $this->select("SELECT h.kod_daerah AS k, count(*) AS n FROM hak_milik h WHERE {$where} AND {$this->drillCondition()} GROUP BY 1 ORDER BY 2 DESC", $b);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'hak milik',
            'pecahan' => $this->slices($rows, $this->names('ref_daerah'), 'daerah'),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
