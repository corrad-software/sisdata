<?php

namespace App\Modules\Tkt\Kpi;

use App\Models\User;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Database\Eloquent\Builder;

/** KPI: open support tickets, broken down by severity. */
class TiketTerbuka extends TiketKpi
{
    public function key(): string
    {
        return 'tkt.tiket_terbuka';
    }

    public function label(): string
    {
        return (string) __('tkt::tkt.kpi.tiket_terbuka');
    }

    public function domain(): string
    {
        return 'risiko';
    }

    public function compute(User $user, array $filters = []): array
    {
        $d = $this->analitik->papanPemuka($this->tempoh($filters));

        return [
            'nilai' => $d['terbuka'],
            'unit' => 'tiket',
            'trend' => array_map(fn ($r) => ['tempoh' => $r['tempoh'], 'nilai' => $r['dibuka']], $d['trend']),
            'pecahan' => array_map(fn ($r) => ['label' => $r['label'], 'nilai' => $r['nilai'], 'kod' => 'keterukan:'.$r['kod']], $d['terbuka_mengikut_keterukan']),
            'pautan' => '/admin/tkt/tiket?status=terbuka',
        ];
    }

    protected function rekod(User $user, array $filters, ?string $slice): Builder
    {
        $q = Tiket::query()->whereIn('status', Lalai::TERBUKA);
        if ($slice !== null && str_starts_with($slice, 'keterukan:')) {
            $q->where('keterukan', (int) substr($slice, 10));
        }

        return $q;
    }
}
