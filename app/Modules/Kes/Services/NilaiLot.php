<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS-KES-22/23 "nilai lot terlibat": latest market valuation of the lots in
 * active cases. Valuations are Rahsia data of MOD-PRO (`penilaian`, read
 * only here, no contract exists for it yet) — shown only to users who also
 * hold `pro.rahsia.lihat`; others get the lot count without the value.
 */
class NilaiLot
{
    /**
     * @param  list<string>  $lotIds
     * @return array{boleh_lihat: bool, nilai: string|null, bil_lot_dinilai: int}
     */
    public function untuk(array $lotIds, User $user): array
    {
        if (! $user->can('pro.rahsia.lihat') || ! Schema::hasTable('penilaian')) {
            return ['boleh_lihat' => false, 'nilai' => null, 'bil_lot_dinilai' => 0];
        }
        if ($lotIds === []) {
            return ['boleh_lihat' => true, 'nilai' => '0.00', 'bil_lot_dinilai' => 0];
        }
        $latest = DB::table('penilaian')->whereIn('lot_id', $lotIds)->whereNull('deleted_at')->whereNotNull('nilai_pasaran')
            ->selectRaw('DISTINCT ON (lot_id) lot_id, nilai_pasaran')->orderBy('lot_id')->orderByDesc('tarikh_penilaian')->orderByDesc('versi');
        $row = DB::query()->fromSub($latest, 'p')->selectRaw('COALESCE(SUM(nilai_pasaran),0) AS n, COUNT(*) AS c')->first();

        return ['boleh_lihat' => true, 'nilai' => bcadd((string) ($row->n ?? '0'), '0', 2), 'bil_lot_dinilai' => (int) ($row->c ?? 0)];
    }
}
