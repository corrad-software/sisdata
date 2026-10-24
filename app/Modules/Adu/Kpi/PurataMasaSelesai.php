<?php

namespace App\Modules\Adu\Kpi;

use App\Models\User;

/** KPI: average days to resolve a complaint (by district). */
class PurataMasaSelesai extends AduanKpi
{
    public function key(): string
    {
        return 'adu.purata_masa_selesai';
    }

    public function label(): string
    {
        return (string) __('adu::adu.kpi.purata_masa_selesai');
    }

    public function compute(User $user, array $filters = []): array
    {
        $f = $this->penapis($filters);
        $sum = $this->analitik->untuk($user)->ringkasan($f);

        return [
            'nilai' => $sum['purata_hari_selesai'],
            'unit' => 'hari',
            'pecahan' => array_values(array_filter(array_map(
                fn ($r) => $r['purata_hari_selesai'] === null ? null : ['label' => (string) $r['label'], 'nilai' => $r['purata_hari_selesai'], 'kod' => 'daerah:'.$r['kod']],
                $this->analitik->untuk($user)->prestasiSla($f, 'daerah'),
            ))),
            'pautan' => '/admin/adu/laporan',
        ];
    }
}
