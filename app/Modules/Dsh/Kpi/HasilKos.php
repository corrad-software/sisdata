<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;

/**
 * KPI: revenue vs cost for the year — rent collected (MOD-PJK charges paid)
 * against land / assessment tax paid (MOD-CKI, only when the user also holds
 * cki.laporan.lihat), with the monthly net trend. Read-only over jadual_caj,
 * kontrak_pajakan and cukai.
 */
class HasilKos extends DshKpi
{
    public function key(): string
    {
        return 'dsh.hasil_kos';
    }

    public function label(): string
    {
        return __('dsh::dsh.kpi.hasil_kos');
    }

    public function domain(): string
    {
        return 'hasil';
    }

    public function permission(): string
    {
        return 'pjk.laporan.lihat';
    }

    public function compute(User $user, array $filters = []): array
    {
        $year = $this->year($filters);
        [$kw, $kb] = $this->scopedWhere($user, $filters, 'k', ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'bahagian' => 'bahagian']);
        $hasil = collect($this->select("SELECT to_char(c.tarikh_caj, 'MM') AS m, coalesce(sum(c.amaun_dibayar), 0) AS n
            FROM jadual_caj c JOIN kontrak_pajakan k ON k.id = c.kontrak_id
            WHERE {$kw} AND k.deleted_at IS NULL AND c.dikeluarkan = true AND c.kod_caj <> 'deposit' AND extract(year FROM c.tarikh_caj) = ? GROUP BY 1", [...$kb, $year]))
            ->mapWithKeys(fn ($r) => [(string) $r->m => (float) $r->n]);

        $withCost = $this->access->allows($user, 'cki.laporan.lihat');
        $kos = collect();
        if ($withCost) {
            [$cw, $cb] = $this->scopedWhere($user, $filters, 'x', ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim']);
            $kos = collect($this->select("SELECT to_char(x.tarikh_bayar, 'MM') AS m, coalesce(sum(x.amaun_dibayar), 0) AS n FROM cukai x
                WHERE {$cw} AND x.deleted_at IS NULL AND x.tarikh_bayar IS NOT NULL AND extract(year FROM x.tarikh_bayar) = ? GROUP BY 1", [...$cb, $year]))
                ->mapWithKeys(fn ($r) => [(string) $r->m => (float) $r->n]);
        }

        $trend = [];
        $totalHasil = 0.0;
        $totalKos = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $mm = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $h = (float) ($hasil[$mm] ?? 0);
            $c = (float) ($kos[$mm] ?? 0);
            $totalHasil += $h;
            $totalKos += $c;
            $trend[] = ['tempoh' => "{$year}-{$mm}", 'nilai' => round($h - $c, 2), 'hasil' => round($h, 2), 'kos' => round($c, 2)];
        }

        $pecahan = [['label' => __('dsh::dsh.hasil_sewaan'), 'nilai' => round($totalHasil, 2), 'kod' => 'hasil']];
        if ($withCost) {
            $pecahan[] = ['label' => __('dsh::dsh.kos_cukai'), 'nilai' => round($totalKos, 2), 'kod' => 'kos'];
        }

        return [
            'nilai' => round($totalHasil - $totalKos, 2),
            'unit' => 'RM',
            'trend' => $trend,
            'pecahan' => $pecahan,
            'pautan' => '/admin/pjk/laporan?jenis=kutipan',
        ];
    }
}
