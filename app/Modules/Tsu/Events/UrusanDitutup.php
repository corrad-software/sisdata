<?php

namespace App\Modules\Tsu\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** SRS-TSU-24: a Land Office urusan was closed and locked (immutable from now on). */
class UrusanDitutup
{
    use Dispatchable;

    /** @param list<string> $lotIds */
    public function __construct(public string $urusanId, public string $jenisUrusan, public array $lotIds) {}
}
