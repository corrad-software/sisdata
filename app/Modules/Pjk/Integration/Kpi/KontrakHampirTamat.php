<?php

namespace App\Modules\Pjk\Integration\Kpi;

use App\Models\User;
use Illuminate\Support\Carbon;

/** KPI: running contracts ending within 180 days (30 / 90 / 180 breakdown). */
class KontrakHampirTamat extends PjkKpi
{
    public function key(): string
    {
        return 'pjk.kontrak_hampir_tamat';
    }

    public function label(): string
    {
        return __('pjk::pjk.kpi.kontrak_hampir_tamat');
    }

    public function domain(): string
    {
        return 'operasi';
    }

    public function compute(User $user, array $filters = []): array
    {
        $today = Carbon::today();
        $count = fn (int $days) => $this->contracts($user, $filters)->whereIn('status', ['aktif', 'hampir_tamat'])
            ->whereDate('tarikh_tamat', '>=', $today)->whereDate('tarikh_tamat', '<=', $today->copy()->addDays($days))->count();
        $d30 = $count(30);
        $d90 = $count(90);
        $d180 = $count(180);

        return [
            'nilai' => $d180,
            'unit' => 'kontrak',
            'pecahan' => [
                ['label' => '≤ 30 hari', 'nilai' => $d30, 'kod' => '30'],
                ['label' => '31–90 hari', 'nilai' => $d90 - $d30, 'kod' => '90'],
                ['label' => '91–180 hari', 'nilai' => $d180 - $d90, 'kod' => '180'],
            ],
            'pautan' => '/admin/pjk/laporan?jenis=hampir_tamat',
        ];
    }
}
