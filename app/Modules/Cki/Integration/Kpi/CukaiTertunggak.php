<?php

namespace App\Modules\Cki\Integration\Kpi;

use App\Models\User;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Wang;
use Illuminate\Support\Carbon;

/** KPI: outstanding overdue tax (RM, all years) with the arrears-ageing breakdown. */
class CukaiTertunggak extends CkiKpi
{
    public function key(): string
    {
        return 'cki.tertunggak';
    }

    public function label(): string
    {
        return __('cki::cki.kpi.tertunggak');
    }

    public function domain(): string
    {
        return 'risiko';
    }

    public function compute(User $user, array $filters = []): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $rows = $this->bills($user, $filters, false)->where('cukai.status_bayaran', '<>', 'dibayar')->whereDate('cukai.tarikh_akhir', '<', $today)
            ->selectRaw("CASE WHEN ?::date - cukai.tarikh_akhir <= 30 THEN '1_30' WHEN ?::date - cukai.tarikh_akhir <= 90 THEN '31_90' WHEN ?::date - cukai.tarikh_akhir <= 365 THEN '91_365' ELSE 'lebih_365' END as k,
                sum(cukai.amaun_pokok + cukai.amaun_denda - cukai.amaun_dibayar)::text as baki", [$today, $today, $today])
            ->groupBy('k')->toBase()->pluck('baki', 'k');
        $total = '0.00';
        $pecahan = [];
        foreach (Kod::KUMPULAN_UMUR as $k => $label) {
            $v = Wang::norm((string) ($rows[$k] ?? '0'));
            $total = Wang::add($total, $v);
            $pecahan[] = ['label' => $label, 'nilai' => Wang::float($v), 'kod' => $k];
        }

        return ['nilai' => Wang::float($total), 'unit' => 'RM', 'pecahan' => $pecahan, 'pautan' => '/admin/cki/laporan?jenis=tertunggak'];
    }
}
