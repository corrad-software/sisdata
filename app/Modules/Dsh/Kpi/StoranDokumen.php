<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI: document storage used (MB, all versions) by source module — MOD-DMS, read-only. */
class StoranDokumen extends DshKpi
{
    public function key(): string
    {
        return 'dms.storan';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.storan');
    }

    public function domain(): string
    {
        return 'operasi';
    }

    public function permission(): string
    {
        return 'dms.kuota.lihat';
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->scopedWhere($user, $filters, 'd', ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim']);
        $rows = $this->select("SELECT d.modul_sumber AS k, coalesce(sum(v.saiz_bait), 0) / 1048576.0 AS n, count(DISTINCT d.id) AS c
            FROM dokumen d JOIN dokumen_versi v ON v.dokumen_id = d.id WHERE {$where} AND d.deleted_at IS NULL GROUP BY 1 ORDER BY 2 DESC", $b);
        $docs = array_sum(array_map(fn ($r) => (int) $r->c, $rows));

        return [
            'nilai' => round(array_sum(array_map(fn ($r) => (float) $r->n, $rows)), 1),
            'unit' => 'MB',
            'pecahan' => array_map(fn ($r) => ['label' => strtoupper((string) ($r->k ?? '-')), 'nilai' => round((float) $r->n, 1)], $rows),
            'pautan' => '/admin/dms',
            'bilangan_dokumen' => $docs,
        ];
    }
}
