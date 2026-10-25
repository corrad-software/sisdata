<?php

namespace App\Modules\Tkt\Kpi;

use App\Models\User;
use App\Modules\Tkt\Models\Tiket;
use Illuminate\Database\Eloquent\Builder;

/** KPI: self-service deflection rate — help sessions solved by a KB suggestion (SRS-TKT-07). */
class KadarPenyelesaianKendiri extends TiketKpi
{
    public function key(): string
    {
        return 'tkt.kadar_penyelesaian_kendiri';
    }

    public function label(): string
    {
        return (string) __('tkt::tkt.kpi.penyelesaian_kendiri');
    }

    public function compute(User $user, array $filters = []): array
    {
        $d = $this->analitik->papanPemuka($this->tempoh($filters))['penyelesaian_kendiri'];

        return [
            'nilai' => $d['kadar_penyelesaian_kendiri'],
            'unit' => '%',
            'pecahan' => [
                ['label' => 'Diselesaikan sendiri', 'nilai' => $d['diselesaikan_sendiri'], 'kod' => 'sendiri'],
                ['label' => 'Tiket diteruskan', 'nilai' => $d['diteruskan'], 'kod' => 'diteruskan'],
            ],
            'pautan' => '/admin/tkt/laporan',
        ];
    }

    /** Drill-down: tickets submitted although suggestions were shown. */
    protected function rekod(User $user, array $filters, ?string $slice): Builder
    {
        return Tiket::query()->whereIn('id', fn ($q) => $q->select('id_tiket')->from('tkt_cadangan_kb')->whereNotNull('id_tiket'));
    }
}
