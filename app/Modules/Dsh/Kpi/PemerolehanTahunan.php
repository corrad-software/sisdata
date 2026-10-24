<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: acquisitions completed in the year, by channel, with a 5-year trend. */
class PemerolehanTahunan extends PemerolehanKpi
{
    public function key(): string
    {
        return 'per.pemerolehan_tahunan';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.pemerolehan_tahunan');
    }

    public function domain(): string
    {
        return 'portfolio';
    }

    protected function condition(array $filters): string
    {
        return "p.status_pemerolehan = 'selesai' AND extract(year FROM p.tarikh_pemerolehan) = ".$this->year($filters);
    }

    public function compute(User $user, array $filters = []): array
    {
        $year = $this->year($filters);
        [$where, $b] = $this->caseWhere($user, $filters);
        $rows = $this->select("SELECT p.id_saluran AS k, count(*) AS n FROM pemerolehan p WHERE {$where} AND {$this->condition($filters)} GROUP BY 1 ORDER BY 2 DESC", $b);
        $perYear = collect($this->select("SELECT extract(year FROM p.tarikh_pemerolehan)::int AS y, count(*) AS n FROM pemerolehan p WHERE {$where} AND p.status_pemerolehan = 'selesai' AND extract(year FROM p.tarikh_pemerolehan) BETWEEN ? AND ? GROUP BY 1", [...$b, $year - 4, $year]))
            ->mapWithKeys(fn ($r) => [(int) $r->y => (int) $r->n]);
        $trend = [];
        for ($y = $year - 4; $y <= $year; $y++) {
            $trend[] = ['tempoh' => (string) $y, 'nilai' => $perYear[$y] ?? 0];
        }

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'kes',
            'trend' => $trend,
            'pecahan' => $this->slices($rows, $this->channels(), 'saluran'),
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
