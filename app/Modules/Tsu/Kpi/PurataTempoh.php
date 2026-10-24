<?php

namespace App\Modules\Tsu\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Tsu\Services\LaporanService;
use App\Modules\Tsu\Support\Lalai;

/** KPI: average completion time (days) of finished urusan, by type (SRS-TSU-27). */
class PurataTempoh implements KpiProvider
{
    public function __construct(private LaporanService $laporan) {}

    public function key(): string
    {
        return 'tsu.purata_tempoh';
    }

    public function label(): string
    {
        return 'Purata tempoh penyempurnaan urusan tanah';
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
        $base = fn () => $this->laporan->base($user, ['kod_daerah' => $filters['kod_daerah'] ?? null, 'tahun' => $filters['tahun'] ?? null])
            ->whereIn('status', ['selesai', 'ditutup'])->whereNotNull('tarikh_siap');
        $all = $base()->selectRaw('round(avg(tarikh_siap - tarikh_mula)::numeric, 1) AS hari')->value('hari');
        $by = $base()->selectRaw('jenis_urusan, round(avg(tarikh_siap - tarikh_mula)::numeric, 1) AS hari')->groupBy('jenis_urusan')->pluck('hari', 'jenis_urusan');

        return [
            'nilai' => $all !== null ? (float) $all : null, 'unit' => 'hari',
            'pecahan' => $by->map(fn ($h, $k) => ['label' => Lalai::JENIS_URUSAN[$k] ?? (string) $k, 'nilai' => (float) $h, 'kod' => 'jenis:'.$k])->values()->all(),
            'pautan' => '/admin/tsu/laporan',
        ];
    }
}
