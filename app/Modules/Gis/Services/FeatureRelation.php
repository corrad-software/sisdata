<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use App\Modules\Gis\Contracts\MapLayer;
use App\Modules\Gis\Layers\SqlLayer;

/**
 * Any authorised layer as one SQL relation `(id, label, pautan, props jsonb,
 * geom)` for spatial analysis. MOD-GIS's own layers contribute their scoped
 * SQL directly; layers registered by other modules are fetched through
 * MapLayer::features() (their own access filtering) and fed back to PostGIS
 * as GeoJSON — so analysis never sees more than the map does.
 */
class FeatureRelation
{
    public function __construct(private LayerService $layers) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox  only used for non-SQL layers
     * @return array{0: string, 1: list<mixed>, 2: MapLayer}
     */
    public function for(User $user, string $key, array $filters = [], ?array $bbox = null, int $limit = 20000): array
    {
        $layer = $this->layers->authorize($user, $key, 'analisis');

        if ($layer instanceof SqlLayer) {
            [$sql, $bindings] = $layer->filtered($user, $filters);

            return ["SELECT s.id::text AS id, s.label::text AS label, s.pautan::text AS pautan, to_jsonb(s) - 'geom' AS props, s.geom FROM ({$sql}) s WHERE s.geom IS NOT NULL", $bindings, $layer];
        }

        $collection = $layer->features($user, $bbox, $filters, $limit);
        $json = json_encode(array_values(array_filter($collection['features'], fn ($f) => ! empty($f['geometry']))));

        return ["SELECT f->'properties'->>'id' AS id, f->'properties'->>'label' AS label, f->'properties'->>'pautan' AS pautan, f->'properties' AS props,
                        ST_SetSRID(ST_GeomFromGeoJSON(f->>'geometry'), 4326) AS geom
                 FROM jsonb_array_elements(?::jsonb) f", [(string) $json], $layer];
    }
}
