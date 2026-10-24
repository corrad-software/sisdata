<?php

namespace App\Modules\Per\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A pemerolehan case closed successfully; the lot now belongs to MAIS. */
class AcquisitionCompleted
{
    use Dispatchable;

    public function __construct(public string $caseId, public string $channel, public ?string $lotId, public ?string $titleId) {}
}
