<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use Illuminate\Support\Facades\DB;

/**
 * Spatial QA (SRS-GIS-27/28): scans the current lot geometries (MOD-PRO's
 * data_spatial, read-only) for invalid / empty geometry, lots without
 * geometry, coordinates outside Selangor or outside their mukim (beyond the
 * MAIS tolerance), overlaps between lots and gaps between adjacent lots.
 * Findings go to gis_ralat_kualiti (re-runs update them; a finding no longer
 * detected by a re-run is closed automatically). Corrections themselves are made in
 * MOD-PRO's spatial page — MOD-GIS never writes lot geometry.
 */
class KualitiSpatial
{
    public const CHECKS = ['geometri_tidak_sah', 'geometri_kosong', 'tiada_geometri', 'luar_selangor', 'luar_mukim', 'pertindihan', 'jurang'];

    /** Fallback Selangor envelope when MOD-REF's state boundary isn't loaded. */
    private const SELANGOR_BBOX = [100.7, 2.55, 101.98, 3.9];

    public function __construct(private LotScope $scope, private Parameters $parameters) {}

    /**
     * @param  list<string>|null  $checks
     * @return array{dikesan: array<string, int>, ditutup: int, jumlah_terbuka: int}
     */
    public function run(?User $user = null, ?array $checks = null): array
    {
        $checks = $checks === null ? self::CHECKS : array_values(array_intersect($checks, self::CHECKS));
        [$where, $bindings] = $user !== null ? $this->scope->where($user, 'l') : ["l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung')", []];
        $found = [];
        $keys = [];

        DB::transaction(function () use ($checks, $where, $bindings, &$found, &$keys) {
            foreach ($checks as $check) {
                [$select, $params] = $this->{'sql'.str_replace('_', '', ucwords($check, '_'))}($where, $bindings);
                $rows = DB::select(
                    "INSERT INTO gis_ralat_kualiti (kunci, jenis_ralat, id_lot, id_lot_lain, kod_daerah, kod_mukim, perihal, nilai, cadangan, geom, status_pembetulan, dikesan_pada, created_at, updated_at)
                     SELECT f.kunci, ?, f.id_lot, f.id_lot_lain, f.kod_daerah, f.kod_mukim, f.perihal, f.nilai, f.cadangan, f.geom, 'baharu', now(), now(), now() FROM ({$select}) f
                     ON CONFLICT (kunci) DO UPDATE SET perihal = EXCLUDED.perihal, nilai = EXCLUDED.nilai, cadangan = EXCLUDED.cadangan, geom = EXCLUDED.geom,
                        dikesan_pada = EXCLUDED.dikesan_pada, updated_at = now(), diselesaikan_pada = NULL,
                        status_pembetulan = CASE WHEN gis_ralat_kualiti.status_pembetulan = 'selesai' THEN 'baharu' ELSE gis_ralat_kualiti.status_pembetulan END
                     RETURNING kunci",
                    [$check, ...$params],
                );
                $found[$check] = count($rows);
                array_push($keys, ...array_map(fn ($r) => (string) $r->kunci, $rows));
            }
        });

        // Close what this run no longer finds, within the scope it covered.
        $closed = DB::update(
            "UPDATE gis_ralat_kualiti SET status_pembetulan = 'selesai', diselesaikan_pada = now(), updated_at = now(), catatan = ?
             WHERE kunci NOT IN (SELECT jsonb_array_elements_text(?::jsonb)) AND status_pembetulan IN ('baharu', 'dalam_tindakan') AND jenis_ralat IN (".implode(',', array_fill(0, max(1, count($checks)), '?')).")
               AND ((id_lot IS NOT NULL AND id_lot IN (SELECT l.id FROM lot_tanah l WHERE {$where}))
                 OR (id_lot IS NULL AND kod_mukim IN (SELECT DISTINCT l.kod_mukim FROM lot_tanah l WHERE {$where})))",
            [__('gis::gis.kualiti.tidak_dikesan_lagi'), (string) json_encode($keys), ...($checks === [] ? [''] : $checks), ...$bindings, ...$bindings],
        );

        $open = DB::table('gis_ralat_kualiti')->whereIn('status_pembetulan', ['baharu', 'dalam_tindakan'])->count();

        return ['dikesan' => $found, 'ditutup' => $closed, 'jumlah_terbuka' => $open];
    }

    private function base(string $where): string
    {
        return "FROM lot_tanah l JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa WHERE {$where}";
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function sqlGeometriTidakSah(string $where, array $bindings): array
    {
        return ["SELECT 'tidak_sah:' || l.id AS kunci, l.id AS id_lot, NULL::uuid AS id_lot_lain, l.kod_daerah, l.kod_mukim,
                        format(?, l.no_lot, ST_IsValidReason(ds.geometri)) AS perihal,
                        abs(ST_Area(ST_MakeValid(ds.geometri)::geography) - ST_Area(ds.geometri::geography)) AS nilai,
                        format(?, round((abs(ST_Area(ST_MakeValid(ds.geometri)::geography) - ST_Area(ds.geometri::geography)) / NULLIF(ST_Area(ds.geometri::geography), 0) * 100)::numeric, 2)) AS cadangan,
                        ds.geometri::geometry AS geom
                 {$this->base($where)} AND NOT ST_IsEmpty(ds.geometri) AND NOT ST_IsValid(ds.geometri)",
            [__('gis::gis.kualiti.perihal.tidak_sah'), __('gis::gis.kualiti.cadangan.make_valid'), ...$bindings]];
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function sqlGeometriKosong(string $where, array $bindings): array
    {
        return ["SELECT 'kosong:' || l.id AS kunci, l.id AS id_lot, NULL::uuid AS id_lot_lain, l.kod_daerah, l.kod_mukim,
                        format(?, l.no_lot) AS perihal, NULL::numeric AS nilai, ?::text AS cadangan, NULL::geometry AS geom
                 {$this->base($where)} AND (ST_IsEmpty(ds.geometri) OR ST_NPoints(ds.geometri) < 4)",
            [__('gis::gis.kualiti.perihal.kosong'), __('gis::gis.kualiti.cadangan.muat_naik'), ...$bindings]];
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function sqlTiadaGeometri(string $where, array $bindings): array
    {
        return ["SELECT 'tiada:' || l.id AS kunci, l.id AS id_lot, NULL::uuid AS id_lot_lain, l.kod_daerah, l.kod_mukim,
                        format(?, l.no_lot) AS perihal, NULL::numeric AS nilai, ?::text AS cadangan,
                        (SELECT ST_PointOnSurface(m.geometri) FROM ref_sempadan m WHERE m.jenis = 'mukim' AND m.kod = l.kod_mukim LIMIT 1) AS geom
                 FROM lot_tanah l WHERE {$where} AND NOT EXISTS (SELECT 1 FROM data_spatial ds WHERE ds.lot_id = l.id AND ds.semasa)",
            [__('gis::gis.kualiti.perihal.tiada'), __('gis::gis.kualiti.cadangan.muat_naik'), ...$bindings]];
    }

    private function toleranceMetres(): float
    {
        return max(0.0, (float) $this->parameters->get('gis.toleransi_ketepatan_m', 50));
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function sqlLuarSelangor(string $where, array $bindings): array
    {
        [$x0, $y0, $x1, $y1] = self::SELANGOR_BBOX;
        $tolerance = $this->toleranceMetres();

        return ["WITH n AS (
                    SELECT ST_Buffer(COALESCE((SELECT geometri FROM ref_sempadan WHERE jenis = 'negeri' AND kod = '10' LIMIT 1), ST_MakeEnvelope({$x0}, {$y0}, {$x1}, {$y1}, 4326))::geography, ?)::geometry AS g
                 )
                 SELECT 'luar_selangor:' || l.id AS kunci, l.id AS id_lot, NULL::uuid AS id_lot_lain, l.kod_daerah, l.kod_mukim,
                        format(?, l.no_lot, ?::text) AS perihal, ST_Distance(ds.geometri::geography, (SELECT g FROM n)::geography) AS nilai,
                        ?::text AS cadangan, ds.geometri::geometry AS geom
                 {$this->base($where)} AND NOT ST_IsEmpty(ds.geometri) AND NOT ST_Within(ds.geometri, (SELECT g FROM n))",
            [$tolerance, __('gis::gis.kualiti.perihal.luar_selangor'), (string) $tolerance, __('gis::gis.kualiti.cadangan.semak_srid'), ...$bindings]];
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function sqlLuarMukim(string $where, array $bindings): array
    {
        return ["SELECT 'luar_mukim:' || l.id AS kunci, l.id AS id_lot, NULL::uuid AS id_lot_lain, l.kod_daerah, l.kod_mukim,
                        format(?, l.no_lot, l.kod_mukim) AS perihal, ST_Distance(ds.geometri::geography, m.geometri::geography) AS nilai,
                        ?::text AS cadangan, ds.geometri::geometry AS geom
                 FROM lot_tanah l JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
                 JOIN ref_sempadan m ON m.jenis = 'mukim' AND m.kod = l.kod_mukim
                 WHERE {$where} AND NOT ST_IsEmpty(ds.geometri)
                   AND NOT ST_DWithin(ds.geometri::geography, m.geometri::geography, ?)",
            [__('gis::gis.kualiti.perihal.luar_mukim'), __('gis::gis.kualiti.cadangan.semak_mukim'), ...$bindings, $this->toleranceMetres()]];
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function sqlPertindihan(string $where, array $bindings): array
    {
        $whereB = str_replace('l.', 'lb.', $where);
        $threshold = (float) $this->parameters->get('gis.ambang_pertindihan_peratus', 1);

        return ["SELECT * FROM (
                    SELECT 'tindih:' || a.lot_id || ':' || b.lot_id AS kunci, a.lot_id AS id_lot, b.lot_id AS id_lot_lain, l.kod_daerah, l.kod_mukim,
                           format(?, l.no_lot, lb.no_lot, round((ST_Area(ST_Intersection(a.geometri, b.geometri)::geography))::numeric, 2)) AS perihal,
                           ST_Area(ST_Intersection(a.geometri, b.geometri)::geography) AS nilai, ?::text AS cadangan,
                           ST_Intersection(a.geometri, b.geometri) AS geom,
                           ST_Area(ST_Intersection(a.geometri, b.geometri)::geography) / NULLIF(least(ST_Area(a.geometri::geography), ST_Area(b.geometri::geography)), 0) * 100 AS peratus
                    FROM data_spatial a JOIN lot_tanah l ON l.id = a.lot_id
                    JOIN data_spatial b ON b.semasa AND b.lot_id > a.lot_id AND a.geometri && b.geometri
                    JOIN lot_tanah lb ON lb.id = b.lot_id
                    WHERE a.semasa AND {$where} AND {$whereB} AND ST_IsValid(a.geometri) AND ST_IsValid(b.geometri)
                      AND ST_Intersects(a.geometri, b.geometri) AND NOT ST_Touches(a.geometri, b.geometri)
                 ) x WHERE x.peratus >= ?",
            [__('gis::gis.kualiti.perihal.pertindihan'), __('gis::gis.kualiti.cadangan.pertindihan'), ...$bindings, ...$bindings, $threshold]];
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function sqlJurang(string $where, array $bindings): array
    {
        return ["WITH u AS (
                    SELECT l.kod_mukim, min(l.kod_daerah) AS kod_daerah, ST_Union(ds.geometri) AS g
                    FROM lot_tanah l JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
                    WHERE {$where} AND ST_IsValid(ds.geometri) GROUP BY l.kod_mukim HAVING count(*) > 1
                 ), parts AS (SELECT u.kod_mukim, u.kod_daerah, (ST_Dump(u.g)).geom AS p FROM u)
                 SELECT 'jurang:' || parts.kod_mukim || ':' || md5(ST_AsText(ST_SnapToGrid(d.geom, 0.000001))) AS kunci, NULL::uuid AS id_lot, NULL::uuid AS id_lot_lain,
                        parts.kod_daerah, parts.kod_mukim, format(?, parts.kod_mukim, round(ST_Area(d.geom::geography)::numeric, 2)) AS perihal,
                        ST_Area(d.geom::geography) AS nilai, ?::text AS cadangan, ST_MakePolygon(ST_ExteriorRing(d.geom)) AS geom
                 FROM parts, LATERAL ST_DumpRings(parts.p) d
                 WHERE d.path[1] > 0 AND ST_Area(d.geom::geography) <= ?",
            [...$bindings, __('gis::gis.kualiti.perihal.jurang'), __('gis::gis.kualiti.cadangan.jurang'), (float) $this->parameters->get('gis.ambang_jurang_m2', 500)]];
    }
}
