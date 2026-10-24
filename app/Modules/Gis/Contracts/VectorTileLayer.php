<?php

namespace App\Modules\Gis\Contracts;

use App\Models\User;

/**
 * Optional on a MapLayer: MOD-GIS serves it as Mapbox Vector Tiles
 * (`/api/v1/gis/tiles/{layer}/{z}/{x}/{y}.pbf`, PostGIS ST_AsMVT) instead of
 * bbox GeoJSON. The source SQL MUST already apply $user's access scope — the
 * tile endpoint adds only the tile envelope (SRS-RBC-08 / SRS-GIS-32).
 */
interface VectorTileLayer extends MapLayer
{
    /**
     * A SELECT yielding a `geom` column (SRID 4326) plus the listed property
     * columns. It is wrapped as a subquery: `SELECT … FROM ({sql}) s WHERE s.geom && <tile>`.
     *
     * @param  array<string, mixed>  $filters
     * @return array{sql: string, bindings: list<mixed>, columns: list<string>}
     */
    public function tileSource(User $user, array $filters = []): array;

    /**
     * Stable hash of everything that decides which features $user sees
     * (scope values, classification levels). Users with the same signature
     * share cached tiles.
     */
    public function scopeSignature(User $user): string;
}
