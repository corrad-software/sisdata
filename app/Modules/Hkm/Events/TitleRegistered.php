<?php

namespace App\Modules\Hkm\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TitleRegistered
{
    use Dispatchable;

    public function __construct(public string $titleId, public string $lotId, public ?string $sourceRef = null) {}
}
