<?php

namespace App\Modules\Gis\Services;

use App\Modules\Gis\Contracts\MapLayer;
use App\Modules\Gis\Contracts\MapLayers;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * MOD-GIS's MapLayers implementation (bound as a singleton). Layers are
 * registered as class names and built lazily through the container; a layer
 * that fails to build is reported and skipped instead of breaking the map.
 */
class LayerRegistry implements MapLayers
{
    /** @var list<class-string<MapLayer>> */
    private array $classes = [];

    /** @var array<string, MapLayer>|null */
    private ?array $built = null;

    public function __construct(private Container $container) {}

    public function register(string $layer): void
    {
        if (! in_array($layer, $this->classes, true)) {
            $this->classes[] = $layer;
            $this->built = null;
        }
    }

    /** @return array<string, MapLayer> keyed by layer key */
    public function all(): array
    {
        if ($this->built !== null) {
            return $this->built;
        }

        $built = [];
        foreach ($this->classes as $class) {
            try {
                $layer = $this->container->make($class);
                if ($layer instanceof MapLayer) {
                    $built[$layer->key()] = $layer;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->built = $built;
    }

    public function get(string $key): ?MapLayer
    {
        return $this->all()[$key] ?? null;
    }

    /** @return list<class-string<MapLayer>> */
    public function classes(): array
    {
        return $this->classes;
    }
}
