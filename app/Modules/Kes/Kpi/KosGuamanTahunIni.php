<?php

namespace App\Modules\Kes\Kpi;

use App\Models\User;
use App\Modules\Kes\Models\KosGuaman;
use App\Modules\Kes\Services\Wang;
use App\Modules\Kes\Support\Kod;

/** KPI: legal costs recorded this year (or the filter year), by category, monthly trend (SRS-KES-22/23). */
class KosGuamanTahunIni extends KesKpi
{
    public function key(): string
    {
        return 'kes.kos_guaman_tahun_ini';
    }

    public function label(): string
    {
        return (string) __('kes::kes.kpi.kos_guaman');
    }

    public function domain(): string
    {
        return 'hasil';
    }

    public function compute(User $user, array $filters = []): array
    {
        $tahun = (int) ($filters['tahun'] ?? now()->year);
        $base = fn () => KosGuaman::query()->where('status_bayaran', '!=', 'ditolak')->whereYear('tarikh', $tahun)
            ->whereIn('kes_mahkamah_id', $this->kes($user, $filters)->select('kes_mahkamah.id'));
        $byCat = $base()->groupBy('kategori_kos')->selectRaw('kategori_kos AS k, SUM(amaun) AS n')->pluck('n', 'k');
        $byMonth = $base()->selectRaw("to_char(tarikh, 'YYYY-MM') AS m, SUM(amaun) AS n")->groupBy('m')->orderBy('m')->pluck('n', 'm');

        return [
            'nilai' => (float) Wang::jumlah($byCat->values()->map(fn ($v) => (string) $v)->all()),
            'unit' => 'RM',
            'trend' => $byMonth->map(fn ($n, $m) => ['tempoh' => (string) $m, 'nilai' => (float) $n])->values()->all(),
            'pecahan' => $byCat->map(fn ($n, $k) => ['label' => Kod::KATEGORI_KOS[(string) $k] ?? (string) $k, 'nilai' => (float) $n, 'kod' => 'kategori:'.$k])->values()->all(),
            'pautan' => '/admin/kes/laporan?jenis=kos',
        ];
    }
}
