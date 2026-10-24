<?php

namespace App\Modules\Dsh\Services;

use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Dsh\Contracts\KpiProviders;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * MOD-DSH's KpiProviders implementation (singleton). Providers are registered
 * as class names (from other modules' callAfterResolving hooks and from
 * MOD-DSH's own portfolio providers) and built lazily through the container;
 * a provider that fails to build is reported and skipped.
 */
class KpiRegistry implements KpiProviders
{
    /** @var list<class-string<KpiProvider>> */
    private array $classes = [];

    /** @var array<string, KpiProvider>|null */
    private ?array $built = null;

    public function __construct(private Container $container) {}

    public function register(string $provider): void
    {
        if (! in_array($provider, $this->classes, true)) {
            $this->classes[] = $provider;
            $this->built = null;
        }
    }

    /** @return array<string, KpiProvider> keyed by KPI key, in registration order */
    public function all(): array
    {
        if ($this->built !== null) {
            return $this->built;
        }

        $built = [];
        foreach ($this->classes as $class) {
            try {
                $provider = $this->container->make($class);
                if ($provider instanceof KpiProvider) {
                    $built[$provider->key()] ??= $provider;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->built = $built;
    }

    public function get(string $key): ?KpiProvider
    {
        return $this->all()[$key] ?? null;
    }

    /** @return list<class-string<KpiProvider>> */
    public function classes(): array
    {
        return $this->classes;
    }
}
