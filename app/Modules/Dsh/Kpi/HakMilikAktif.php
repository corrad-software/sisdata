<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: titles in force, by title type; land status breakdown is HakMilikStatus (SRS-DSH-02). */
class HakMilikAktif extends HakMilikKpi
{
    public function key(): string
    {
        return 'hkm.hak_milik';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.hak_milik');
    }

    public function domain(): string
    {
        return 'portfolio';
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->titleWhere($user, $filters);
        $where .= ' AND '.$this->inForce();
        $rows = $this->select("SELECT h.jenis_hak_milik AS k, count(*) AS n FROM hak_milik h WHERE {$where} GROUP BY 1 ORDER BY 2 DESC", $b);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'hak milik',
            'pecahan' => $this->slices($rows, $this->names('ref_jenis_hak_milik'), 'jenis'),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
            'data_terakhir' => $this->iso($this->scalar("SELECT max(h.updated_at) FROM hak_milik h WHERE {$where}", $b)),
        ];
    }
}
