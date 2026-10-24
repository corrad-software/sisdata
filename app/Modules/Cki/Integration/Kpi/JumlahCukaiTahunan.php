<?php

namespace App\Modules\Cki\Integration\Kpi;

use App\Models\User;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Wang;

/** KPI: total tax billed for the year (RM), by tax type, with a yearly trend. */
class JumlahCukaiTahunan extends CkiKpi
{
    public function key(): string
    {
        return 'cki.jumlah_cukai_tahunan';
    }

    public function label(): string
    {
        return __('cki::cki.kpi.jumlah_tahunan');
    }

    public function domain(): string
    {
        return 'portfolio';
    }

    public function compute(User $user, array $filters = []): array
    {
        $rows = $this->bills($user, $filters)->groupBy('cukai.jenis')->selectRaw('cukai.jenis, sum(cukai.amaun_pokok + cukai.amaun_denda)::text as t')->toBase()->pluck('t', 'jenis');
        $total = Wang::add('0', ...$rows->map(fn ($v) => (string) $v)->values()->all());
        $trend = $this->bills($user, $filters, false)->groupBy('cukai.tahun_taksiran')->orderBy('cukai.tahun_taksiran')
            ->selectRaw('cukai.tahun_taksiran as tahun, sum(cukai.amaun_pokok + cukai.amaun_denda)::text as t')->toBase()->get()
            ->map(fn ($r) => ['tempoh' => (string) $r->tahun, 'nilai' => Wang::float((string) $r->t)])->values()->all();

        return [
            'nilai' => Wang::float($total), 'unit' => 'RM', 'trend' => $trend,
            'pecahan' => $rows->map(fn ($v, $k) => ['label' => Kod::JENIS_CUKAI[$k] ?? (string) $k, 'nilai' => Wang::float((string) $v), 'kod' => (string) $k])->values()->all(),
            'pautan' => '/admin/cki/ringkasan?tahun='.$this->tahun($filters),
        ];
    }
}
