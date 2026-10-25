<?php

namespace App\Modules\Tkt\Kpi;

use App\Models\User;
use App\Modules\Tkt\Models\Tiket;
use Illuminate\Database\Eloquent\Builder;

/** KPI: % of tickets resolved within the resolution SLA (per severity in the breakdown). */
class PematuhanSla extends TiketKpi
{
    public function key(): string
    {
        return 'tkt.pematuhan_sla';
    }

    public function label(): string
    {
        return (string) __('tkt::tkt.kpi.pematuhan_sla');
    }

    public function domain(): string
    {
        return 'pematuhan';
    }

    public function compute(User $user, array $filters = []): array
    {
        $d = $this->analitik->papanPemuka($this->tempoh($filters));

        return [
            'nilai' => $d['sla']['peratus_selesai'],
            'unit' => '%',
            'sasaran' => 95.0,
            'pecahan' => array_map(fn ($r) => ['label' => $r['label'], 'nilai' => $r['peratus_selesai'] ?? 0, 'kod' => 'keterukan:'.$r['keterukan']], $d['sla_mengikut_keterukan']),
            'pautan' => '/admin/tkt',
        ];
    }

    /** Drill-down: resolved tickets that missed the SLA. */
    protected function rekod(User $user, array $filters, ?string $slice): Builder
    {
        $q = Tiket::query()->whereNotNull('tarikh_selesai')->where(fn ($w) => $w->where('selesai_dilepasi', true)->orWhereColumn('tarikh_selesai', '>', 'tarikh_sasaran_selesai'));
        if ($slice !== null && str_starts_with($slice, 'keterukan:')) {
            $q->where('keterukan', (int) substr($slice, 10));
        }

        return $q;
    }
}
