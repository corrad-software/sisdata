<?php

namespace App\Modules\Tkt\Kpi;

use App\Models\User;
use App\Modules\Tkt\Models\Tiket;
use Illuminate\Database\Eloquent\Builder;

/** KPI: mean time to resolve (hours, time waiting for the reporter excluded). */
class Mttr extends TiketKpi
{
    public function key(): string
    {
        return 'tkt.mttr';
    }

    public function label(): string
    {
        return (string) __('tkt::tkt.kpi.mttr');
    }

    public function compute(User $user, array $filters = []): array
    {
        $d = $this->analitik->papanPemuka($this->tempoh($filters));

        return [
            'nilai' => $d['mttr_jam'],
            'unit' => 'jam',
            'pecahan' => array_map(fn ($r) => ['label' => $r['label'], 'nilai' => $r['mttr_jam'] ?? 0, 'kod' => 'keterukan:'.$r['keterukan']], $d['sla_mengikut_keterukan']),
            'pautan' => '/admin/tkt',
        ];
    }

    protected function rekod(User $user, array $filters, ?string $slice): Builder
    {
        $q = Tiket::query()->whereNotNull('tarikh_selesai');
        if ($slice !== null && str_starts_with($slice, 'keterukan:')) {
            $q->where('keterukan', (int) substr($slice, 10));
        }

        return $q;
    }
}
