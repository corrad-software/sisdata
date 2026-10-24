<?php

namespace App\Modules\Pjk\Integration\Kpi;

use App\Models\User;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** KPI: rent collected ÷ rent billed (issued charges, deposits excluded) in the year, with monthly trend. */
class KadarKutipan extends PjkKpi
{
    public function key(): string
    {
        return 'pjk.kadar_kutipan';
    }

    public function label(): string
    {
        return __('pjk::pjk.kpi.kadar_kutipan');
    }

    public function domain(): string
    {
        return 'hasil';
    }

    public function compute(User $user, array $filters = []): array
    {
        $year = (int) ($filters['tahun'] ?? Carbon::today()->year);
        $ids = $this->contracts($user, $filters)->select('kontrak_pajakan.id');
        $rows = DB::table('jadual_caj')->whereIn('kontrak_id', $ids)->where('dikeluarkan', true)->where('kod_caj', '!=', 'deposit')
            ->whereNotIn('status_caj', ['dibatalkan'])->whereYear('tarikh_caj', $year)
            ->groupByRaw("to_char(tarikh_caj, 'YYYY-MM')")
            ->selectRaw("to_char(tarikh_caj, 'YYYY-MM') as tempoh, sum(amaun - amaun_dikecualikan)::text as dicaj, sum(amaun_dibayar)::text as dibayar")
            ->orderBy('tempoh')->get();

        $billed = 0;
        $paid = 0;
        $trend = [];
        foreach ($rows as $r) {
            $b = Wang::sen((string) $r->dicaj);
            $p = Wang::sen((string) $r->dibayar);
            $billed += $b;
            $paid += $p;
            $trend[] = ['tempoh' => (string) $r->tempoh, 'nilai' => $b > 0 ? round($p * 100 / $b, 1) : 0];
        }

        return [
            'nilai' => $billed > 0 ? round($paid * 100 / $billed, 1) : null,
            'unit' => '%',
            'sasaran' => 95.0,
            'trend' => $trend,
            'pecahan' => [
                ['label' => __('pjk::pjk.kpi.dicaj'), 'nilai' => round($billed / 100, 2), 'kod' => 'dicaj'],
                ['label' => __('pjk::pjk.kpi.dikutip'), 'nilai' => round($paid / 100, 2), 'kod' => 'dikutip'],
            ],
            'pautan' => '/admin/pjk/laporan?jenis=kutipan',
        ];
    }
}
