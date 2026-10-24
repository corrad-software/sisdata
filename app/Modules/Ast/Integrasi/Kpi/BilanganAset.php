<?php

namespace App\Modules\Ast\Integrasi\Kpi;

use App\Models\User;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Dsh\Contracts\KpiDrillDown;

/** KPI: active assets, sliced by condition (kondisi:*) and drillable to the records; category split in `kategori`. */
class BilanganAset extends AstKpi implements KpiDrillDown
{
    public function key(): string
    {
        return 'ast.bilangan_aset';
    }

    public function label(): string
    {
        return 'Bilangan aset fizikal mengikut kondisi';
    }

    public function compute(User $user, array $filters = []): array
    {
        $byKondisi = $this->aset($user, $filters)->reorder()->groupBy('status_kondisi')->selectRaw('status_kondisi AS k, COUNT(*) AS n')->pluck('n', 'k')->all();
        $total = array_sum(array_map('intval', $byKondisi));

        return [
            'nilai' => $total, 'unit' => 'aset',
            'pecahan' => array_map(fn ($k) => ['label' => Lalai::KONDISI[$k], 'nilai' => (int) ($byKondisi[$k] ?? 0), 'kod' => 'kondisi:'.$k], array_keys(Lalai::KONDISI)),
            'pautan' => '/admin/ast/aset',
        ];
    }

    public function records(User $user, array $filters, ?string $slice, int $page = 1, int $perPage = 25): array
    {
        $q = $this->aset($user, $filters);
        if ($slice !== null && str_starts_with($slice, 'kondisi:')) {
            $q->where('status_kondisi', substr($slice, 8));
        } elseif ($slice !== null && str_starts_with($slice, 'kategori:')) {
            $q->where('id_kategori', (int) substr($slice, 9));
        }
        $total = (clone $q)->count();
        $kat = AsetKategori::query()->pluck('nama_kategori', 'id')->all();
        $rows = $q->orderBy('nama_aset')->forPage(max(1, $page), max(1, min(100, $perPage)))->get();

        return [
            'lajur' => [['kod' => 'no_rujukan', 'label' => 'No. rujukan'], ['kod' => 'nama', 'label' => 'Nama aset'], ['kod' => 'kategori', 'label' => 'Kategori'], ['kod' => 'daerah', 'label' => 'Daerah'], ['kod' => 'kondisi', 'label' => 'Kondisi']],
            'baris' => $rows->map(fn (AsetFizikal $a) => ['pautan' => '/admin/ast/aset/'.$a->id, 'sel' => [
                'no_rujukan' => $a->no_rujukan_aset, 'nama' => $a->nama_aset, 'kategori' => $kat[$a->id_kategori] ?? null,
                'daerah' => $this->ref->namaDaerah($a->kod_daerah), 'kondisi' => Lalai::KONDISI[$a->status_kondisi] ?? $a->status_kondisi,
            ]])->values()->all(),
            'jumlah' => $total,
        ];
    }
}
