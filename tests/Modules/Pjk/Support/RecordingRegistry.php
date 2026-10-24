<?php

namespace Tests\Modules\Pjk\Support;

use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Gis\Contracts\MapLayers;

/** Stands in for MOD-GIS / MOD-DSH registries (being built in parallel): records what PJK registers. */
class RecordingRegistry implements KpiProviders, MapLayers
{
    /** @var list<string> */
    public array $registered = [];

    public function register(string $class): void
    {
        $this->registered[] = $class;
    }
}
