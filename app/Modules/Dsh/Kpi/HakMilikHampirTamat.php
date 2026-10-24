<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: titles (leasehold / TOL / state leases) expiring within the configured window — risk. */
class HakMilikHampirTamat extends HakMilikKpi
{
    public function key(): string
    {
        return 'hkm.hampir_tamat';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.hampir_tamat');
    }

    public function domain(): string
    {
        return 'risiko';
    }

    private function days(): int
    {
        return max(1, (int) $this->parameters->get('dsh.hari_hampir_tamat', 365));
    }

    protected function drillCondition(): string
    {
        return $this->inForce().' AND h.tarikh_tamat_tempoh BETWEEN '.$this->today().' AND '.$this->today($this->days());
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->titleWhere($user, $filters);
        $where .= ' AND '.$this->drillCondition();
        $rows = $this->select("SELECT h.jenis_hak_milik AS k, count(*) AS n FROM hak_milik h WHERE {$where} GROUP BY 1 ORDER BY 2 DESC", $b);
        [$all, $ab] = $this->titleWhere($user, $filters);
        $expired = (int) $this->scalar("SELECT count(*) FROM hak_milik h WHERE {$all} AND {$this->inForce()} AND h.tarikh_tamat_tempoh < {$this->today()}", $ab);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'hak milik',
            'pecahan' => [
                ...$this->slices($rows, $this->names('ref_jenis_hak_milik'), 'jenis'),
                ['label' => __('dsh::dsh.telah_tamat'), 'nilai' => $expired],
            ],
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
