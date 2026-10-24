<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiDrillDown;

/**
 * Operations / SLA KPIs over MOD-WFE's task tables (wf_tugasan + wf_instans,
 * read-only), scoped by the instance's district. Drill-down lists tasks →
 * the workflow instance page.
 */
abstract class TugasanKpi extends DshKpi implements KpiDrillDown
{
    public const TERBUKA = "t.status IN ('menunggu', 'ditangguh')";

    public function permission(): string
    {
        return 'wfe.pemantauan.lihat';
    }

    public function domain(): string
    {
        return 'operasi';
    }

    /** @return array{0: string, 1: list<mixed>} */
    protected function taskWhere(User $user, array $filters): array
    {
        [$sql, $b] = $this->scopedWhere($user, $filters, 'i', ['daerah' => 'kod_daerah']);
        if (! empty($filters['modul'])) {
            $sql .= ' AND i.modul = ?';
            $b[] = (string) $filters['modul'];
        }
        if (! empty($filters['pegawai'])) {
            $sql .= ' AND t.id_pelulus = ?';
            $b[] = (int) $filters['pegawai'];
        }

        return [$sql, $b];
    }

    abstract protected function condition(User $user, array $filters): string;

    /** @return list<mixed> bindings of condition() */
    protected function conditionBindings(User $user): array
    {
        return [];
    }

    public function records(User $user, array $filters, ?string $slice, int $page = 1, int $perPage = 25): array
    {
        [$where, $b] = $this->taskWhere($user, $filters);
        $where .= ' AND '.$this->condition($user, $filters);
        $b = [...$b, ...$this->conditionBindings($user)];
        [$dim, $value] = $this->slice($slice);
        if ($dim === 'modul' && $value !== null) {
            $where .= ' AND i.modul = ?';
            $b[] = $value;
        }
        [$rows, $total] = $this->page("FROM wf_tugasan t JOIN wf_instans i ON i.id = t.id_instans WHERE {$where}", $b,
            't.id, t.tajuk, t.status, t.tarikh_akhir_sla, t.created_at, i.id AS instans, i.modul, i.tajuk AS tajuk_instans, i.kod_daerah',
            't.tarikh_akhir_sla NULLS LAST, t.id', $page, $perPage);
        $daerah = $this->names('ref_daerah');

        return [
            'lajur' => [
                ['kod' => 'tajuk', 'label' => __('dsh::dsh.lajur.tajuk')],
                ['kod' => 'modul', 'label' => __('dsh::dsh.lajur.modul')],
                ['kod' => 'daerah', 'label' => __('dsh::dsh.lajur.daerah')],
                ['kod' => 'status', 'label' => __('dsh::dsh.lajur.status')],
                ['kod' => 'tarikh_akhir', 'label' => __('dsh::dsh.lajur.tarikh_akhir')],
            ],
            'baris' => array_map(fn ($r) => [
                'pautan' => $this->link($r),
                'sel' => [
                    'tajuk' => $r->tajuk_instans ?: $r->tajuk,
                    'modul' => strtoupper((string) $r->modul),
                    'daerah' => $daerah[(string) $r->kod_daerah] ?? $r->kod_daerah,
                    'status' => $r->status,
                    'tarikh_akhir' => $r->tarikh_akhir_sla,
                ],
            ], $rows),
            'jumlah' => $total,
        ];
    }

    protected function link(object $row): string
    {
        return '/admin/wfe/instans/'.$row->instans;
    }

    /** @param list<object> $rows */
    protected function modulSlices(array $rows): array
    {
        return array_map(fn ($r) => ['label' => strtoupper((string) $r->k), 'nilai' => (int) $r->n, 'kod' => 'modul:'.$r->k], $rows);
    }
}
