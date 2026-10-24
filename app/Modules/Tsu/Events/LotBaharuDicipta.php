<?php

namespace App\Modules\Tsu\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * SRS-TSU-19/21: an approved pecah sempadan / pecah bahagian / penyatuan has
 * created successor lots and archived the original ones. Records anchored on
 * the original lots move only through the two-level confirmed
 * LotRecordTransfer — this event is informational.
 */
class LotBaharuDicipta
{
    use Dispatchable;

    /**
     * @param  list<string>  $lotAsal
     * @param  list<string>  $lotBaharu
     */
    public function __construct(public string $urusanId, public string $jenisUrusan, public array $lotAsal, public array $lotBaharu) {}
}
