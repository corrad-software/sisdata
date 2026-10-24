<?php

namespace App\Modules\Cki\Integration\Kpi;

use App\Models\User;

/** KPI: % of the year's bills (already due) that were paid on or before the due date. */
class BayarTepatMasa extends CkiKpi
{
    public function key(): string
    {
        return 'cki.peratus_bayar_tepat_masa';
    }

    public function label(): string
    {
        return __('cki::cki.kpi.tepat_masa');
    }

    public function domain(): string
    {
        return 'pematuhan';
    }

    public function compute(User $user, array $filters = []): array
    {
        $r = $this->bills($user, $filters)->whereDate('cukai.tarikh_akhir', '<=', now()->format('Y-m-d'))
            ->selectRaw("count(*) as n, count(*) filter (where cukai.status_bayaran = 'dibayar' and cukai.tarikh_bayar <= cukai.tarikh_akhir) as tepat")->toBase()->first();
        $trend = $this->bills($user, $filters, false)->whereDate('cukai.tarikh_akhir', '<=', now()->format('Y-m-d'))->groupBy('cukai.tahun_taksiran')->orderBy('cukai.tahun_taksiran')
            ->selectRaw("cukai.tahun_taksiran as tahun, count(*) as n, count(*) filter (where cukai.status_bayaran = 'dibayar' and cukai.tarikh_bayar <= cukai.tarikh_akhir) as tepat")->toBase()->get()
            ->map(fn ($x) => ['tempoh' => (string) $x->tahun, 'nilai' => $x->n ? round(100 * $x->tepat / $x->n, 1) : 0])->values()->all();

        return ['nilai' => $r && $r->n ? round(100 * $r->tepat / $r->n, 1) : null, 'unit' => '%', 'sasaran' => 95.0, 'trend' => $trend, 'pautan' => '/admin/cki/laporan?jenis=trend_tahunan'];
    }
}
