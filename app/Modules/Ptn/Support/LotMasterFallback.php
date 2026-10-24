<?php

namespace App\Modules\Ptn\Support;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ptn\Contracts\LotMasterUpdates;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Fallback LotMasterUpdates until MOD-PRO binds its own: applies approved
 * whitelisted field changes to lot_tanah, bumps versi_rekod and writes an
 * explicit audit entry with before/after (the PRO model observer is not
 * involved because this is a query-builder update).
 */
class LotMasterFallback implements LotMasterUpdates
{
    public function __construct(private AuditLogger $audit) {}

    public function apply(string $lotId, array $changes, User $approvedBy, string $reason): void
    {
        $changes = array_intersect_key($changes, Lalai::MEDAN_CADANGAN);
        if ($changes === []) {
            throw new InvalidArgumentException('Tiada medan yang dibenarkan untuk dikemas kini.');
        }
        DB::transaction(function () use ($lotId, $changes, $reason) {
            $old = DB::table('lot_tanah')->where('id', $lotId)->lockForUpdate()->first();
            if ($old === null) {
                throw new InvalidArgumentException('Lot tidak dijumpai.');
            }
            DB::table('lot_tanah')->where('id', $lotId)->update([...$changes, 'versi_rekod' => DB::raw('versi_rekod + 1'), 'updated_at' => now()]);
            $diff = [];
            foreach ($changes as $field => $value) {
                $diff[$field] = [$old->{$field} ?? null, $value];
            }
            $this->audit->record('kemas_kini', 'pro', 'lot_tanah', $lotId, changes: $diff, meta: ['sumber' => 'ptn.cadangan_kemaskini_lot', 'sebab' => $reason]);
        });
    }
}
