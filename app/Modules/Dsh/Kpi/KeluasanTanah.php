<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: total land area (hectares) in the portfolio, by land-use category (SRS-DSH-02). */
class KeluasanTanah extends LotKpi
{
    public function key(): string
    {
        return 'pro.keluasan';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.keluasan');
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->lotWhere($user, $filters);
        $hektar = self::HEKTAR;
        $total = (float) $this->scalar("SELECT coalesce(sum({$hektar}), 0) FROM lot_tanah l WHERE {$where}", $b);
        $rows = $this->select("SELECT l.kategori_guna_tanah AS k, coalesce(sum({$hektar}), 0) AS n FROM lot_tanah l WHERE {$where} GROUP BY 1 ORDER BY 2 DESC", $b);

        return [
            'nilai' => round($total, 2),
            'unit' => 'ha',
            'pecahan' => $this->slices($rows, $this->names('ref_kategori_guna_tanah'), 'kategori', true),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
            'data_terakhir' => $this->lastUpdate($where, $b),
        ];
    }
}
