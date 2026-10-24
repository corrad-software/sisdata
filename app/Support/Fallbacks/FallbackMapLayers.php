<?php

namespace App\Support\Fallbacks;

use App\Modules\Gis\Contracts\MapLayers;

/** Stand-in for MapLayers until MOD-GIS binds the real one. */
class FallbackMapLayers implements MapLayers
{
    public function register(string $layer): void {}
}
