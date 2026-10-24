<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Gis\Models\LogImport;
use App\Modules\Gis\Models\MetadataLapisan;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

/**
 * Spatial file import (SRS-GIS-22, GIS-27, GIS-03): Shapefile (.zip),
 * GeoJSON, KML/KMZ, DXF and GeoPackage via GDAL, reprojected to EPSG:4326
 * from the file's CRS or the one given (GDM2000 / Selangor Cassini 3380,
 * Peninsula RSO 3375, Kertau RSO 3168…). The original file is kept in DMS
 * (which also scans it — a virus or failed scan stops the import). Every
 * feature is checked for empty coordinates, invalid geometry, position
 * outside Selangor (MAIS tolerance), required attributes and — for lot /
 * boundary datasets — overlaps inside the file. Any error rejects the file
 * with a report naming each failing feature; only a clean file can be
 * accepted as a dataset layer.
 */
class ImportSpatial
{
    public const SRID_DISOKONG = [4326, 4742, 3380, 3375, 3168, 3857, 32647, 32648];

    private const SHP_PARTS = ['shp', 'shx', 'dbf', 'prj', 'cpg', 'qix', 'sbn', 'sbx', 'qpj'];

    private const SELANGOR_BBOX = [100.7, 2.55, 101.98, 3.9];

    public function __construct(
        private Ogr $ogr,
        private Parameters $parameters,
        private DocumentStore $documents,
        private LotRegistry $lots,
    ) {}

    /**
     * @param  array{nama_set_data: string, kategori?: string, srid?: int|null, medan_no_lot?: string|null, medan_wajib?: list<string>, kod_mukim?: string|null, klasifikasi?: string}  $meta
     */
    public function import(UploadedFile $file, array $meta, User $user): LogImport
    {
        $name = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $format = match ($ext) {
            'zip' => 'shp', 'json' => 'geojson', default => $ext,
        };
        if (! in_array($format, LogImport::FORMATS, true)) {
            throw ValidationException::withMessages(['fail' => [__('gis::gis.import.format_tidak_disokong', ['format' => $ext])]]);
        }
        $maxMb = (int) $this->parameters->get('gis.import_saiz_maks_mb', 50);
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages(['fail' => [__('gis::gis.import.terlalu_besar', ['had' => $maxMb])]]);
        }
        if (! empty($meta['srid']) && ! in_array((int) $meta['srid'], self::SRID_DISOKONG, true)) {
            throw ValidationException::withMessages(['srid' => [__('gis::gis.import.srid_tidak_disokong', ['senarai' => implode(', ', self::SRID_DISOKONG)])]]);
        }

        $import = LogImport::query()->create([
            'nama_set_data' => $meta['nama_set_data'],
            'nama_fail' => Str::limit($name, 250, ''),
            'format' => $format,
            'kategori' => $meta['kategori'] ?? 'umum',
            'srid_asal' => $meta['srid'] ?? null,
            'medan_no_lot' => $meta['medan_no_lot'] ?? null,
            'klasifikasi' => $meta['klasifikasi'] ?? 'dalaman',
            'status' => 'disemak',
            'bilangan_ciri' => 0,
            'bilangan_sah' => 0,
            'bilangan_ralat' => 0,
            'ralat_fail' => [],
            'amaran' => [],
            'medan' => [],
            'id_pengguna' => $user->id,
        ]);

        $warnings = [];
        try {
            $import->id_dokumen = $this->documents->store($file, 'gis_log_import', $import->id, 'fail_gis', $user, $import->klasifikasi, ['tajuk' => 'Import spatial: '.$import->nama_set_data]);
        } catch (DocumentRejected $e) {
            if (in_array($e->reason, ['virus', 'imbasan_gagal', 'fail_rosak'], true)) {
                return $this->reject($import, [$e->getMessage()]);
            }
            $warnings[] = __('gis::gis.import.tidak_disimpan_dms', ['sebab' => $e->getMessage()]);
        }

        $dir = storage_path('app/gis-import/'.$import->id);
        File::ensureDirectoryExists($dir);
        try {
            $source = $this->stage($file, $format, $dir);
            $layers = $this->ogr->layers($source);
            if ($layers === []) {
                return $this->reject($import, [__('gis::gis.import.tiada_lapisan')], $warnings);
            }
            $srid = ! empty($meta['srid']) ? (int) $meta['srid'] : null;
            $detected = $layers[0]['srid'];
            if ($srid === null && $detected === null) {
                $srid = (int) $this->parameters->get('gis.import_srid_lalai', 3380);
                $warnings[] = __('gis::gis.import.srid_diandaikan', ['srid' => $srid]);
            }
            $import->srid_asal = $srid ?? $detected;

            $limit = (int) $this->parameters->get('gis.import_ciri_maks', 20000);
            $no = 0;
            $fields = [];
            foreach ($layers as $i => $layer) {
                $out = $dir.'/lapisan-'.$i.'.geojson';
                $this->ogr->toGeoJson($source, $layer['nama'], $out, $srid);
                $json = json_decode((string) file_get_contents($out), true);
                foreach (array_chunk((array) ($json['features'] ?? []), 500) as $chunk) {
                    if ($no + count($chunk) > $limit) {
                        return $this->reject($import, [__('gis::gis.import.terlalu_banyak_ciri', ['had' => $limit])], $warnings);
                    }
                    foreach ($chunk as $f) {
                        $fields = array_unique([...$fields, ...array_keys((array) ($f['properties'] ?? []))]);
                    }
                    $this->insertChunk($import->id, $chunk, $no);
                    $no += count($chunk);
                }
            }
            $import->medan = array_values(array_map('strval', $fields));
        } catch (Throwable $e) {
            report($e);

            return $this->reject($import, [__('gis::gis.import.gagal_baca', ['format' => strtoupper($format)])], $warnings);
        } finally {
            File::deleteDirectory($dir);
        }

        if ($no === 0) {
            return $this->reject($import, [__('gis::gis.import.tiada_ciri')], $warnings);
        }

        $this->validate($import, $meta);
        $warnings = [...$warnings, ...$this->matchLots($import, $meta, $user)];

        $counts = DB::selectOne('SELECT count(*) AS n, count(*) FILTER (WHERE sah) AS sah FROM gis_ciri_import WHERE id_import = ?', [$import->id]);
        $import->bilangan_ciri = (int) $counts->n;
        $import->bilangan_sah = (int) $counts->sah;
        $import->bilangan_ralat = $import->bilangan_ciri - $import->bilangan_sah;
        $import->status = $import->bilangan_ralat > 0 ? 'ditolak' : 'sedia';
        $import->amaran = $warnings;
        $import->save();

        return $import;
    }

    /** Copy / unpack the upload into $dir and return the path GDAL should open. */
    private function stage(UploadedFile $file, string $format, string $dir): string
    {
        if ($format !== 'shp') {
            $target = $dir.'/sumber.'.($format === 'geojson' ? 'geojson' : $format);
            File::copy((string) $file->getRealPath(), $target);

            return $target;
        }

        $zip = new ZipArchive;
        if ($zip->open((string) $file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['fail' => [__('gis::gis.import.zip_rosak')]]);
        }
        $shp = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            $base = basename($entry);
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            // Zip-slip safe: only flat Shapefile parts are written, by basename.
            if ($base === '' || str_starts_with($base, '.') || ! in_array($ext, self::SHP_PARTS, true)) {
                continue;
            }
            $stream = $zip->getStream($entry);
            if ($stream === false) {
                continue;
            }
            file_put_contents($dir.'/'.$base, $stream);
            if ($ext === 'shp' && $shp === null) {
                $shp = $dir.'/'.$base;
            }
        }
        $zip->close();
        if ($shp === null) {
            throw ValidationException::withMessages(['fail' => [__('gis::gis.import.tiada_shp')]]);
        }

        return $shp;
    }

    /** @param list<array<string, mixed>> $features */
    private function insertChunk(string $importId, array $features, int $offset): void
    {
        $payload = array_map(fn ($f) => ['p' => (object) ($f['properties'] ?? []), 'g' => $f['geometry'] ?? null], $features);
        DB::insert(
            "INSERT INTO gis_ciri_import (id_import, no_ciri, atribut, geom, sah, ralat, created_at, updated_at)
             SELECT ?, ? + e.n, COALESCE(e.f->'p', '{}'::jsonb),
                    CASE WHEN jsonb_typeof(e.f->'g') = 'object' THEN ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON(e.f->>'g'), 4326)) END,
                    TRUE, '[]'::jsonb, now(), now()
             FROM jsonb_array_elements(?::jsonb) WITH ORDINALITY AS e(f, n)",
            [$importId, $offset, json_encode($payload)],
        );
    }

    /** Append an error message to features matching $condition. */
    private function flag(string $importId, string $condition, string $message, array $bindings = [], string $messageSql = '?::text'): void
    {
        DB::update(
            "UPDATE gis_ciri_import c SET sah = FALSE, ralat = c.ralat || to_jsonb({$messageSql}) WHERE c.id_import = ? AND ({$condition})",
            [$message, $importId, ...$bindings],
        );
    }

    /** @param array<string, mixed> $meta */
    private function validate(LogImport $import, array $meta): void
    {
        $id = $import->id;

        // DXF survey plans arrive as closed polylines: make polygons for area datasets.
        if (in_array($import->kategori, ['lot', 'sempadan', 'zoning'], true)) {
            DB::update("UPDATE gis_ciri_import SET geom = ST_MakePolygon(geom) WHERE id_import = ? AND GeometryType(geom) = 'LINESTRING' AND ST_IsClosed(geom) AND ST_NPoints(geom) >= 4", [$id]);
        }

        $this->flag($id, 'c.geom IS NULL OR ST_IsEmpty(c.geom)', __('gis::gis.import.ralat.kosong'));
        DB::update(
            'UPDATE gis_ciri_import c SET sah = FALSE, ralat = c.ralat || to_jsonb(format(?, ST_IsValidReason(c.geom))) WHERE c.id_import = ? AND c.geom IS NOT NULL AND NOT ST_IsEmpty(c.geom) AND NOT ST_IsValid(c.geom)',
            [__('gis::gis.import.ralat.tidak_sah'), $id],
        );

        [$x0, $y0, $x1, $y1] = self::SELANGOR_BBOX;
        $tolerance = (float) $this->parameters->get('gis.toleransi_ketepatan_m', 50);
        DB::update(
            "WITH n AS (SELECT ST_Buffer(COALESCE((SELECT geometri FROM ref_sempadan WHERE jenis = 'negeri' AND kod = '10' LIMIT 1), ST_MakeEnvelope({$x0}, {$y0}, {$x1}, {$y1}, 4326))::geography, ?)::geometry AS g)
             UPDATE gis_ciri_import c SET sah = FALSE, ralat = c.ralat || to_jsonb(format(?, round(ST_X(ST_PointOnSurface(c.geom))::numeric, 6), round(ST_Y(ST_PointOnSurface(c.geom))::numeric, 6), ?::text))
             FROM n WHERE c.id_import = ? AND c.geom IS NOT NULL AND NOT ST_IsEmpty(c.geom) AND ST_IsValid(c.geom) AND NOT ST_Within(c.geom, n.g)",
            [$tolerance, __('gis::gis.import.ralat.luar_selangor'), (string) $tolerance, $id],
        );

        foreach ((array) ($meta['medan_wajib'] ?? []) as $field) {
            $field = (string) $field;
            $this->flag($id, "COALESCE(c.atribut->>?, '') = ''", __('gis::gis.import.ralat.medan_wajib', ['medan' => $field]), [$field]);
        }

        if (in_array($import->kategori, ['lot', 'sempadan'], true)) {
            DB::update(
                'UPDATE gis_ciri_import c SET sah = FALSE, ralat = c.ralat || to_jsonb(format(?, o.no_lain))
                 FROM (SELECT a.id, min(b.no_ciri) AS no_lain FROM gis_ciri_import a JOIN gis_ciri_import b
                         ON b.id_import = a.id_import AND b.id <> a.id AND a.geom && b.geom
                       WHERE a.id_import = ? AND ST_Dimension(a.geom) = 2 AND ST_Dimension(b.geom) = 2 AND ST_IsValid(a.geom) AND ST_IsValid(b.geom)
                         AND ST_Overlaps(a.geom, b.geom) AND ST_Area(ST_Intersection(a.geom, b.geom)::geography) > 1
                       GROUP BY a.id) o
                 WHERE c.id = o.id',
                [__('gis::gis.import.ralat.bertindih'), $id],
            );
        }
    }

    /**
     * Match features of a lot dataset to registered lots by number (+ mukim).
     *
     * @param  array<string, mixed>  $meta
     * @return list<string> warnings
     */
    private function matchLots(LogImport $import, array $meta, User $user): array
    {
        $field = $import->medan_no_lot;
        if ($field === null || $field === '') {
            return [];
        }
        $unmatched = 0;
        foreach (DB::table('gis_ciri_import')->where('id_import', $import->id)->orderBy('no_ciri')->get(['id', 'atribut']) as $row) {
            $attributes = (array) json_decode((string) $row->atribut, true);
            $number = trim((string) ($attributes[$field] ?? ''));
            $mukim = (string) ($attributes['kod_mukim'] ?? $meta['kod_mukim'] ?? '');
            $lotId = ($number !== '' && $mukim !== '') ? $this->lots->findByNumber($mukim, $number, $user) : null;
            if ($lotId === null) {
                $unmatched++;

                continue;
            }
            DB::table('gis_ciri_import')->where('id', $row->id)->update(['lot_id' => $lotId]);
        }

        return $unmatched > 0 ? [__('gis::gis.import.tidak_dipadan', ['n' => $unmatched])] : [];
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function reject(LogImport $import, array $errors, array $warnings = []): LogImport
    {
        $import->status = 'ditolak';
        $import->ralat_fail = $errors;
        $import->amaran = $warnings;
        $import->save();

        return $import;
    }

    /** @param array<string, mixed> $metadata */
    public function accept(LogImport $import, User $user, array $metadata = []): LogImport
    {
        if ($import->status !== 'sedia') {
            throw ValidationException::withMessages(['status' => [__('gis::gis.import.tidak_boleh_terima')]]);
        }
        DB::transaction(function () use ($import, $user, $metadata) {
            $import->update(['status' => 'diterima', 'diterima_pada' => now()]);
            MetadataLapisan::query()->updateOrCreate(['id_import' => $import->id], array_filter([
                'sumber_data' => $metadata['sumber_data'] ?? $import->nama_fail,
                'pemilik_data' => $metadata['pemilik_data'] ?? null,
                'tarikh_kemas_kini' => $metadata['tarikh_kemas_kini'] ?? now()->toDateString(),
                'status_pengesahan' => 'disahkan',
                'sistem_unjuran' => 'EPSG:4326 (asal EPSG:'.($import->srid_asal ?? 4326).')',
                'tahap_ketepatan' => $metadata['tahap_ketepatan'] ?? null,
                'had_penggunaan' => $metadata['had_penggunaan'] ?? null,
                'dikemas_kini_oleh' => $user->id,
            ], fn ($v) => $v !== null));
        });

        return $import->refresh();
    }

    public function cancel(LogImport $import): LogImport
    {
        DB::table('gis_ciri_import')->where('id_import', $import->id)->delete();
        $import->update(['status' => 'dibatalkan']);

        return $import;
    }
}
