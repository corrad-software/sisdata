<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/**
 * KPI: active court cases touching lots in view (SRS-DSH-02). Court cases are
 * Rahsia (SRS §4.2) → explicit grant dsh.rahsia.lihat. Reads the kes_mahkamah
 * skeleton until MOD-KES (G4) registers its own KPI.
 */
class KesMahkamahAktif extends DshKpi
{
    public const SELESAI = ['selesai', 'ditutup', 'diputuskan', 'ditarik_balik'];

    public function key(): string
    {
        return 'kes.aktif';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.kes_aktif');
    }

    public function domain(): string
    {
        return 'risiko';
    }

    public function permission(): string
    {
        return 'dsh.rahsia.lihat';
    }

    public function compute(User $user, array $filters = []): array
    {
        [$where, $b] = $this->lotWhere($user, $filters);
        $done = "'".implode("','", self::SELESAI)."'";
        $rows = $this->select("SELECT k.jenis AS k, count(DISTINCT k.id) AS n FROM kes_mahkamah k
            WHERE k.deleted_at IS NULL AND coalesce(k.status, '') NOT IN ({$done})
              AND EXISTS (SELECT 1 FROM kes_mahkamah_lot kl JOIN lot_tanah l ON l.id = kl.lot_id WHERE kl.kes_mahkamah_id = k.id AND {$where})
            GROUP BY 1 ORDER BY 2 DESC", $b);

        return [
            'nilai' => array_sum(array_map(fn ($r) => (int) $r->n, $rows)),
            'unit' => 'kes',
            'pecahan' => array_map(fn ($r) => ['label' => $r->k ? ucfirst(str_replace('_', ' ', (string) $r->k)) : __('dsh::dsh.tiada_maklumat'), 'nilai' => (int) $r->n], $rows),
        ];
    }
}
