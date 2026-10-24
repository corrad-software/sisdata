<?php

namespace App\Modules\Ast\Integrasi\Kpi;

use App\Models\User;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use Illuminate\Support\Facades\DB;

/** KPI: completed maintenance cost in the year (RM), monthly trend, split by work type. */
class KosPenyelenggaraan extends AstKpi
{
    public function key(): string
    {
        return 'ast.kos_penyelenggaraan';
    }

    public function label(): string
    {
        return 'Kos penyelenggaraan aset (RM)';
    }

    public function domain(): string
    {
        return 'operasi';
    }

    public function compute(User $user, array $filters = []): array
    {
        $tahun = (int) ($filters['tahun'] ?? now()->year);
        $q = fn () => RekodPenyelenggaraan::query()->untuk($user)->where('status', 'selesai')->whereNotNull('kos')->whereYear('tarikh_siap', $tahun)
            ->when(! empty($filters['kod_daerah']), fn ($w) => $w->where('kod_daerah', $filters['kod_daerah']));
        $bulan = $q()->groupBy(DB::raw('EXTRACT(MONTH FROM tarikh_siap)'))->selectRaw('EXTRACT(MONTH FROM tarikh_siap)::int AS m, SUM(kos)::text AS v')->pluck('v', 'm')->all();
        $jenis = $q()->groupBy('jenis_kerja')->selectRaw('jenis_kerja AS k, SUM(kos)::text AS v')->pluck('v', 'k')->all();
        $total = '0';
        foreach ($bulan as $v) {
            $total = bcadd($total, (string) $v, 2);
        }
        $label = ['pencegahan' => 'Pencegahan', 'pembaikan' => 'Pembaikan', 'kecemasan' => 'Kecemasan'];

        return [
            'nilai' => (float) $total, 'unit' => 'RM',
            'trend' => array_map(fn ($m) => ['tempoh' => sprintf('%d-%02d', $tahun, $m), 'nilai' => (float) ($bulan[$m] ?? 0)], range(1, 12)),
            'pecahan' => array_map(fn ($k) => ['label' => $label[$k] ?? $k, 'nilai' => (float) $jenis[$k], 'kod' => 'jenis:'.$k], array_keys($jenis)),
            'pautan' => '/admin/ast/laporan?kod=kos_penyelenggaraan',
        ];
    }
}
