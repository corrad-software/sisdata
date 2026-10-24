<?php

namespace App\Modules\Ptn\Contracts;

use App\Models\User;

/**
 * SRS-PTN-08: applies an APPROVED inspection proposal to the master lot
 * record. MOD-PRO exposes no write contract yet, so MOD-PTN binds a fallback
 * (Support\LotMasterFallback) that updates the whitelisted lot_tanah fields
 * and audits before/after. MOD-PRO should bind its own implementation (with
 * its versioning, SRS-DAT-04) — bindIf leaves that one in place.
 */
interface LotMasterUpdates
{
    /**
     * @param  array<string, string|null>  $changes  field => new value (kategori_guna_tanah, zoning, status_lot, syarat_nyata)
     */
    public function apply(string $lotId, array $changes, User $approvedBy, string $reason): void;
}
