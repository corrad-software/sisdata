<?php

namespace App\Modules\Ast\Integrasi\Kpi;

use App\Models\User;
use App\Modules\Ast\Models\AsetKategori;

/** KPI: active assets by category (kategori:{id}); drill-down reuses BilanganAset's record list. */
class BilanganAsetKategori extends BilanganAset
{
    public function key(): string
    {
        return 'ast.aset_mengikut_kategori';
    }

    public function label(): string
    {
        return 'Bilangan aset fizikal mengikut kategori';
    }

    public function compute(User $user, array $filters = []): array
    {
        $kat = AsetKategori::query()->pluck('nama_kategori', 'id')->all();
        $rows = $this->aset($user, $filters)->reorder()->groupBy('id_kategori')->selectRaw('id_kategori AS k, COUNT(*) AS n')->orderByDesc('n')->get();

        return [
            'nilai' => (int) $rows->sum(fn ($r) => (int) $r->getAttribute('n')), 'unit' => 'aset',
            'pecahan' => $rows->map(fn ($r) => ['label' => $kat[(int) $r->getAttribute('k')] ?? (string) $r->getAttribute('k'), 'nilai' => (int) $r->getAttribute('n'), 'kod' => 'kategori:'.$r->getAttribute('k')])->values()->all(),
            'pautan' => '/admin/ast/laporan?kod=inventori',
        ];
    }
}
