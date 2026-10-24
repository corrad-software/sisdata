<?php

namespace App\Support\Fallbacks;

use App\Modules\Dsh\Contracts\KpiProviders;

/** Stand-in for KpiProviders until MOD-DSH binds the real one. */
class FallbackKpiProviders implements KpiProviders
{
    public function register(string $provider): void {}
}
