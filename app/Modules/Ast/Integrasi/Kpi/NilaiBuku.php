<?php

namespace App\Modules\Ast\Integrasi\Kpi;

use App\Models\User;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Support\SusutNilai;

/**
 * KPI: portfolio book value (straight-line depreciation of acquisition cost,
 * bcmath) at the end of the filter year, by category; current valuation
 * total as `sasaran`-less comparison in the trend of the last 5 years.
 */
class NilaiBuku extends AstKpi
{
    public function key(): string
    {
        return 'ast.nilai_buku';
    }

    public function label(): string
    {
        return 'Nilai buku aset fizikal (RM)';
    }

    public function compute(User $user, array $filters = []): array
    {
        $tahun = (int) ($filters['tahun'] ?? now()->year);
        $kat = AsetKategori::query()->get()->keyBy('id');
        $perKat = [];
        $trend = [];
        for ($y = $tahun - 4; $y <= $tahun; $y++) {
            $trend[$y] = '0';
        }
        foreach ($this->aset($user, $filters)->whereNotNull('kos_perolehan')->cursor() as $a) {
            $k = $kat[$a->id_kategori] ?? null;
            if ($k === null) {
                continue;
            }
            $mula = (int) ($a->tahun_perolehan ?? $a->created_at->year);
            foreach (array_keys($trend) as $y) {
                if ($y >= $mula) {
                    $trend[$y] = bcadd($trend[$y], SusutNilai::kira((string) $a->kos_perolehan, $mula, $k->hayat_guna_tahun, (string) $k->kadar_nilai_sisa, $y)['nilai_buku'], 2);
                }
            }
            $perKat[$k->id] = bcadd($perKat[$k->id] ?? '0', SusutNilai::kira((string) $a->kos_perolehan, $mula, $k->hayat_guna_tahun, (string) $k->kadar_nilai_sisa, $tahun)['nilai_buku'], 2);
        }
        arsort($perKat, SORT_NUMERIC);

        return [
            'nilai' => (float) $trend[$tahun], 'unit' => 'RM',
            'trend' => array_map(fn ($y) => ['tempoh' => (string) $y, 'nilai' => (float) $trend[$y]], array_keys($trend)),
            'pecahan' => array_map(fn ($id) => ['label' => $kat[$id]->nama_kategori, 'nilai' => (float) $perKat[$id], 'kod' => 'kategori:'.$id], array_keys($perKat)),
            'pautan' => '/admin/ast/laporan?kod=nilai_portfolio',
        ];
    }
}
