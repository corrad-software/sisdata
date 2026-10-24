<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/** KPI (Operasi): my unread in-app notifications (MOD-NTF), by priority. */
class NotifikasiBelumDibaca extends DshKpi
{
    public function key(): string
    {
        return 'ntf.belum_dibaca';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.notifikasi');
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
        $rows = $this->select('SELECT m.keutamaan AS k, count(*) AS n FROM ntf_status_pengguna s JOIN ntf_mesej m ON m.id = s.mesej_id
            WHERE s.pengguna_id = ? AND s.dibaca_pada IS NULL AND s.dipadam_pada IS NULL AND s.diarkib_pada IS NULL GROUP BY 1 ORDER BY 2 DESC', [$user->id]);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'notifikasi',
            'pecahan' => array_map(fn ($r) => ['label' => ucfirst((string) $r->k), 'nilai' => (int) $r->n], $rows),
            'pautan' => '/admin/notifikasi',
        ];
    }
}
