<?php

namespace App\Modules\Ast\Integrasi\Kpi;

use App\Models\User;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Support\Rujukan;
use App\Modules\Dsh\Contracts\KpiProvider;
use Illuminate\Database\Eloquent\Builder;

/** Base of MOD-AST KPIs for MOD-DSH; every figure is computed on the viewer's scope (`untuk($user)`). */
abstract class AstKpi implements KpiProvider
{
    public function __construct(protected Rujukan $ref) {}

    public function domain(): string
    {
        return 'portfolio';
    }

    public function permission(): string
    {
        return 'ast.laporan.lihat';
    }

    /**
     * @param  array{kod_daerah?: string, tahun?: int, bahagian?: string}  $filters
     * @return Builder<AsetFizikal>
     */
    protected function aset(User $user, array $filters): Builder
    {
        return AsetFizikal::query()->untuk($user)->where('aset_fizikal.status_arkib', false)
            ->when(! empty($filters['kod_daerah']), fn ($q) => $q->where('aset_fizikal.kod_daerah', $filters['kod_daerah']))
            ->when(! empty($filters['bahagian']), fn ($q) => $q->whereIn('aset_fizikal.lot_id', fn ($s) => $s->select('id')->from('lot_tanah')->where('bahagian_pemilik', $filters['bahagian'])));
    }
}
