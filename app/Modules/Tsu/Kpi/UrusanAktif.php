<?php

namespace App\Modules\Tsu\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Tsu\Services\LaporanService;
use App\Modules\Tsu\Support\Lalai;

/** KPI: active Land Office matters, by type (SRS-TSU-27). Scoped to the viewer. */
class UrusanAktif implements KpiProvider
{
    public function __construct(private LaporanService $laporan) {}

    public function key(): string
    {
        return 'tsu.urusan_aktif';
    }

    public function label(): string
    {
        return 'Urusan tanah aktif (Pejabat Tanah)';
    }

    public function domain(): string
    {
        return 'operasi';
    }

    public function permission(): string
    {
        return 'tsu.laporan.lihat';
    }

    public function compute(User $user, array $filters = []): array
    {
        $rows = $this->laporan->base($user, ['kod_daerah' => $filters['kod_daerah'] ?? null])->whereIn('status', Lalai::URUSAN_TERBUKA)
            ->selectRaw('jenis_urusan, count(*) AS n')->groupBy('jenis_urusan')->pluck('n', 'jenis_urusan');

        return [
            'nilai' => (int) $rows->sum(), 'unit' => 'urusan',
            'pecahan' => array_map(fn ($k) => ['label' => Lalai::JENIS_URUSAN[$k], 'nilai' => (int) ($rows[$k] ?? 0), 'kod' => 'jenis:'.$k], array_keys(Lalai::JENIS_URUSAN)),
            'pautan' => '/admin/tsu/urusan?terbuka=1',
        ];
    }
}
