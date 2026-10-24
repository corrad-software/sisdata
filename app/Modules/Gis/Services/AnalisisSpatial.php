<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The five RFP 8.1.6 spatial analyses (SRS-GIS-15..19) plus clustering
 * (GIS-21). Every input layer passes LayerService::authorize() through
 * FeatureRelation, so results contain only features the user may see.
 * Distances/areas are geodesic (geography) and, for official survey use,
 * also planar in GDM2000 / Selangor Cassini (EPSG:3380).
 *
 * Each analysis returns ['jenis', 'ringkasan', 'hasil' (rows with id,
 * label, pautan, lapisan + metrics), 'geometri' (GeoJSON for the map)].
 */
class AnalisisSpatial
{
    public const SRID_CASSINI = 3380;

    public function __construct(private FeatureRelation $relations, private Parameters $parameters) {}

    private function cap(): int
    {
        return max(1, (int) $this->parameters->get('gis.had_hasil_analisis', 2000));
    }

    /**
     * Source geometry: a lot (through the scoped lot layer — out-of-scope lots
     * behave as if they don't exist) or a drawn GeoJSON geometry.
     *
     * @param  array<string, mixed>  $sumber
     * @return array{0: string, 1: list<mixed>, 2: string|null} SQL yielding one geometry, bindings, source lot id
     */
    private function sourceGeometry(User $user, array $sumber): array
    {
        if (! empty($sumber['lot_id'])) {
            [$sql, $bindings] = $this->relations->for($user, 'gis.lot');
            $row = DB::selectOne("SELECT ST_AsEWKT(r.geom) AS g FROM ({$sql}) r WHERE r.id = ?", [...$bindings, (string) $sumber['lot_id']]);
            if ($row === null) {
                throw ValidationException::withMessages(['sumber.lot_id' => [__('gis::gis.analisis.lot_tiada_geometri')]]);
            }

            return ['ST_GeomFromEWKT(?)', [(string) $row->g], (string) $sumber['lot_id']];
        }
        if (! empty($sumber['geometri'])) {
            $json = is_string($sumber['geometri']) ? $sumber['geometri'] : json_encode($sumber['geometri']);
            try {
                DB::selectOne('SELECT ST_GeomFromGeoJSON(?) AS g', [$json]);
            } catch (Throwable) {
                throw ValidationException::withMessages(['sumber.geometri' => [__('gis::gis.analisis.geometri_tidak_sah')]]);
            }

            return ['ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))', [(string) $json], null];
        }

        throw ValidationException::withMessages(['sumber' => [__('gis::gis.analisis.sumber_diperlukan')]]);
    }

    /** @return array<string, mixed> */
    private function rows(string $sql, array $bindings, string $layerKey): array
    {
        return array_map(function ($r) use ($layerKey) {
            $row = (array) $r;
            $row['lapisan'] = $layerKey;
            foreach (['jarak_m', 'luas_tindih_m2', 'peratus', 'luas_m2'] as $f) {
                if (isset($row[$f])) {
                    $row[$f] = round((float) $row[$f], 2);
                }
            }

            return $row;
        }, DB::select($sql, $bindings));
    }

    /** @return array{type: string, features: list<array<string, mixed>>} */
    private function collection(string $sql, array $bindings, array $props = []): array
    {
        $features = [];
        foreach (DB::select("SELECT ST_AsGeoJSON(g, 7) AS j FROM ({$sql}) x(g) WHERE g IS NOT NULL", $bindings) as $r) {
            $features[] = ['type' => 'Feature', 'geometry' => json_decode((string) $r->j, true), 'properties' => $props];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * SRS-GIS-15 Buffer: features of the target layers within `jarak_m` of the source.
     *
     * @param  array<string, mixed>  $p  sumber{lot_id|geometri}, jarak_m, lapisan[]
     * @return array<string, mixed>
     */
    public function buffer(User $user, array $p): array
    {
        [$geom, $gb, $lotId] = $this->sourceGeometry($user, (array) $p['sumber']);
        $distance = (float) $p['jarak_m'];
        $buffer = "ST_Buffer(({$geom})::geography, ?)::geometry";
        $bufferBindings = [...$gb, $distance];

        $results = [];
        foreach ((array) ($p['lapisan'] ?? ['gis.lot']) as $key) {
            [$rel, $rb] = $this->relations->for($user, (string) $key, (array) ($p['penapis'][$key] ?? []));
            $rows = $this->rows(
                "WITH src AS (SELECT {$geom} AS g), buf AS (SELECT {$buffer} AS b)
                 SELECT r.id, r.label, r.pautan, ST_Distance(r.geom::geography, src.g::geography) AS jarak_m
                 FROM ({$rel}) r, src, buf
                 WHERE r.geom && buf.b AND ST_Intersects(r.geom, buf.b) AND r.id IS DISTINCT FROM ?
                 ORDER BY jarak_m LIMIT ".$this->cap(),
                [...$gb, ...$bufferBindings, ...$rb, $lotId],
                (string) $key,
            );
            $results = [...$results, ...$rows];
        }

        return [
            'jenis' => 'penimbal',
            'ringkasan' => ['jarak_m' => $distance, 'bilangan' => count($results), 'mengikut_lapisan' => array_count_values(array_column($results, 'lapisan'))],
            'hasil' => $results,
            'geometri' => $this->collection("SELECT {$buffer}", $bufferBindings, ['peranan' => 'zon_penimbal']),
        ];
    }

    /**
     * SRS-GIS-16 Overlay: features of layer A that overlap features of each layer B.
     *
     * @param  array<string, mixed>  $p  lapisan_a, lapisan_b[], penapis{key: filters}
     * @return array<string, mixed>
     */
    public function overlay(User $user, array $p): array
    {
        $a = (string) ($p['lapisan_a'] ?? 'gis.lot');
        [$relA, $ba] = $this->relations->for($user, $a, (array) ($p['penapis'][$a] ?? []));
        $results = [];
        foreach ((array) $p['lapisan_b'] as $b) {
            [$relB, $bb] = $this->relations->for($user, (string) $b, (array) ($p['penapis'][$b] ?? []));
            $rows = $this->rows(
                "SELECT a.id, a.label, a.pautan, b.id AS id_ciri_b, b.label AS label_b, ?::text AS lapisan_b,
                        ST_Area(ST_Intersection(a.geom, b.geom)::geography) AS luas_tindih_m2,
                        CASE WHEN ST_Dimension(a.geom) = 2 AND ST_Area(a.geom) > 0
                             THEN ST_Area(ST_Intersection(a.geom, b.geom)::geography) / NULLIF(ST_Area(a.geom::geography), 0) * 100 END AS peratus
                 FROM ({$relA}) a JOIN ({$relB}) b ON a.geom && b.geom AND ST_Intersects(a.geom, b.geom) AND NOT ST_Touches(a.geom, b.geom)
                 WHERE NOT (a.id = b.id AND ? = ?)
                 ORDER BY luas_tindih_m2 DESC NULLS LAST LIMIT ".$this->cap(),
                [(string) $b, ...$ba, ...$bb, $a, (string) $b],
                $a,
            );
            $results = [...$results, ...$rows];
        }

        return [
            'jenis' => 'tindihan',
            'ringkasan' => ['lapisan_a' => $a, 'lapisan_b' => array_values((array) $p['lapisan_b']), 'bilangan' => count($results), 'lot_unik' => count(array_unique(array_column($results, 'id')))],
            'hasil' => $results,
            'geometri' => ['type' => 'FeatureCollection', 'features' => []],
        ];
    }

    /**
     * SRS-GIS-17 Spatial query: features of a layer inside an area (drawn
     * polygon, an administrative boundary, or a radius around a point),
     * optionally with attribute filters.
     *
     * @param  array<string, mixed>  $p  lapisan, kawasan{poligon|sempadan{jenis,kod}|titik{lng,lat},jarak_m}, hubungan, penapis
     * @return array<string, mixed>
     */
    public function query(User $user, array $p): array
    {
        $key = (string) ($p['lapisan'] ?? 'gis.lot');
        $area = (array) ($p['kawasan'] ?? []);
        if (! empty($area['poligon'])) {
            $zone = 'ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))';
            $zb = [is_string($area['poligon']) ? $area['poligon'] : (string) json_encode($area['poligon'])];
        } elseif (! empty($area['sempadan']['kod'])) {
            $zone = '(SELECT geometri FROM ref_sempadan WHERE jenis = ? AND kod = ? LIMIT 1)';
            $zb = [(string) ($area['sempadan']['jenis'] ?? 'mukim'), (string) $area['sempadan']['kod']];
        } elseif (isset($area['titik']['lng'], $area['titik']['lat'])) {
            $zone = 'ST_Buffer(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)::geometry';
            $zb = [(float) $area['titik']['lng'], (float) $area['titik']['lat'], max(1.0, (float) ($area['jarak_m'] ?? 500))];
        } else {
            throw ValidationException::withMessages(['kawasan' => [__('gis::gis.analisis.kawasan_diperlukan')]]);
        }

        $within = ($p['hubungan'] ?? 'bersilang') === 'di_dalam';
        $predicate = $within ? 'ST_Within(r.geom, z.g)' : 'ST_Intersects(r.geom, z.g)';
        [$rel, $rb] = $this->relations->for($user, $key, (array) ($p['penapis'] ?? []));

        try {
            $rows = $this->rows(
                "WITH z AS (SELECT {$zone} AS g)
                 SELECT r.id, r.label, r.pautan, ST_Area(r.geom::geography) AS luas_m2
                 FROM ({$rel}) r, z WHERE z.g IS NOT NULL AND r.geom && z.g AND {$predicate}
                 ORDER BY r.label LIMIT ".$this->cap(),
                [...$zb, ...$rb],
                $key,
            );
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['kawasan' => [__('gis::gis.analisis.geometri_tidak_sah')]]);
        }

        return [
            'jenis' => 'pertanyaan',
            'ringkasan' => ['lapisan' => $key, 'bilangan' => count($rows), 'jumlah_luas_m2' => round(array_sum(array_column($rows, 'luas_m2')), 2)],
            'hasil' => $rows,
            'geometri' => $this->collection("SELECT {$zone}", $zb, ['peranan' => 'kawasan_carian']),
        ];
    }

    /**
     * SRS-GIS-18 Area calculation: geodesic area/perimeter (WGS84 ellipsoid)
     * and planar area/perimeter in GDM2000 / Selangor Cassini (EPSG:3380),
     * with units; for a lot also the declared area and the difference.
     *
     * @param  array<string, mixed>  $p  sumber{lot_id|geometri}
     * @return array<string, mixed>
     */
    public function area(User $user, array $p): array
    {
        [$geom, $gb, $lotId] = $this->sourceGeometry($user, (array) $p['sumber']);
        $r = DB::selectOne(
            'WITH g AS (SELECT '.$geom.' AS g)
             SELECT ST_Area(g::geography) AS luas_geo, ST_Perimeter(g::geography) AS perimeter_geo,
                    ST_Area(ST_Transform(g, '.self::SRID_CASSINI.')) AS luas_cassini, ST_Perimeter(ST_Transform(g, '.self::SRID_CASSINI.')) AS perimeter_cassini,
                    ST_NPoints(g) AS bilangan_bucu, ST_AsGeoJSON(g, 7) AS j FROM g',
            $gb,
        );
        $declared = null;
        if ($lotId !== null) {
            [$rel, $rb] = $this->relations->for($user, 'gis.lot');
            $lot = DB::selectOne("SELECT r.props->>'keluasan' AS keluasan, r.props->>'unit_keluasan' AS unit FROM ({$rel}) r WHERE r.id = ?", [...$rb, $lotId]);
            if ($lot !== null && $lot->keluasan !== null) {
                $factor = match ($lot->unit) {
                    'hektar' => 10000, 'ekar' => 4046.8564224, default => 1
                };
                $declared = (float) $lot->keluasan * $factor;
            }
        }
        $m2 = (float) $r->luas_geo;

        return [
            'jenis' => 'keluasan',
            'ringkasan' => [
                'luas_m2' => round($m2, 2),
                'luas_hektar' => round($m2 / 10000, 4),
                'luas_ekar' => round($m2 / 4046.8564224, 4),
                'perimeter_m' => round((float) $r->perimeter_geo, 2),
                'luas_cassini_m2' => round((float) $r->luas_cassini, 2),
                'perimeter_cassini_m' => round((float) $r->perimeter_cassini, 2),
                'bilangan_bucu' => (int) $r->bilangan_bucu,
                'keluasan_diisytihar_m2' => $declared !== null ? round($declared, 2) : null,
                'beza_peratus' => $declared ? round(abs($m2 - $declared) / $declared * 100, 2) : null,
                'unjuran' => [
                    'geodesi' => 'EPSG:4326 WGS 84 — kiraan geodesi atas elipsoid (m², m)',
                    'satah' => 'EPSG:3380 GDM2000 / Selangor Grid (Cassini-Soldner) — kiraan satah (m², m)',
                ],
            ],
            'hasil' => [],
            'geometri' => ['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'geometry' => json_decode((string) $r->j, true), 'properties' => ['peranan' => 'diukur']]]],
        ];
    }

    /**
     * SRS-GIS-19 Proximity. Mode "terdekat": the N nearest target features to
     * a source. Mode "dalam_jarak": every feature of the source layer within
     * jarak_m of ANY target feature (e.g. lots within 500 m of a mosque),
     * with the nearest target and its distance.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    public function proximity(User $user, array $p): array
    {
        $target = (string) $p['lapisan_sasaran'];
        [$relT, $tb] = $this->relations->for($user, $target, (array) ($p['penapis'][$target] ?? []));

        if (($p['mod'] ?? 'terdekat') === 'terdekat') {
            [$geom, $gb] = $this->sourceGeometry($user, (array) $p['sumber']);
            $n = max(1, min(100, (int) ($p['bilangan'] ?? 10)));
            $rows = $this->rows(
                "WITH src AS (SELECT {$geom} AS g)
                 SELECT t.id, t.label, t.pautan, ST_Distance(t.geom::geography, src.g::geography) AS jarak_m
                 FROM ({$relT}) t, src ORDER BY t.geom <-> src.g LIMIT ".($n * 3),
                [...$gb, ...$tb],
                $target,
            );
            usort($rows, fn ($a, $b) => $a['jarak_m'] <=> $b['jarak_m']);
            $rows = array_slice($rows, 0, $n);

            return ['jenis' => 'kedekatan', 'ringkasan' => ['mod' => 'terdekat', 'lapisan_sasaran' => $target, 'bilangan' => count($rows), 'terdekat_m' => $rows[0]['jarak_m'] ?? null], 'hasil' => $rows, 'geometri' => ['type' => 'FeatureCollection', 'features' => []]];
        }

        $source = (string) ($p['lapisan_sumber'] ?? 'gis.lot');
        $distance = max(1.0, (float) ($p['jarak_m'] ?? 500));
        // Prefilter in degrees (≈ 1° ≥ 110 km at Selangor's latitude), exact test in metres.
        $degrees = $distance / 100000;
        [$relS, $sb] = $this->relations->for($user, $source, (array) ($p['penapis'][$source] ?? []));
        $rows = $this->rows(
            "SELECT s.id, s.label, s.pautan, n.label AS sasaran_terdekat, n.id AS id_sasaran, n.jarak AS jarak_m
             FROM ({$relS}) s
             CROSS JOIN LATERAL (
                 SELECT t.id, t.label, ST_Distance(s.geom::geography, t.geom::geography) AS jarak
                 FROM ({$relT}) t
                 WHERE ST_DWithin(s.geom, t.geom, ?) AND ST_DWithin(s.geom::geography, t.geom::geography, ?)
                 ORDER BY s.geom <-> t.geom LIMIT 1
             ) n
             ORDER BY n.jarak LIMIT ".$this->cap(),
            [...$sb, ...$tb, $degrees, $distance],
            $source,
        );

        return ['jenis' => 'kedekatan', 'ringkasan' => ['mod' => 'dalam_jarak', 'lapisan_sumber' => $source, 'lapisan_sasaran' => $target, 'jarak_m' => $distance, 'bilangan' => count($rows)], 'hasil' => $rows, 'geometri' => ['type' => 'FeatureCollection', 'features' => []]];
    }

    /**
     * SRS-GIS-21 clustering: DBSCAN on feature centres in Selangor Cassini
     * metres; returns each cluster's size, centre and hull (heat maps are
     * drawn client-side from the same layer).
     *
     * @param  array<string, mixed>  $p  lapisan, jarak_m (eps), minimum
     * @return array<string, mixed>
     */
    public function cluster(User $user, array $p): array
    {
        $key = (string) ($p['lapisan'] ?? 'gis.lot');
        $eps = max(10.0, (float) ($p['jarak_m'] ?? 1000));
        $min = max(2, (int) ($p['minimum'] ?? 3));
        [$rel, $rb] = $this->relations->for($user, $key, (array) ($p['penapis'] ?? []));

        $rows = DB::select(
            'WITH pts AS (SELECT r.id, r.label, ST_Transform(ST_PointOnSurface(r.geom), '.self::SRID_CASSINI.') AS p FROM ('.$rel.') r),
                  c AS (SELECT id, label, p, ST_ClusterDBSCAN(p, eps := ?, minpoints := ?) OVER () AS k FROM pts)
             SELECT k, count(*) AS bilangan, ST_AsGeoJSON(ST_Transform(ST_Centroid(ST_Collect(p)), 4326), 7) AS pusat,
                    ST_AsGeoJSON(ST_Transform(ST_ConvexHull(ST_Collect(p)), 4326), 7) AS sempadan,
                    (array_agg(id ORDER BY id))[1:50] AS ahli
             FROM c WHERE k IS NOT NULL GROUP BY k ORDER BY count(*) DESC LIMIT 200',
            [...$rb, $eps, $min],
        );

        $hasil = [];
        $features = [];
        foreach ($rows as $r) {
            $members = trim((string) $r->ahli, '{}');
            $hasil[] = ['id' => 'kluster-'.$r->k, 'label' => __('gis::gis.analisis.kluster', ['n' => (int) $r->k + 1]), 'pautan' => null, 'lapisan' => $key, 'bilangan' => (int) $r->bilangan, 'ahli' => $members === '' ? [] : explode(',', $members)];
            $features[] = ['type' => 'Feature', 'geometry' => json_decode((string) $r->sempadan, true), 'properties' => ['peranan' => 'kluster', 'bilangan' => (int) $r->bilangan]];
            $features[] = ['type' => 'Feature', 'geometry' => json_decode((string) $r->pusat, true), 'properties' => ['peranan' => 'pusat_kluster', 'bilangan' => (int) $r->bilangan]];
        }

        return ['jenis' => 'kluster', 'ringkasan' => ['lapisan' => $key, 'jarak_m' => $eps, 'minimum' => $min, 'bilangan_kluster' => count($hasil)], 'hasil' => $hasil, 'geometri' => ['type' => 'FeatureCollection', 'features' => $features]];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function run(User $user, string $type, array $parameters): array
    {
        return match ($type) {
            'penimbal' => $this->buffer($user, $parameters),
            'tindihan' => $this->overlay($user, $parameters),
            'pertanyaan' => $this->query($user, $parameters),
            'keluasan' => $this->area($user, $parameters),
            'kedekatan' => $this->proximity($user, $parameters),
            'kluster' => $this->cluster($user, $parameters),
            default => throw ValidationException::withMessages(['jenis' => [__('gis::gis.analisis.jenis_tidak_sah')]]),
        };
    }
}
