<?php

namespace App\Support\Fallbacks;

use App\Modules\Adm\Contracts\SearchProviders;

/** Stand-in for SearchProviders until its owning module binds the real one. See App\Support\Fallbacks. */
class FallbackSearchProviders implements SearchProviders
{
    public function register(string $provider): void {}
}
