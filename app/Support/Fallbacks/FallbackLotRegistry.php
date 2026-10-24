<?php

namespace App\Support\Fallbacks;

use App\Models\User;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Support\Facades\DB;

/** Stand-in for LotRegistry until MOD-PRO binds the real one: plain table reads, no access scoping. */
class FallbackLotRegistry implements LotRegistry
{
    public function find(string $lotId, ?User $user = null): ?array
    {
        $row = DB::table('lot_tanah')->whereNull('deleted_at')->where('id', $lotId)
            ->first(['id', 'no_lot', 'kod_mukim', 'kod_daerah', 'keluasan', 'unit_keluasan', 'kategori_guna_tanah', 'bahagian_pemilik', 'status_lot', 'klasifikasi']);

        return $row ? (array) $row : null;
    }

    public function exists(string $lotId, ?User $user = null): bool
    {
        return $this->find($lotId, $user) !== null;
    }

    public function findByNumber(string $kodMukim, string $noLot, ?User $user = null): ?string
    {
        return DB::table('lot_tanah')->whereNull('deleted_at')
            ->where('kod_mukim', $kodMukim)->where('no_lot', $noLot)->value('id');
    }

    public function summaries(array $lotIds, ?User $user = null): array
    {
        return $lotIds === [] ? [] : DB::table('lot_tanah')->whereNull('deleted_at')->whereIn('id', $lotIds)
            ->get(['id', 'no_lot', 'kod_mukim', 'kod_daerah', 'keluasan', 'unit_keluasan', 'kategori_guna_tanah', 'bahagian_pemilik', 'status_lot', 'klasifikasi'])
            ->mapWithKeys(fn ($r) => [(string) $r->id => (array) $r])->all();
    }

    public function search(string $query, ?User $user = null, int $limit = 20, array $filters = []): array
    {
        return DB::table('lot_tanah')->whereNull('deleted_at')->where('no_lot', 'ilike', '%'.$query.'%')
            ->when(! empty($filters['kod_mukim']), fn ($q) => $q->where('kod_mukim', $filters['kod_mukim']))
            ->orderBy('no_lot')->limit($limit)
            ->get(['id', 'no_lot', 'kod_mukim', 'kod_daerah', 'keluasan', 'unit_keluasan', 'kategori_guna_tanah', 'bahagian_pemilik', 'status_lot', 'klasifikasi'])
            ->map(fn ($r) => (array) $r)->all();
    }

    public function profileLink(string $lotId): string
    {
        return '/admin/pro/lot/'.$lotId;
    }
}
