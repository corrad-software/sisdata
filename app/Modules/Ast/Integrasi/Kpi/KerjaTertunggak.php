<?php

namespace App\Modules\Ast\Integrasi\Kpi;

use App\Models\User;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Support\Lalai;

/** KPI: overdue maintenance — active schedules past due + open work orders past their planned date (target 0). */
class KerjaTertunggak extends AstKpi
{
    public function key(): string
    {
        return 'ast.kerja_tertunggak';
    }

    public function label(): string
    {
        return 'Penyelenggaraan aset tertunggak';
    }

    public function domain(): string
    {
        return 'operasi';
    }

    public function compute(User $user, array $filters = []): array
    {
        $today = now()->toDateString();
        $daerah = fn ($q) => $q->when(! empty($filters['kod_daerah']), fn ($w) => $w->where('kod_daerah', $filters['kod_daerah']));
        $jadual = $daerah(JadualPenyelenggaraan::query()->untuk($user)->where('status', 'aktif')->whereDate('tarikh_jatuh_tempoh', '<', $today)
            ->whereIn('id_aset', fn ($s) => $s->select('id')->from('aset_fizikal')->where('status_arkib', false)))->count();
        $kerja = $daerah(RekodPenyelenggaraan::query()->untuk($user)->whereIn('status', Lalai::KERJA_TERBUKA)->whereDate('tarikh_dijadual', '<', $today))->count();

        return [
            'nilai' => $jadual + $kerja, 'unit' => 'item', 'sasaran' => 0,
            'pecahan' => [['label' => 'Jadual melepasi tarikh', 'nilai' => $jadual, 'kod' => 'jadual'], ['label' => 'Kerja terbuka melepasi tarikh', 'nilai' => $kerja, 'kod' => 'kerja']],
            'pautan' => '/admin/ast/penyelenggaraan?status=tertunggak',
        ];
    }
}
