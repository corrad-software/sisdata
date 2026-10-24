<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/**
 * KPI: current portfolio value — latest valuation (market value) per lot in
 * view, by district (SRS-DSH-02). Valuations are Rahsia (SRS §4.2), so this
 * summary needs the explicit grant dsh.rahsia.lihat (never by wildcard).
 */
class NilaiPortfolio extends LotKpi
{
    public function key(): string
    {
        return 'pro.nilai_portfolio';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.nilai_portfolio');
    }

    public function permission(): string
    {
        return 'dsh.rahsia.lihat';
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->lotWhere($user, $filters);
        $latest = 'SELECT DISTINCT ON (p.lot_id) p.lot_id, p.nilai_pasaran, p.tarikh_penilaian, p.updated_at FROM penilaian p
            WHERE p.deleted_at IS NULL AND p.nilai_pasaran IS NOT NULL ORDER BY p.lot_id, p.tarikh_penilaian DESC, p.versi DESC';
        $rows = $this->select("SELECT l.kod_daerah AS k, sum(v.nilai_pasaran) AS n FROM lot_tanah l JOIN ({$latest}) v ON v.lot_id = l.id WHERE {$where} GROUP BY 1 ORDER BY 2 DESC", $b);
        $last = $this->scalar("SELECT max(v.updated_at) FROM lot_tanah l JOIN ({$latest}) v ON v.lot_id = l.id WHERE {$where}", $b);

        return [
            'nilai' => round(array_sum(array_map(fn ($r) => (float) $r->n, $rows)), 2),
            'unit' => 'RM',
            'pecahan' => $this->slices($rows, $this->names('ref_daerah'), 'daerah', true),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
            'data_terakhir' => $this->iso($last),
        ];
    }
}
