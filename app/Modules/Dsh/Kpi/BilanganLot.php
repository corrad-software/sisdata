<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/**
 * KPI: number of land lots / assets in the portfolio, by district, with a
 * district × land-use heatmap and lots registered per year (SRS-DSH-02).
 */
class BilanganLot extends LotKpi
{
    public function key(): string
    {
        return 'pro.bilangan_lot';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.bilangan_lot');
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->lotWhere($user, $filters);
        $daerah = $this->names('ref_daerah');
        $kategori = $this->names('ref_kategori_guna_tanah');

        $total = (int) $this->scalar("SELECT count(*) FROM lot_tanah l WHERE {$where}", $b);
        $byDistrict = $this->select("SELECT l.kod_daerah AS k, count(*) AS n FROM lot_tanah l WHERE {$where} GROUP BY 1 ORDER BY 2 DESC", $b);

        $year = $this->year($filters);
        $trend = [];
        $perYear = collect($this->select("SELECT extract(year FROM l.created_at)::int AS y, count(*) AS n FROM lot_tanah l WHERE {$where} AND l.created_at < make_date(?, 12, 31) + 1 GROUP BY 1", [...$b, $year]))
            ->mapWithKeys(fn ($r) => [(int) $r->y => (int) $r->n]);
        $running = (int) $this->scalar("SELECT count(*) FROM lot_tanah l WHERE {$where} AND l.created_at < make_date(?, 1, 1)", [...$b, $year - 4]);
        for ($y = $year - 4; $y <= $year; $y++) {
            $running += $perYear[$y] ?? 0;
            $trend[] = ['tempoh' => (string) $y, 'nilai' => $running];
        }

        // Heatmap: district × land-use category (top 6 categories).
        $cells = $this->select("SELECT l.kod_daerah AS d, coalesce(l.kategori_guna_tanah, 'tiada') AS k, count(*) AS n FROM lot_tanah l WHERE {$where} GROUP BY 1, 2", $b);
        $catTotals = [];
        foreach ($cells as $c) {
            $catTotals[$c->k] = ($catTotals[$c->k] ?? 0) + (int) $c->n;
        }
        arsort($catTotals);
        $cols = array_slice(array_keys($catTotals), 0, 6);
        $rows = [];
        foreach ($cells as $c) {
            $i = array_search($c->k, $cols, true);
            if ($i === false) {
                continue;
            }
            $rows[$c->d] ??= array_fill(0, count($cols), 0);
            $rows[$c->d][$i] = (int) $c->n;
        }
        ksort($rows);

        return [
            'nilai' => $total,
            'unit' => 'lot',
            'trend' => $trend,
            'pecahan' => $this->slices($byDistrict, $daerah, 'daerah'),
            'matriks' => [
                'tajuk' => __('dsh::dsh.matriks.daerah_kategori'),
                'lajur' => array_map(fn ($k) => $kategori[$k] ?? ($k === 'tiada' ? __('dsh::dsh.tiada_maklumat') : $k), $cols),
                'kod_lajur' => array_map(fn ($k) => 'kategori:'.$k, $cols),
                'baris' => array_map(fn ($d, $sel) => ['label' => $daerah[$d] ?? $d, 'kod' => 'daerah:'.$d, 'sel' => $sel], array_keys($rows), array_values($rows)),
            ],
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
            'data_terakhir' => $this->lastUpdate($where, $b),
        ];
    }
}
