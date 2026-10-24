<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: lots per owning division (Hartanah / Wakaf / Baitulmal) — the Pengurusan view. */
class LotMengikutBahagian extends LotKpi
{
    public function key(): string
    {
        return 'pro.lot_bahagian';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.lot_bahagian');
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->lotWhere($user, $filters);
        $rows = $this->select("SELECT l.bahagian_pemilik AS k, count(*) AS n FROM lot_tanah l WHERE {$where} GROUP BY 1 ORDER BY 2 DESC", $b);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'lot',
            'pecahan' => $this->slices($rows, self::BAHAGIAN, 'bahagian'),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
