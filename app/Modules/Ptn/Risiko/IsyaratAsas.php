<?php

namespace App\Modules\Ptn\Risiko;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ptn\Contracts\RiskSignal;

/**
 * Base of MOD-PTN's built-in risk signals. They read the satellite skeleton
 * tables created by MOD-PRO (cukai, kontrak_pajakan, kes_mahkamah_lot,
 * dokumen) read-only, until the owning module registers its own signal for
 * the category (Contracts\RiskSignals) — PLAN §8: "enjin skor risiko dibina
 * sekarang tetapi input CKI/PJK/KES diisi bila modul itu siap".
 */
abstract class IsyaratAsas implements RiskSignal
{
    public function __construct(protected Parameters $parameters) {}

    /** @param list<string> $lotIds */
    protected function marks(array $lotIds): string
    {
        return implode(',', array_fill(0, count($lotIds), '?'));
    }
}
