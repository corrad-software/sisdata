<?php

namespace App\Modules\Gis\Layers;

use App\Models\User;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Gis\Contracts\VectorTileLayer;
use Illuminate\Support\Facades\DB;

/**
 * Base for MOD-GIS's own SQL-backed layers. A subclass supplies one scoped
 * SELECT (`source()`) yielding `geom` + property columns; this class turns it
 * into bbox GeoJSON (simplified by zoom), vector tiles and analysis input.
 */
abstract class SqlLayer implements ClassifiedMapLayer, VectorTileLayer
{
    /**
     * Scoped SELECT with `geom` (4326) and every column in columns().
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: list<mixed>}
     */
    abstract public function source(User $user, array $filters = []): array;

    /** @return list<string> property columns (always id, label, pautan first) */
    abstract public function columns(): array;

    /** @return list<string> columns a caller may filter on with equality */
    public function filterable(): array
    {
        return [];
    }

    public function klasifikasi(): string
    {
        return 'dalaman';
    }

    public function scopeSignature(User $user): string
    {
        return substr(sha1($this->key()), 0, 8);
    }

    public function tileSource(User $user, array $filters = []): array
    {
        [$sql, $bindings] = $this->filtered($user, $filters);

        return ['sql' => $sql, 'bindings' => $bindings, 'columns' => $this->columns()];
    }

    /**
     * Source with the caller's equality filters applied.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: list<mixed>}
     */
    public function filtered(User $user, array $filters = []): array
    {
        [$sql, $bindings] = $this->source($user, $filters);
        $where = [];
        foreach ($this->filterable() as $column) {
            $value = $filters[$column] ?? null;
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $where[] = 's."'.$column.'" = ?';
            $bindings[] = (string) $value;
        }

        if ($where === []) {
            return [$sql, $bindings];
        }

        return ['SELECT * FROM ('.$sql.') s WHERE '.implode(' AND ', $where), $bindings];
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        [$sql, $bindings] = $this->filtered($user, $filters);
        $zoom = isset($filters['_zoom']) && is_numeric($filters['_zoom']) ? (float) $filters['_zoom'] : null;
        $tolerance = ($zoom !== null && $this->geometryType() !== 'point' && $zoom < 17) ? 360 / (256 * 2 ** $zoom) * 0.75 : 0.0;

        $select = implode(', ', array_map(fn ($c) => 's."'.$c.'"', $this->columns()));
        $geometry = $tolerance > 0 ? 'ST_SimplifyPreserveTopology(s.geom, ?)' : 's.geom';
        $query = "SELECT {$select}, ST_AsGeoJSON({$geometry}, 7) AS _geojson FROM ({$sql}) s WHERE s.geom IS NOT NULL";
        $params = $tolerance > 0 ? [$tolerance, ...$bindings] : $bindings;
        if ($bbox !== null) {
            $query .= ' AND s.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)';
            array_push($params, ...array_map('floatval', $bbox));
        }
        $query .= ' LIMIT '.max(1, min($limit, 50000));

        $features = [];
        foreach (DB::select($query, $params) as $row) {
            $props = (array) $row;
            $geojson = $props['_geojson'];
            unset($props['_geojson']);
            $features[] = [
                'type' => 'Feature',
                'id' => $props['id'] ?? null,
                'geometry' => json_decode((string) $geojson, true),
                'properties' => $props,
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
