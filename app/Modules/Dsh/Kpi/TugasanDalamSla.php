<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: % of tasks completed in the year within their SLA, monthly trend (operations SLA). */
class TugasanDalamSla extends TugasanKpi
{
    public function key(): string
    {
        return 'wfe.dalam_sla';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.dalam_sla');
    }

    public function domain(): string
    {
        return 'pematuhan';
    }

    public function permission(): string
    {
        return 'wfe.laporan.lihat';
    }

    protected function condition(User $user, array $filters): string
    {
        return "t.status = 'selesai' AND extract(year FROM t.diselesaikan_pada) = ".$this->year($filters).' AND t.tarikh_akhir_sla IS NOT NULL AND t.diselesaikan_pada > t.tarikh_akhir_sla';
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->taskWhere($user, $filters);
        $year = $this->year($filters);
        $rows = $this->select("SELECT to_char(t.diselesaikan_pada, 'YYYY-MM') AS m, count(*) AS n,
                sum(CASE WHEN t.tarikh_akhir_sla IS NULL OR t.diselesaikan_pada <= t.tarikh_akhir_sla THEN 1 ELSE 0 END) AS ok
            FROM wf_tugasan t JOIN wf_instans i ON i.id = t.id_instans
            WHERE {$where} AND t.status = 'selesai' AND extract(year FROM t.diselesaikan_pada) = ? GROUP BY 1 ORDER BY 1", [...$b, $year]);
        $n = 0;
        $ok = 0;
        $trend = [];
        foreach ($rows as $r) {
            $n += (int) $r->n;
            $ok += (int) $r->ok;
            $trend[] = ['tempoh' => (string) $r->m, 'nilai' => (int) $r->n > 0 ? round((int) $r->ok * 100 / (int) $r->n, 1) : 0];
        }

        return [
            'nilai' => $n > 0 ? round($ok * 100 / $n, 1) : null,
            'unit' => '%',
            'sasaran' => (float) $this->parameters->get('dsh.sasaran_sla_peratus', 90),
            'trend' => $trend,
            'pecahan' => [
                ['label' => __('dsh::dsh.dalam_sla'), 'nilai' => $ok],
                ['label' => __('dsh::dsh.lewat_sla'), 'nilai' => $n - $ok, 'kod' => 'lewat:ya'],
            ],
            'pautan' => '/admin/dsh/senarai?kpi='.$this->key(),
        ];
    }
}
