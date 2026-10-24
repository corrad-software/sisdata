<?php

namespace App\Modules\Hkm\Support;

use App\Models\User;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lot data HKM needs. Single-lot lookups go through MOD-PRO's LotRegistry
 * contract (scoped). LotRegistry has no batch/search API yet, so list views
 * (lot number per title) and the no_lot / jenis tanah search filters read the
 * lot_tanah table here — the only place HKM touches it directly (read-only,
 * same table the FK points at). Replace with a LotRegistry::search() once
 * MOD-PRO offers one (see report).
 */
class LotLookup
{
    public function __construct(private LotRegistry $lots, private AccessControl $access) {}

    /** @return array{id: string, no_lot: string, kod_mukim: string, kod_daerah: string, keluasan: ?string, unit_keluasan: string, kategori_guna_tanah: ?string, bahagian_pemilik: ?string, status_lot: string, klasifikasi: string}|null */
    public function find(string $lotId, ?User $user = null): ?array
    {
        $lot = $this->lots->find($lotId, $user);
        if ($lot === null || $user === null) {
            return $lot;
        }

        // Defence in depth: LotRegistry scopes by user, but re-check daerah/mukim
        // here so a stand-in implementation can't widen HKM's access.
        foreach (['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim'] as $type => $field) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed !== null && ! in_array($lot[$field], $allowed, true)) {
                return null;
            }
        }

        return $lot;
    }

    public function findByNumber(string $kodMukim, string $noLot): ?string
    {
        return $this->lots->findByNumber($kodMukim, $noLot);
    }

    public function profileLink(string $lotId): string
    {
        return $this->lots->profileLink($lotId);
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{no_lot: string, bahagian_pemilik: ?string, kategori_guna_tanah: ?string}>
     */
    public function summaries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('lot_tanah')->whereIn('id', array_values(array_unique($ids)))
            ->get(['id', 'no_lot', 'bahagian_pemilik', 'kategori_guna_tanah'])
            ->mapWithKeys(fn ($r) => [$r->id => ['no_lot' => $r->no_lot, 'bahagian_pemilik' => $r->bahagian_pemilik, 'kategori_guna_tanah' => $r->kategori_guna_tanah]])
            ->all();
    }

    /** Sub-query of lot ids matching a lot number (normalised: case/space-insensitive). */
    public function idsByNumber(string $noLot): Builder
    {
        $needle = preg_replace('/\s+/', '', mb_strtoupper($noLot));

        return DB::table('lot_tanah')->select('id')->whereNull('deleted_at')
            ->whereRaw("replace(upper(no_lot), ' ', '') LIKE ?", ['%'.$needle.'%']);
    }

    /** Sub-query of lot ids by bahagian_pemilik (hartanah | wakaf | baitulmal) — "jenis tanah". */
    public function idsByBahagian(string $bahagian): Builder
    {
        return DB::table('lot_tanah')->select('id')->whereNull('deleted_at')->where('bahagian_pemilik', $bahagian);
    }
}
