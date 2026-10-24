<?php

namespace App\Modules\Dsh\Contracts;

/**
 * Registry of executive-dashboard KPIs (MOD-DSH). Modules register their
 * KpiProvider classes lazily:
 *
 *     $this->callAfterResolving(KpiProviders::class, fn (KpiProviders $k) => $k->register(MyKpi::class));
 *
 * MOD-DSH binds a singleton and always resolves it through this contract, so
 * every callAfterResolving registration has run before the dashboard reads it.
 */
interface KpiProviders
{
    /** @param class-string<KpiProvider> $provider */
    public function register(string $provider): void;
}
