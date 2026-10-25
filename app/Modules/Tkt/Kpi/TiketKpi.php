<?php

namespace App\Modules\Tkt\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiDrillDown;
use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\Analitik;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base of MOD-TKT's dashboard KPIs (MOD-DSH): support figures are
 * system-wide (tickets are not district-scoped); the drill-down rows go
 * through the ticket visibility scope (SRS-TKT-20). Filter: tahun.
 */
abstract class TiketKpi implements KpiDrillDown, KpiProvider
{
    public function __construct(protected Analitik $analitik) {}

    public function domain(): string
    {
        return 'operasi';
    }

    public function permission(): string
    {
        return 'tkt.laporan.lihat';
    }

    /** @return array{dari?: string, hingga?: string} */
    protected function tempoh(array $filters): array
    {
        return ! empty($filters['tahun']) ? ['dari' => $filters['tahun'].'-01-01', 'hingga' => $filters['tahun'].'-12-31'] : [];
    }

    /** @return Builder<Tiket> */
    abstract protected function rekod(User $user, array $filters, ?string $slice): Builder;

    public function records(User $user, array $filters, ?string $slice, int $page = 1, int $perPage = 25): array
    {
        $q = $this->rekod($user, $filters, $slice)->bolehDilihat($user);
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('tarikh_buka')->forPage(max(1, $page), max(1, min(100, $perPage)))->get();

        return [
            'lajur' => [
                ['kod' => 'nombor', 'label' => 'No. tiket'], ['kod' => 'tajuk', 'label' => 'Tajuk'], ['kod' => 'keterukan', 'label' => 'Severity'],
                ['kod' => 'modul', 'label' => 'Modul'], ['kod' => 'status', 'label' => 'Status'],
            ],
            'baris' => $rows->map(fn (Tiket $t) => [
                'pautan' => '/admin/tkt/tiket/'.$t->id,
                'sel' => ['nombor' => $t->nombor_rujukan, 'tajuk' => $t->tajuk, 'keterukan' => Lalai::KETERUKAN_RINGKAS[$t->keterukan] ?? $t->keterukan, 'modul' => Lalai::MODUL[$t->modul ?? 'umum'] ?? $t->modul, 'status' => Lalai::STATUS[$t->status] ?? $t->status],
            ])->values()->all(),
            'jumlah' => $total,
        ];
    }
}
