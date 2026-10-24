<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: in-process acquisition cases past their channel's target duration (lower is better). */
class PemerolehanMelebihiTempoh extends PemerolehanKpi
{
    public function key(): string
    {
        return 'per.melebihi_tempoh';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.per_melebihi_tempoh');
    }

    public function domain(): string
    {
        return 'pematuhan';
    }

    protected function condition(array $filters): string
    {
        return $this->inProcess().' AND p.tarikh_mula_kes IS NOT NULL AND ('.$this->today()
            .' - p.tarikh_mula_kes) > coalesce(p.tempoh_sasaran_hari, (SELECT s.tempoh_sasaran_hari FROM saluran_pemerolehan s WHERE s.id = p.id_saluran), 365)';
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->caseWhere($user, $filters);
        $rows = $this->select("SELECT p.kod_daerah AS k, count(*) AS n FROM pemerolehan p WHERE {$where} AND {$this->condition($filters)} GROUP BY 1 ORDER BY 2 DESC", $b);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'kes',
            'pecahan' => $this->slices($rows, $this->names('ref_daerah'), 'daerah'),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
