<?php

namespace App\Modules\Ptn\Kpi;

use App\Models\User;

/** KPI: % of due scheduled visits done by their target date this year (SRS-PTN-23). */
class LawatanIkutJadual extends PtnKpi
{
    public function key(): string
    {
        return 'ptn.lawatan_ikut_jadual';
    }

    public function label(): string
    {
        return 'Lawatan pemantauan ikut jadual';
    }

    public function domain(): string
    {
        return 'operasi';
    }

    public function compute(User $user, array $filters = []): array
    {
        $p = $this->analitik->prestasi($user, $this->f($filters));

        return [
            'nilai' => $p['peratus_ikut_jadual'], 'unit' => '%', 'sasaran' => 90.0,
            'pecahan' => [
                ['label' => 'Ikut jadual', 'nilai' => $p['bil_ikut_jadual'], 'kod' => 'ikut_jadual'],
                ['label' => 'Perlu dilaksana setakat ini', 'nilai' => $p['bil_perlu_setakat_ini'], 'kod' => 'perlu'],
                ['label' => 'Tertunggak', 'nilai' => $p['bil_tertunggak'], 'kod' => 'tertunggak'],
            ],
            'pautan' => '/admin/ptn/laporan?laporan=prestasi',
        ];
    }
}
