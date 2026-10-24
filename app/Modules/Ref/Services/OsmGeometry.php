<?php

namespace App\Modules\Ref\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns Overpass `out geom` relations into PostGIS (Multi)Polygons and back
 * into compact GeoJSON. All work happens in a session-local temp table —
 * nothing persistent is written by this class.
 */
class OsmGeometry
{
    /**
     * Linework of a relation's way members as WKT MULTILINESTRING.
     *
     * @param  array<string, mixed>  $relation
     */
    public static function relationLines(array $relation): string
    {
        $lines = [];
        foreach ($relation['members'] ?? [] as $member) {
            if (($member['type'] ?? null) !== 'way' || ! in_array($member['role'] ?? '', ['outer', 'inner', ''], true) || empty($member['geometry'])) {
                continue;
            }
            $pts = array_map(fn (array $p) => sprintf('%.7F %.7F', $p['lon'], $p['lat']), $member['geometry']);
            if (count($pts) >= 2) {
                $lines[] = '('.implode(',', $pts).')';
            }
        }

        if ($lines === []) {
            throw new RuntimeException('Relation '.($relation['id'] ?? '?').' has no way geometry');
        }

        return 'MULTILINESTRING('.implode(',', $lines).')';
    }

    public static function createTemp(): void
    {
        DB::statement('DROP TABLE IF EXISTS tmp_ref_sempadan');
        DB::statement('CREATE TEMP TABLE tmp_ref_sempadan (kod text PRIMARY KEY, nama text, osm_id bigint, g geometry(MultiPolygon, 4326))');
    }

    /**
     * Assemble a relation's linework into a valid MultiPolygon (holes from
     * inner rings are resolved by ST_BuildArea) and store it in the temp table.
     */
    public static function insertRelation(string $kod, string $nama, int $osmId, string $linesWkt): void
    {
        DB::insert(
            'INSERT INTO tmp_ref_sempadan (kod, nama, osm_id, g)
             SELECT ?, ?, ?, ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_BuildArea(ST_Union(ST_GeomFromText(?, 4326)))), 3))',
            [$kod, $nama, $osmId, $linesWkt],
        );
    }

    /**
     * Simplify everything in the temp table. With $coverage the polygons are
     * simplified as a coverage (shared edges stay shared, PostGIS ≥ 3.4);
     * otherwise each is simplified on its own.
     */
    public static function simplify(float $tolerance, bool $coverage): void
    {
        if ($coverage) {
            try {
                DB::statement('UPDATE tmp_ref_sempadan t SET g = s.g FROM (
                    SELECT kod, ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_CoverageSimplify(g, ?) OVER ()), 3)) AS g FROM tmp_ref_sempadan
                ) s WHERE s.kod = t.kod', [$tolerance]);

                return;
            } catch (\Throwable) {
                // older PostGIS / invalid coverage: fall through to per-polygon simplification
            }
        }

        DB::statement('UPDATE tmp_ref_sempadan SET g = ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_SimplifyPreserveTopology(g, ?)), 3))', [$tolerance]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties  extra properties per kod
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public static function featureCollection(array $properties = [], int $precision = 5): array
    {
        $rows = DB::select(
            'SELECT kod, nama, osm_id, ST_AsGeoJSON(g, ?) AS geojson,
                    round((ST_Area(g::geography) / 1e6)::numeric, 3) AS luas_km2
             FROM tmp_ref_sempadan WHERE NOT ST_IsEmpty(g) ORDER BY kod',
            [$precision],
        );

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'properties' => array_merge([
                    'kod' => $row->kod,
                    'nama' => $row->nama,
                    'osm_id' => $row->osm_id,
                    'luas_km2' => (float) $row->luas_km2,
                ], $properties[$row->kod] ?? []),
                'geometry' => json_decode($row->geojson, true),
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
