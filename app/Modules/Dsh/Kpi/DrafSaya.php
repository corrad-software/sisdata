<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI (Operasi): my saved transaction drafts (SRS-DSH-22), by module. */
class DrafSaya extends DshKpi
{
    public function key(): string
    {
        return 'dsh.draf_saya';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.draf_saya');
    }

    public function domain(): string
    {
        return 'operasi';
    }

    public function permission(): string
    {
        return 'dsh.dashboard.lihat';
    }

    public function compute(User $user, array $filters = []): array
    {
        $rows = $this->select('SELECT modul AS k, count(*) AS n FROM dsh_draf_transaksi WHERE id_pengguna = ? GROUP BY 1 ORDER BY 2 DESC', [$user->id]);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'draf',
            'pecahan' => array_map(fn ($r) => ['label' => strtoupper((string) $r->k), 'nilai' => (int) $r->n], $rows),
            'pautan' => '/admin/dsh/inbox?tab=draf',
        ];
    }
}
