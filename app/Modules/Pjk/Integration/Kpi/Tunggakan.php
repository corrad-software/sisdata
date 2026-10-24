<?php

namespace App\Modules\Pjk\Integration\Kpi;

use App\Models\User;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** KPI: total rent arrears (RM) with SRS-PJK-12 ageing breakdown. */
class Tunggakan extends PjkKpi
{
    public function key(): string
    {
        return 'pjk.tunggakan';
    }

    public function label(): string
    {
        return __('pjk::pjk.kpi.tunggakan');
    }

    public function domain(): string
    {
        return 'risiko';
    }

    public function compute(User $user, array $filters = []): array
    {
        $ids = $this->contracts($user, $filters)->select('kontrak_pajakan.id');
        $today = Carbon::today()->format('Y-m-d');
        $rows = DB::table('jadual_caj')->whereIn('kontrak_id', $ids)->where('dikeluarkan', true)->whereIn('status_caj', ['belum_bayar', 'separa'])
            ->where('tarikh_akhir', '<', $today)
            ->selectRaw("CASE WHEN ?::date - tarikh_akhir <= 30 THEN '1_30' WHEN ?::date - tarikh_akhir <= 60 THEN '31_60' WHEN ?::date - tarikh_akhir <= 90 THEN '61_90' ELSE 'lebih_90' END as kumpulan, sum(amaun - amaun_dibayar - amaun_dikecualikan)::text as baki", [$today, $today, $today])
            ->groupBy('kumpulan')->pluck('baki', 'kumpulan');
        $total = 0;
        $pecahan = [];
        foreach (Kod::KUMPULAN_UMUR as $kod => $label) {
            $s = Wang::sen((string) ($rows[$kod] ?? '0'));
            $total += $s;
            $pecahan[] = ['label' => $label, 'nilai' => round($s / 100, 2), 'kod' => $kod];
        }

        return ['nilai' => round($total / 100, 2), 'unit' => 'RM', 'pecahan' => $pecahan, 'pautan' => '/admin/pjk/laporan?jenis=tunggakan'];
    }
}
