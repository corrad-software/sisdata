<?php

namespace App\Modules\Pro\Contracts;

use App\Models\User;

/**
 * Read access to the master lot record (MOD-PRO) for other modules. The
 * `lot_tanah` table may be referenced by FK from a module's migrations, but
 * its model is MOD-PRO's — go through this contract in code.
 *
 * Every method that takes a $user applies that user's access scope
 * (daerah/mukim/klasifikasi); a lot outside it behaves as if it didn't exist.
 */
interface LotRegistry
{
    /**
     * @return array{id: string, no_lot: string, kod_mukim: string, kod_daerah: string, keluasan: ?string, unit_keluasan: string, kategori_guna_tanah: ?string, bahagian_pemilik: ?string, status_lot: string, klasifikasi: string}|null
     */
    public function find(string $lotId, ?User $user = null): ?array;

    public function exists(string $lotId, ?User $user = null): bool;

    /** Lot id for a lot number in a mukim, if registered (SRS-PER-20 linking). */
    public function findByNumber(string $kodMukim, string $noLot, ?User $user = null): ?string;

    /**
     * Batch lookup (avoids N+1 in other modules' lists). Lots the user may not
     * see are simply absent from the result.
     *
     * @param  list<string>  $lotIds
     * @return array<string, array{id: string, no_lot: string, kod_mukim: string, kod_daerah: string, keluasan: ?string, unit_keluasan: string, kategori_guna_tanah: ?string, bahagian_pemilik: ?string, status_lot: string, klasifikasi: string}> keyed by lot id
     */
    public function summaries(array $lotIds, ?User $user = null): array;

    /**
     * Lot picker search by lot number (any spelling: "Lot No. 12", "PT12"…),
     * optionally limited by kod_daerah / kod_mukim / bahagian_pemilik.
     *
     * @param  array{kod_daerah?: string, kod_mukim?: string, bahagian_pemilik?: string}  $filters
     * @return list<array{id: string, no_lot: string, kod_mukim: string, kod_daerah: string, keluasan: ?string, unit_keluasan: string, kategori_guna_tanah: ?string, bahagian_pemilik: ?string, status_lot: string, klasifikasi: string}>
     */
    public function search(string $query, ?User $user = null, int $limit = 20, array $filters = []): array;

    /** Frontend path of the lot's 360° profile, for deep links. */
    public function profileLink(string $lotId): string;
}
