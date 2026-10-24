<?php

namespace App\Modules\Kes\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiDrillDown;
use App\Modules\Kes\Models\KesMahkamah;

/** KPI: active cases, by status stage (SRS-KES-22). */
class KesAktif extends KesKpi implements KpiDrillDown
{
    public function key(): string
    {
        return 'kes.kes_aktif';
    }

    public function label(): string
    {
        return (string) __('kes::kes.kpi.kes_aktif');
    }

    public function compute(User $user, array $filters = []): array
    {
        $rows = $this->kes($user, $filters)->aktif()->where('kes_mahkamah.status_arkib', false)
            ->groupBy('kes_mahkamah.status')->selectRaw('kes_mahkamah.status AS k, COUNT(*) AS n')->pluck('n', 'k');
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $akhir = now()->subMonthsNoOverflow($i)->endOfMonth();
            $trend[] = ['tempoh' => $akhir->format('Y-m'), 'nilai' => $this->kes($user, $filters)->whereBetween('kes_mahkamah.created_at', [$akhir->copy()->startOfMonth(), $akhir])->count()];
        }

        return [
            'nilai' => (int) $rows->sum(),
            'unit' => 'kes',
            'trend' => $trend,
            'pecahan' => $rows->map(fn ($n, $k) => ['label' => (string) $this->ref->namaStatus((string) $k), 'nilai' => (int) $n, 'kod' => 'status:'.$k])->values()->all(),
            'pautan' => '/admin/kes/kes?aktif=1',
        ];
    }

    public function records(User $user, array $filters, ?string $slice, int $page = 1, int $perPage = 25): array
    {
        $q = $this->kes($user, $filters)->aktif()->where('kes_mahkamah.status_arkib', false)
            ->when($slice !== null && str_starts_with($slice, 'status:'), fn ($q) => $q->where('kes_mahkamah.status', substr((string) $slice, 7)));
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('kes_mahkamah.created_at')->forPage($page, $perPage)->get();

        return [
            'lajur' => [['kod' => 'no_rujukan', 'label' => 'No. rujukan'], ['kod' => 'jenis', 'label' => 'Jenis kes'], ['kod' => 'status', 'label' => 'Status'], ['kod' => 'prosiding', 'label' => 'Prosiding berikutnya']],
            'baris' => $rows->map(fn (KesMahkamah $k) => ['pautan' => '/admin/kes/kes/'.$k->id, 'sel' => [
                'no_rujukan' => $k->no_rujukan, 'jenis' => $this->ref->namaJenis($k->jenis), 'status' => $this->ref->namaStatus($k->status),
                'prosiding' => $k->tarikh_prosiding_berikutnya?->format('d/m/Y'),
            ]])->values()->all(),
            'jumlah' => $total,
        ];
    }
}
