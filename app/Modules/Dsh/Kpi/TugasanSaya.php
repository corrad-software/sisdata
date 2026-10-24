<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;
use App\Modules\Dsh\Services\InboxTugasan;

/** KPI (Operasi): my open tasks — assigned to me, my role, position or group. */
class TugasanSaya extends TugasanKpi
{
    public function key(): string
    {
        return 'wfe.tugasan_saya';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.tugasan_saya');
    }

    public function permission(): string
    {
        return 'wfe.tugasan.lihat';
    }

    protected function condition(User $user, array $filters): string
    {
        $keys = app(InboxTugasan::class)->keys($user);

        return self::TERBUKA.' AND t.penerima_kunci IN ('.$this->skop->marks($keys).')';
    }

    protected function conditionBindings(User $user): array
    {
        return app(InboxTugasan::class)->keys($user);
    }

    protected function taskWhere(User $user, array $filters): array
    {
        // Personal: not narrowed by district scope (tasks were routed to me).
        $sql = 'TRUE';
        $b = [];
        if (! empty($filters['modul'])) {
            $sql .= ' AND i.modul = ?';
            $b[] = (string) $filters['modul'];
        }

        return [$sql, $b];
    }

    protected function link(object $row): string
    {
        return '/admin/wfe/tugasan/'.$row->id;
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->taskWhere($user, $filters);
        $b = [...$b, ...$this->conditionBindings($user)];
        $rows = $this->select("SELECT i.modul AS k, count(*) AS n, sum(CASE WHEN t.tarikh_akhir_sla < now() THEN 1 ELSE 0 END) AS lewat
            FROM wf_tugasan t JOIN wf_instans i ON i.id = t.id_instans WHERE {$where} AND {$this->condition($user, $filters)} GROUP BY 1 ORDER BY 2 DESC", $b);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'tugasan',
            'pecahan' => $this->modulSlices($rows),
            'pautan' => '/admin/dsh/inbox',
        ];
    }
}
