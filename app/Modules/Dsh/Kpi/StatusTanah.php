<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: land status of titles in force (aktif, kosong, disewa, dipajak, tindakan undang-undang…) — SRS-DSH-02. */
class StatusTanah extends HakMilikKpi
{
    public function key(): string
    {
        return 'hkm.status_tanah';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.status_tanah');
    }

    public function domain(): string
    {
        return 'portfolio';
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->titleWhere($user, $filters);
        $where .= ' AND '.$this->inForce();
        $rows = $this->select("SELECT h.status_semasa AS k, count(*) AS n FROM hak_milik h WHERE {$where} GROUP BY 1 ORDER BY 2 DESC", $b);
        $total = array_sum(array_map(fn ($r) => (int) $r->n, $rows));
        $kosong = 0;
        foreach ($rows as $r) {
            $kosong += $r->k === 'kosong' ? (int) $r->n : 0;
        }

        return [
            // Headline: share of titles that are occupied / in use (not "kosong").
            'nilai' => $total > 0 ? round(($total - $kosong) * 100 / $total, 1) : null,
            'unit' => '%',
            'pecahan' => $this->slices($rows, $this->statusLabels(), 'status'),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
