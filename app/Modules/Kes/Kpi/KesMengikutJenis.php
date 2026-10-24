<?php

namespace App\Modules\Kes\Kpi;

use App\Models\User;

/** KPI: active cases by case type (SRS-KES-22 "bilangan kes mengikut … jenis"). */
class KesMengikutJenis extends KesKpi
{
    public function key(): string
    {
        return 'kes.kes_mengikut_jenis';
    }

    public function label(): string
    {
        return (string) __('kes::kes.kpi.mengikut_jenis');
    }

    public function compute(User $user, array $filters = []): array
    {
        $rows = $this->kes($user, $filters)->aktif()->where('kes_mahkamah.status_arkib', false)
            ->groupBy('kes_mahkamah.jenis')->selectRaw('kes_mahkamah.jenis AS k, COUNT(*) AS n')->orderByDesc('n')->pluck('n', 'k');

        return [
            'nilai' => $rows->count(),
            'unit' => 'jenis',
            'pecahan' => $rows->map(fn ($n, $k) => ['label' => (string) ($this->ref->namaJenis($k === '' ? null : (string) $k) ?? __('kes::kes.tiada_jenis')), 'nilai' => (int) $n, 'kod' => 'jenis:'.$k])->values()->all(),
            'pautan' => '/admin/kes/laporan?jenis=ringkasan&kumpul=jenis',
        ];
    }
}
