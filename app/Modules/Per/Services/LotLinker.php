<?php

namespace App\Modules\Per\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PER-20: a case points at a registered lot, or at an "expected lot"
 * (lot number + mukim not yet in lot_tanah). Expected lots are re-linked to
 * the real lot as soon as MOD-PRO registers it — on demand or by the
 * scheduled `per:pautkan-lot`.
 */
class LotLinker
{
    public function __construct(private LotRegistry $lots) {}

    /**
     * @return array{lot_id: string|null, no_lot_dijangka: string|null, kod_mukim: string, kod_daerah: string}
     *
     * @throws ValidationException
     */
    public function resolve(?string $lotId, ?string $kodMukim, ?string $noLot, ?User $user): array
    {
        if ($lotId !== null && $lotId !== '') {
            $lot = $this->lots->find($lotId, $user);
            if ($lot === null) {
                throw ValidationException::withMessages(['lot_id' => __('per::per.lot_tiada')]);
            }

            return ['lot_id' => $lot['id'], 'no_lot_dijangka' => null, 'kod_mukim' => $lot['kod_mukim'], 'kod_daerah' => $lot['kod_daerah']];
        }

        if ($kodMukim === null || $noLot === null || $kodMukim === '' || $noLot === '') {
            throw ValidationException::withMessages(['lot_id' => __('per::per.lot_wajib')]);
        }

        $found = $this->lots->findByNumber($kodMukim, $noLot, $user);
        if ($found !== null && ($lot = $this->lots->find($found, $user)) !== null) {
            return ['lot_id' => $lot['id'], 'no_lot_dijangka' => null, 'kod_mukim' => $lot['kod_mukim'], 'kod_daerah' => $lot['kod_daerah']];
        }

        return ['lot_id' => null, 'no_lot_dijangka' => $noLot, 'kod_mukim' => $kodMukim, 'kod_daerah' => substr($kodMukim, 0, 4)];
    }

    /** Link an expected-lot case if its lot is now registered. */
    public function link(Pemerolehan $case, ?User $user = null): bool
    {
        if ($case->lot_id !== null || $case->no_lot_dijangka === null || $case->kod_mukim === null) {
            return false;
        }

        $lotId = $this->lots->findByNumber($case->kod_mukim, $case->no_lot_dijangka, $user);
        $lot = $lotId !== null ? $this->lots->find($lotId, $user) : null;
        if ($lot === null) {
            return false;
        }

        AuditScope::withReason(__('per::per.sebab_pautan_lot'), fn () => $case->forceFill([
            'lot_id' => $lot['id'],
            'kod_daerah' => $lot['kod_daerah'],
        ])->save());

        return true;
    }

    /** All expected-lot cases (system run, no user scope). Returns how many were linked. */
    public function relinkAll(): int
    {
        $linked = 0;
        Pemerolehan::query()->withoutGlobalScope('access')
            ->whereNull('lot_id')->whereNotNull('no_lot_dijangka')
            ->where('status_pemerolehan', '!=', Pemerolehan::DIBATALKAN)
            ->orderBy('created_at')
            ->chunkById(200, function ($cases) use (&$linked) {
                foreach ($cases as $case) {
                    $linked += $this->link($case) ? 1 : 0;
                }
            });

        return $linked;
    }
}
