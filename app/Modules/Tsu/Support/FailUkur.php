<?php

namespace App\Modules\Tsu\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

/**
 * SRS-TSU-16: reads a surveyor's file — DXF (closed LWPOLYLINE / POLYLINE),
 * zipped Shapefile (GDAL ogr2ogr) or GeoJSON — into polygon features with
 * their declared coordinate reference system, and checks the CRS: the file's
 * own CRS (GeoJSON `crs`, Shapefile .prj) must match the one the officer
 * declared; files without a CRS (DXF) take the declared one and are later
 * checked against the Malaysia extent after reprojection.
 */
final class FailUkur
{
    /** SRIDs accepted for Malaysian survey data (WGS84, GDM2000 geographic/RSO/Cassini Selangor, Kertau RSO, Kertau). */
    public const SRID = [4326, 4742, 3375, 3380, 3168, 4245];

    /**
     * @return array{srid: int, features: list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>}
     */
    public static function baca(string $format, UploadedFile|string $input, int $declared): array
    {
        if (! in_array($declared, self::SRID, true)) {
            throw ValidationException::withMessages(['sistem_koordinat' => [__('tsu::tsu.ukur.srid_tidak_disokong', ['srid' => $declared, 'senarai' => implode(', ', self::SRID)])]]);
        }
        $text = $input instanceof UploadedFile && $format !== 'shp' ? (string) file_get_contents((string) $input->getRealPath()) : (is_string($input) ? $input : '');

        return match ($format) {
            'geojson' => self::geojson($text, $declared),
            'dxf' => ['srid' => $declared, 'features' => self::dxf($text)],
            'shp' => self::shapefile($input instanceof UploadedFile ? $input : throw ValidationException::withMessages(['fail' => [__('tsu::tsu.ukur.shp_zip')]]), $declared),
            default => throw ValidationException::withMessages(['format_fail' => [__('tsu::tsu.ukur.format_tidak_disokong')]]),
        };
    }

    /** @return array{srid: int, features: list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>} */
    private static function geojson(string $text, int $declared): array
    {
        $json = json_decode($text, true);
        if (! is_array($json) || ! isset($json['type'])) {
            throw ValidationException::withMessages(['fail' => [__('tsu::tsu.ukur.fail_tidak_sah', ['format' => 'GeoJSON'])]]);
        }
        $crs = $json['crs']['properties']['name'] ?? null;
        $fileSrid = null;
        if (is_string($crs) && preg_match('/EPSG::?(\d{4,5})/i', $crs, $m)) {
            $fileSrid = (int) $m[1];
        } elseif (is_string($crs) && stripos($crs, 'CRS84') !== false) {
            $fileSrid = 4326;
        }
        if ($fileSrid !== null) {
            if ($fileSrid !== $declared) {
                throw ValidationException::withMessages(['sistem_koordinat' => [__('tsu::tsu.ukur.crs_tidak_sepadan', ['fail' => $fileSrid, 'diisytihar' => $declared])]]);
            }
        }
        $features = match ($json['type']) {
            'FeatureCollection' => array_values(array_filter((array) ($json['features'] ?? []), fn ($f) => is_array($f) && isset($f['geometry']))),
            'Feature' => isset($json['geometry']) ? [$json] : [],
            default => [['type' => 'Feature', 'geometry' => $json, 'properties' => []]],
        };
        $out = [];
        foreach ($features as $f) {
            if (in_array($f['geometry']['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                $out[] = ['geometry' => $f['geometry'], 'properties' => (array) ($f['properties'] ?? [])];
            }
        }
        if ($out === []) {
            throw ValidationException::withMessages(['fail' => [__('tsu::tsu.ukur.tiada_poligon')]]);
        }

        return ['srid' => $declared, 'features' => $out];
    }

    /**
     * Minimal DXF (ASCII) reader: closed LWPOLYLINE and POLYLINE/VERTEX
     * entities become polygons; the layer name (group 8) is kept as label.
     *
     * @return list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>
     */
    public static function dxf(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $entities = [];
        $inEntities = false;
        $current = -1;
        $x = null;
        for ($i = 0; $i + 1 < count($lines); $i += 2) {
            $code = trim($lines[$i]);
            $value = trim($lines[$i + 1]);
            if ($code === '2' && $value === 'ENTITIES') {
                $inEntities = true;

                continue;
            }
            if (! $inEntities) {
                continue;
            }
            if ($code === '0') {
                $x = null;
                if ($value === 'VERTEX' && $current >= 0 && $entities[$current]['type'] === 'POLYLINE') {
                    continue;
                }
                if ($value === 'ENDSEC') {
                    break;
                }
                if (in_array($value, ['LWPOLYLINE', 'POLYLINE'], true)) {
                    $entities[] = ['type' => $value, 'pts' => [], 'closed' => false, 'layer' => null];
                    $current = count($entities) - 1;
                } else {
                    $current = -1;
                }

                continue;
            }
            if ($current < 0) {
                continue;
            }
            if ($code === '8') {
                $entities[$current]['layer'] = $value;
            } elseif ($code === '70') {
                $entities[$current]['closed'] = $entities[$current]['closed'] || (((int) $value) & 1) === 1;
            } elseif ($code === '10') {
                $x = (float) $value;
            } elseif ($code === '20' && $x !== null) {
                $entities[$current]['pts'][] = [$x, (float) $value];
                $x = null;
            }
        }
        $features = [];
        foreach ($entities as $e) {
            $ring = $e['pts'];
            if (count($ring) < 3 || (! $e['closed'] && ! self::sama($ring))) {
                continue;
            }
            if (! self::sama($ring)) {
                $ring[] = $ring[0];
            }
            $features[] = ['geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]], 'properties' => ['lapisan' => $e['layer']]];
        }
        if ($features === []) {
            throw ValidationException::withMessages(['fail' => [__('tsu::tsu.ukur.dxf_tiada_poligon')]]);
        }

        return $features;
    }

    /** @param list<array{0: float, 1: float}> $pts */
    private static function sama(array $pts): bool
    {
        if (count($pts) < 4) {
            return false;
        }
        $a = $pts[0];
        $b = $pts[count($pts) - 1];

        return abs($a[0] - $b[0]) < 1e-9 && abs($a[1] - $b[1]) < 1e-9;
    }

    /**
     * Zipped Shapefile via GDAL. A .prj with an EPSG code must match the
     * declared SRID; the output is reprojected to WGS84 by ogr2ogr.
     *
     * @return array{srid: int, features: list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>}
     */
    private static function shapefile(UploadedFile $file, int $declared): array
    {
        $dir = storage_path('app/tmp/tsu-shp-'.Str::random(12));
        @mkdir($dir, 0700, true);
        $zip = $dir.'/input.zip';
        copy((string) $file->getRealPath(), $zip);
        $out = $dir.'/out.geojson';
        $bin = dirname((string) config('pro.ogr2ogr', '/opt/homebrew/bin/ogr2ogr'));

        try {
            $info = new Process([$bin.'/ogrinfo', '-so', '-al', '/vsizip/'.$zip]);
            $info->setTimeout(60);
            $info->run();
            if (! $info->isSuccessful()) {
                throw ValidationException::withMessages(['fail' => [__('tsu::tsu.ukur.fail_tidak_sah', ['format' => 'Shapefile'])]]);
            }
            $txt = $info->getOutput();
            $hasPrj = ! str_contains($txt, 'Layer SRS WKT:'.PHP_EOL.'(unknown)') && str_contains($txt, 'Layer SRS WKT');
            if ($hasPrj && preg_match_all('/(?:ID|AUTHORITY)\["EPSG",\s*"?(\d{4,5})"?\]/', $txt, $m)) {
                $fileSrid = (int) end($m[1]);
                if ($fileSrid !== $declared) {
                    throw ValidationException::withMessages(['sistem_koordinat' => [__('tsu::tsu.ukur.crs_tidak_sepadan', ['fail' => $fileSrid, 'diisytihar' => $declared])]]);
                }
            }
            $args = [$bin.'/ogr2ogr', '-f', 'GeoJSON', '-t_srs', 'EPSG:4326'];
            if (! $hasPrj) {
                $args = [...$args, '-s_srs', 'EPSG:'.$declared];
            }
            $p = new Process([...$args, $out, '/vsizip/'.$zip]);
            $p->setTimeout(60);
            $p->run();
            if (! $p->isSuccessful() || ! is_file($out)) {
                throw ValidationException::withMessages(['fail' => [__('tsu::tsu.ukur.fail_tidak_sah', ['format' => 'Shapefile'])]]);
            }
            $res = self::geojson((string) file_get_contents($out), 4326);

            return ['srid' => 4326, 'features' => $res['features']];
        } finally {
            @unlink($out);
            @unlink($zip);
            @rmdir($dir);
        }
    }
}
