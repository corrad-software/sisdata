<?php

namespace App\Modules\Ptn\Kpi;

use App\Models\User;

/** KPI: lots coded red (risk), with the green/yellow/red distribution (SRS-PTN-24). */
class LotRisikoMerah extends PtnKpi
{
    public function key(): string
    {
        return 'ptn.lot_risiko_merah';
    }

    public function label(): string
    {
        return 'Lot berisiko tinggi (merah)';
    }

    public function permission(): string
    {
        return 'ptn.risiko.lihat';
    }

    public function compute(User $user, array $filters = []): array
    {
        $d = $this->analitik->dashboard($user, $this->f($filters));

        return [
            'nilai' => $d['taburan_warna']['merah'], 'unit' => 'lot',
            'trend' => array_map(fn ($t) => ['tempoh' => $t['tempoh'], 'nilai' => $t['merah']], $d['trend']),
            'pecahan' => array_map(fn ($k) => ['label' => ucfirst($k), 'nilai' => $d['taburan_warna'][$k], 'kod' => 'warna:'.$k], ['hijau', 'kuning', 'merah']),
            'pautan' => '/admin/ptn/lot?kod_warna=merah',
        ];
    }
}
