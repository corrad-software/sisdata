<?php

namespace App\Modules\Pjk\Integration\Kpi;

use App\Models\User;
use App\Modules\Pjk\Support\Kod;

/** KPI (SRS-PJK-26): occupied ÷ rentable premises (disewa + kosong), by category. */
class KadarPenghunian extends PjkKpi
{
    public function key(): string
    {
        return 'pjk.kadar_penghunian';
    }

    public function label(): string
    {
        return __('pjk::pjk.kpi.kadar_penghunian');
    }

    public function domain(): string
    {
        return 'portfolio';
    }

    public function compute(User $user, array $filters = []): array
    {
        $rows = $this->premises($user, $filters)->whereIn('status_premis', ['disewa', 'kosong'])
            ->selectRaw('kategori_hartanah, count(*) as jumlah, count(*) filter (where status_premis = \'disewa\') as disewa')
            ->groupBy('kategori_hartanah')->get();
        $all = 0;
        $occupied = 0;
        $pecahan = [];
        foreach ($rows as $r) {
            $all += (int) $r->getAttribute('jumlah');
            $occupied += (int) $r->getAttribute('disewa');
            $pecahan[] = ['label' => Kod::KATEGORI_HARTANAH[$r->kategori_hartanah] ?? $r->kategori_hartanah, 'nilai' => (int) $r->getAttribute('jumlah') > 0 ? round((int) $r->getAttribute('disewa') * 100 / (int) $r->getAttribute('jumlah'), 1) : 0, 'kod' => $r->kategori_hartanah];
        }

        return ['nilai' => $all > 0 ? round($occupied * 100 / $all, 1) : null, 'unit' => '%', 'pecahan' => $pecahan, 'pautan' => '/admin/pjk/laporan?jenis=penghunian'];
    }
}
