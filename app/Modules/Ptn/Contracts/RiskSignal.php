<?php

namespace App\Modules\Ptn\Contracts;

/**
 * One input category of the lot risk score (SRS-PTN-11/12). MOD-PTN ships a
 * built-in signal for each of the six categories (reading the skeleton
 * tables read-only); the owning module can replace it by registering its own
 * signal for the same category from its provider:
 *
 *     $this->callAfterResolving(RiskSignals::class, fn (RiskSignals $s) => $s->register(CukaiRiskSignal::class));
 *
 * Categories: pencerobohan | tunggakan_cukai | pertikaian | dokumen |
 * tamat_kontrak | potensi_hasil. Scores are 0–100 (100 = highest risk).
 * Called from the scheduled job without a user — return figures, never
 * Sulit/Rahsia values in `butiran`.
 */
interface RiskSignal
{
    public function category(): string;

    /**
     * @param  list<string>  $lotIds
     * @return array<string, array{skor: float, butiran?: string}> keyed by lot id; missing lot = 0
     */
    public function scores(array $lotIds): array;
}
