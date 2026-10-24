<?php

namespace App\Modules\Adm\Contracts;

interface SearchProviders
{
    /** @param class-string<SearchProvider> $provider */
    public function register(string $provider): void;
}
