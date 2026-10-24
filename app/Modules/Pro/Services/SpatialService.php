<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pro\Models\DataSpatial;
use App\Modules\Pro\Models\LotTanah;
use DOMDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Data Spatial (SRS-PRO-07..10): load a lot geometry from GeoJSON, WKT/EWKT,
 * KML or a zipped Shapefile; reproject from GDM2000 / Selangor Cassini /
 * Kertau RSO to WGS84; record source, acquisition date and uploader; compare
 * the computed area with the declared area; detect overlaps with other lots;
 * and let an authorised officer verify it.
 */
class SpatialService
{
    /** Rough Malaysia bounding box (lng/lat) — catches a wrong SRID. */
    private const BBOX = [99.0, 0.5, 120.0, 7.6];

    public function __construct(
        private Parameters $parameters,
        private AuditLogger $audit,
    ) {}

    /**
     * Turn the input into a valid 4326 MultiPolygon (HEXEWKB).
     *
     * @return array{hex: string, srid: int, format: string}
     */
    public function parse(string $format, ?string $text, int $srid, ?UploadedFile $file = null): array
    {
        $format = strtolower($format);
        if ($file !== null && ($text === null || $text === '')) {
            if ($format === 'shp') {
                $text = $this->shapefileToGeoJson($file, $srid);
                $format = 'geojson';
                $srid = 4326;
            } else {
                $text = (string) file_get_contents($file->getRealPath());
            }
        }
        $text = trim((string) $text);
        if ($text === '') {
            throw ValidationException::withMessages(['geometri' => [__('pro::pro.spatial.kosong')]]);
        }
        if (! in_array($srid, DataSpatial::SRID, true)) {
            throw ValidationException::withMessages(['srid' => [__('pro::pro.spatial.srid_tidak_disokong', ['srid' => $srid, 'senarai' => implode(', ', DataSpatial::SRID)])]]);
        }

        [$sql, $bindings] = match ($format) {
            'geojson' => $this->geoJsonSql($text, $srid),
            'wkt' => str_starts_with(strtoupper($text), 'SRID=')
                ? ['ST_GeomFromEWKT(?)', [$text]]
                : ['ST_GeomFromText(?, '.$srid.')', [$text]],
            'kml' => $this->kmlSql($text),
            default => throw ValidationException::withMessages(['format' => [__('pro::pro.spatial.format_tidak_disokong')]]),
        };
        if ($format === 'kml') {
            $srid = 4326;
        }

        try {
            // Inside a savepoint: a parse error must not poison an enclosing transaction (PostgreSQL).
            $row = DB::transaction(fn () => DB::selectOne(
                "WITH g AS (SELECT ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_Transform(ST_Force2D({$sql}), 4326)), 3)) AS geom)
                 SELECT ST_AsHEXEWKB(geom) AS hex, ST_IsEmpty(geom) AS kosong, ST_XMin(geom) AS x0, ST_YMin(geom) AS y0, ST_XMax(geom) AS x1, ST_YMax(geom) AS y1 FROM g",
                $bindings,
            ));
        } catch (Throwable) {
            throw ValidationException::withMessages(['geometri' => [__('pro::pro.spatial.tidak_sah', ['format' => strtoupper($format)])]]);
        }

        if ($row === null || $row->kosong || $row->hex === null) {
            throw ValidationException::withMessages(['geometri' => [__('pro::pro.spatial.bukan_poligon')]]);
        }
        [$minX, $minY, $maxX, $maxY] = self::BBOX;
        if ($row->x0 < $minX || $row->y0 < $minY || $row->x1 > $maxX || $row->y1 > $maxY) {
            throw ValidationException::withMessages(['srid' => [__('pro::pro.spatial.luar_malaysia', ['srid' => $srid])]]);
        }

        return ['hex' => (string) $row->hex, 'srid' => $srid, 'format' => $format];
    }

    /**
     * @param  array{hex: string, srid: int, format: string}  $geometry
     * @param  array<string, mixed>  $meta  sumber_data, rujukan_fail_sumber, tarikh_perolehan_data, id_dokumen_sumber, sumber_sistem, kunci_asal
     * @return array{spatial: DataSpatial, amaran: list<string>}
     */
    public function store(LotTanah $lot, array $geometry, array $meta, ?User $user): array
    {
        return DB::transaction(function () use ($lot, $geometry, $meta, $user) {
            DB::table('lot_tanah')->where('id', $lot->id)->lockForUpdate()->value('id');
            $previous = DataSpatial::query()->where('lot_id', $lot->id)->where('semasa', true)->first();
            $previous?->update(['semasa' => false]);
            $versi = (int) DataSpatial::query()->where('lot_id', $lot->id)->max('versi') + 1;

            $area = (float) DB::selectOne('SELECT ST_Area(ST_GeomFromEWKB(decode(?, \'hex\'))::geography) AS m2', [$geometry['hex']])->m2;
            $declared = $lot->keluasanM2();
            $diff = $declared ? round(abs($area - $declared) / $declared * 100, 2) : null;

            $spatial = DataSpatial::query()->create([
                'lot_id' => $lot->id,
                'geometri' => $geometry['hex'],
                'srid_asal' => $geometry['srid'],
                'format_asal' => $geometry['format'],
                'keluasan_dikira' => round($area, 4),
                'peratus_beza_keluasan' => $diff,
                'sumber_data' => $meta['sumber_data'],
                'rujukan_fail_sumber' => $meta['rujukan_fail_sumber'] ?? null,
                'tarikh_perolehan_data' => $meta['tarikh_perolehan_data'] ?? null,
                'id_dokumen_sumber' => $meta['id_dokumen_sumber'] ?? null,
                'sumber_sistem' => $meta['sumber_sistem'] ?? null,
                'kunci_asal' => $meta['kunci_asal'] ?? null,
                'status_pengesahan' => 'belum_disahkan',
                'id_pemuat_naik' => $user?->id,
                'semasa' => true,
                'versi' => $versi,
            ]);

            DB::update('UPDATE lot_tanah SET koordinat_pusat = ST_PointOnSurface(ds.geometri)::geometry(Point, 4326) FROM data_spatial ds WHERE ds.id = ? AND lot_tanah.id = ds.lot_id', [$spatial->id]);

            $warnings = [];
            $tolerance = (float) $this->parameters->get('pro.toleransi_keluasan_peratus', 10);
            if ($diff !== null && $diff > $tolerance) {
                $warnings[] = __('pro::pro.spatial.beza_keluasan', ['peratus' => $diff, 'toleransi' => $tolerance]);
            }
            if (! $this->insideMukim($spatial->id, $lot->kod_mukim)) {
                $warnings[] = __('pro::pro.spatial.luar_mukim');
            }

            $overlaps = $this->overlaps($spatial);
            if ($overlaps !== []) {
                $notes = array_map(fn ($o) => __('pro::pro.spatial.catatan_tindih', ['no_lot' => $o['no_lot'], 'mukim' => $o['kod_mukim'], 'peratus' => $o['peratus']]), $overlaps);
                $spatial->update(['status_pengesahan' => 'bertindih', 'catatan_pertindihan' => implode('; ', $notes)]);
                foreach ($overlaps as $o) {
                    $other = DataSpatial::query()->find($o['id_spatial']);
                    if ($other !== null) {
                        $note = __('pro::pro.spatial.catatan_tindih', ['no_lot' => $lot->no_lot, 'mukim' => $lot->kod_mukim, 'peratus' => $o['peratus']]);
                        $other->update([
                            'catatan_pertindihan' => trim(($other->catatan_pertindihan ? $other->catatan_pertindihan.'; ' : '').$note),
                            'status_pengesahan' => $other->disahkan() ? $other->status_pengesahan : 'bertindih',
                        ]);
                    }
                }
                $warnings = [...$warnings, ...$notes];
            }

            return ['spatial' => $spatial->refresh(), 'amaran' => $warnings];
        });
    }

    /**
     * Current geometries of OTHER lots overlapping more than the threshold
     * (share of the smaller polygon).
     *
     * @return list<array{id_spatial: int, lot_id: string, no_lot: string, kod_mukim: string, peratus: float}>
     */
    public function overlaps(DataSpatial $spatial): array
    {
        $threshold = (float) $this->parameters->get('pro.ambang_pertindihan_peratus', 5);

        $rows = DB::select(
            'SELECT o.id AS id_spatial, l.id AS lot_id, l.no_lot, l.kod_mukim,
                    round((ST_Area(ST_Intersection(s.geometri, o.geometri)::geography)
                        / NULLIF(least(ST_Area(s.geometri::geography), ST_Area(o.geometri::geography)), 0) * 100)::numeric, 2) AS peratus
             FROM data_spatial s
             JOIN data_spatial o ON o.semasa AND o.lot_id <> s.lot_id AND ST_Intersects(s.geometri, o.geometri) AND NOT ST_Touches(s.geometri, o.geometri)
             JOIN lot_tanah l ON l.id = o.lot_id AND l.deleted_at IS NULL AND l.status_lot NOT IN (\'arkib\', \'digabung\')
             WHERE s.id = ?',
            [$spatial->id],
        );

        return array_values(array_map(fn ($r) => [
            'id_spatial' => (int) $r->id_spatial,
            'lot_id' => (string) $r->lot_id,
            'no_lot' => (string) $r->no_lot,
            'kod_mukim' => (string) $r->kod_mukim,
            'peratus' => (float) $r->peratus,
        ], array_filter($rows, fn ($r) => (float) $r->peratus >= $threshold)));
    }

    /** SRS-PRO-10 step 6: verify (or send back) a geometry. */
    public function verify(DataSpatial $spatial, string $status, User $user, ?string $note = null): DataSpatial
    {
        if (! in_array($status, DataSpatial::STATUS, true)) {
            throw ValidationException::withMessages(['status_pengesahan' => [__('pro::pro.spatial.status_tidak_sah')]]);
        }
        if (in_array($status, DataSpatial::DISAHKAN, true) && $spatial->id_pemuat_naik !== null && $spatial->id_pemuat_naik === $user->id) {
            throw ValidationException::withMessages(['status_pengesahan' => [__('pro::pro.spatial.pengesah_sama')]]);
        }

        $old = $spatial->status_pengesahan;
        $spatial->update([
            'status_pengesahan' => $status,
            'id_pegawai_pengesah' => $user->id,
            'tarikh_pengesahan' => now(),
            'catatan_pengesahan' => $note,
        ]);
        $this->audit->record('sahkan', 'pro', 'data_spatial', $spatial->id, changes: ['status_pengesahan' => [$old, $status]], meta: ['lot_id' => $spatial->lot_id, 'klasifikasi' => 'dalaman']);

        return $spatial;
    }

    /** @return array<string, mixed>|null GeoJSON Feature of the lot's current geometry */
    public function feature(LotTanah $lot): ?array
    {
        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(geometri, 7) AS g, id, status_pengesahan, sumber_data, keluasan_dikira, srid_asal, versi, tarikh_perolehan_data, updated_at
             FROM data_spatial WHERE lot_id = ? AND semasa',
            [$lot->id],
        );
        if ($row === null) {
            return null;
        }

        return [
            'type' => 'Feature',
            'id' => $lot->id,
            'geometry' => json_decode((string) $row->g, true),
            'properties' => [
                'lot_id' => $lot->id,
                'no_lot' => $lot->no_lot,
                'kod_mukim' => $lot->kod_mukim,
                'kod_daerah' => $lot->kod_daerah,
                'id_spatial' => (int) $row->id,
                'status_pengesahan' => $row->status_pengesahan,
                'disahkan' => in_array($row->status_pengesahan, DataSpatial::DISAHKAN, true),
                'sumber_data' => $row->sumber_data,
                'keluasan_dikira' => $row->keluasan_dikira !== null ? (float) $row->keluasan_dikira : null,
                'srid_asal' => (int) $row->srid_asal,
                'versi' => (int) $row->versi,
                'tarikh_perolehan_data' => $row->tarikh_perolehan_data,
                'tarikh_kemas_kini' => $row->updated_at,
            ],
        ];
    }

    private function insideMukim(int $spatialId, string $kodMukim): bool
    {
        $row = DB::selectOne(
            "SELECT bool_or(ST_Intersects(s.geometri, r.geometri)) AS ok, count(r.kod) AS n
             FROM data_spatial s LEFT JOIN ref_sempadan r ON r.jenis = 'mukim' AND r.kod = ? WHERE s.id = ?",
            [$kodMukim, $spatialId],
        );

        // No reference boundary loaded → nothing to compare against.
        return $row === null || (int) $row->n === 0 || (bool) $row->ok;
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function geoJsonSql(string $text, int $srid): array
    {
        $json = json_decode($text, true);
        if (! is_array($json) || ! isset($json['type'])) {
            throw ValidationException::withMessages(['geometri' => [__('pro::pro.spatial.tidak_sah', ['format' => 'GeoJSON'])]]);
        }

        $geometries = match ($json['type']) {
            'FeatureCollection' => array_values(array_filter(array_map(fn ($f) => $f['geometry'] ?? null, (array) ($json['features'] ?? [])))),
            'Feature' => isset($json['geometry']) ? [$json['geometry']] : [],
            default => [$json],
        };
        if ($geometries === []) {
            throw ValidationException::withMessages(['geometri' => [__('pro::pro.spatial.bukan_poligon')]]);
        }

        $parts = array_fill(0, count($geometries), "ST_SetSRID(ST_GeomFromGeoJSON(?), {$srid})");

        return [count($parts) === 1 ? $parts[0] : 'ST_Union(ARRAY['.implode(', ', $parts).'])', array_map(fn ($g) => json_encode($g), $geometries)];
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function kmlSql(string $text): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($text, LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw ValidationException::withMessages(['geometri' => [__('pro::pro.spatial.tidak_sah', ['format' => 'KML'])]]);
        }

        $fragments = [];
        foreach ($doc->getElementsByTagNameNS('*', 'Polygon') as $polygon) {
            $xml = $doc->saveXML($polygon);
            // ST_GeomFromKML wants un-namespaced elements.
            $fragments[] = (string) preg_replace('/<(\/?)[a-zA-Z0-9]+:/', '<$1', (string) preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', (string) $xml));
        }
        if ($fragments === []) {
            throw ValidationException::withMessages(['geometri' => [__('pro::pro.spatial.bukan_poligon')]]);
        }
        $parts = array_fill(0, count($fragments), 'ST_GeomFromKML(?)');

        return [count($parts) === 1 ? $parts[0] : 'ST_Union(ARRAY['.implode(', ', $parts).'])', $fragments];
    }

    /** Zipped Shapefile → GeoJSON in 4326 via GDAL's ogr2ogr (reads .prj when present). */
    private function shapefileToGeoJson(UploadedFile $file, int $srid): string
    {
        $dir = storage_path('app/tmp/pro-shp-'.Str::random(12));
        @mkdir($dir, 0700, true);
        $zip = $dir.'/input.zip';
        copy($file->getRealPath(), $zip);
        $out = $dir.'/out.geojson';

        $binary = (string) config('pro.ogr2ogr', '/opt/homebrew/bin/ogr2ogr');
        $args = [$binary, '-f', 'GeoJSON', '-t_srs', 'EPSG:4326'];
        if ($srid !== 4326) {
            $args = [...$args, '-s_srs', 'EPSG:'.$srid];
        }
        $process = new Process([...$args, $out, '/vsizip/'.$zip]);
        $process->setTimeout(60);

        try {
            $process->run();
            if (! $process->isSuccessful() || ! is_file($out)) {
                throw ValidationException::withMessages(['fail' => [__('pro::pro.spatial.shp_gagal')]]);
            }

            return (string) file_get_contents($out);
        } finally {
            @unlink($out);
            @unlink($zip);
            @rmdir($dir);
        }
    }
}
