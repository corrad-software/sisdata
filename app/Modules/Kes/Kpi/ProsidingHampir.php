<?php

namespace App\Modules\Kes\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiDrillDown;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Support\Kod;
use Illuminate\Database\Eloquent\Builder;

/** KPI: proceedings scheduled in the next 30 days (SRS-KES-22 "kes dengan tarikh prosiding hampir"). */
class ProsidingHampir extends KesKpi implements KpiDrillDown
{
    public function key(): string
    {
        return 'kes.prosiding_30_hari';
    }

    public function label(): string
    {
        return (string) __('kes::kes.kpi.prosiding_30_hari');
    }

    public function domain(): string
    {
        return 'operasi';
    }

    /** @return Builder<Prosiding> */
    private function prosiding(User $user, array $filters): Builder
    {
        return Prosiding::query()->where('status', 'dijadualkan')
            ->whereBetween('tarikh_prosiding', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
            ->whereIn('kes_mahkamah_id', $this->kes($user, $filters)->where('kes_mahkamah.status_arkib', false)->select('kes_mahkamah.id'));
    }

    public function compute(User $user, array $filters = []): array
    {
        $rows = $this->prosiding($user, $filters)->groupBy('jenis_prosiding')->selectRaw('jenis_prosiding AS k, COUNT(*) AS n')->pluck('n', 'k');
        $minggu = $this->prosiding($user, $filters)->where('tarikh_prosiding', '<=', now()->addDays(7)->endOfDay())->count();

        return [
            'nilai' => (int) $rows->sum(),
            'unit' => 'prosiding',
            'pecahan' => [
                ['label' => (string) __('kes::kes.kpi.dalam_7_hari'), 'nilai' => $minggu, 'kod' => 'minggu'],
                ...$rows->map(fn ($n, $k) => ['label' => Kod::JENIS_PROSIDING[(string) $k] ?? (string) $k, 'nilai' => (int) $n, 'kod' => 'jenis:'.$k])->values()->all(),
            ],
            'pautan' => '/admin/kes/kalendar',
        ];
    }

    public function records(User $user, array $filters, ?string $slice, int $page = 1, int $perPage = 25): array
    {
        $q = $this->prosiding($user, $filters)->with('kes')
            ->when($slice === 'minggu', fn ($q) => $q->where('tarikh_prosiding', '<=', now()->addDays(7)->endOfDay()))
            ->when($slice !== null && str_starts_with($slice, 'jenis:'), fn ($q) => $q->where('jenis_prosiding', substr((string) $slice, 6)));
        $total = (clone $q)->count();

        return [
            'lajur' => [['kod' => 'tarikh', 'label' => 'Tarikh'], ['kod' => 'jenis', 'label' => 'Jenis prosiding'], ['kod' => 'no_rujukan', 'label' => 'No. rujukan kes']],
            'baris' => $q->orderBy('tarikh_prosiding')->forPage($page, $perPage)->get()->map(fn (Prosiding $p) => ['pautan' => '/admin/kes/kes/'.$p->kes_mahkamah_id.'?tab=prosiding', 'sel' => [
                'tarikh' => $p->tarikh_prosiding->format('d/m/Y H:i'), 'jenis' => Kod::JENIS_PROSIDING[$p->jenis_prosiding] ?? $p->jenis_prosiding, 'no_rujukan' => $p->kes?->no_rujukan,
            ]])->values()->all(),
            'jumlah' => $total,
        ];
    }
}
