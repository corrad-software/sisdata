<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Gis\Contracts\MapLayer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * SRS-GIS-26: spatial data leaves the system in open formats only (GeoJSON,
 * KML, Shapefile, GeoPackage, CSV+WKT, DXF), optionally reprojected to
 * GDM2000 / Selangor Cassini or Peninsula RSO. Only features the user may
 * see (same MapLayer::features path as the map); every export is audited.
 */
class EksportSpatial
{
    public const FORMATS = [
        'geojson' => ['GeoJSON', 'geojson', 'application/geo+json'],
        'kml' => ['KML', 'kml', 'application/vnd.google-earth.kml+xml'],
        'shp' => ['ESRI Shapefile', 'zip', 'application/zip'],
        'gpkg' => ['GPKG', 'gpkg', 'application/geopackage+sqlite3'],
        'csv' => ['CSV', 'csv', 'text/csv'],
        'dxf' => ['DXF', 'dxf', 'application/dxf'],
    ];

    public const SRID = [4326, 3380, 3375];

    public function __construct(private Ogr $ogr, private Parameters $parameters, private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     */
    public function export(User $user, MapLayer $layer, string $format, int $srid = 4326, array $filters = [], ?array $bbox = null): BinaryFileResponse
    {
        if (! isset(self::FORMATS[$format])) {
            throw ValidationException::withMessages(['format' => [__('gis::gis.eksport.format_tidak_sah')]]);
        }
        if (! in_array($srid, self::SRID, true)) {
            throw ValidationException::withMessages(['srid' => [__('gis::gis.eksport.srid_tidak_sah')]]);
        }
        $limit = (int) $this->parameters->get('gis.eksport_ciri_maks', 50000);
        $collection = $layer->features($user, $bbox, $filters, $limit);
        // Flatten properties to scalars so every driver can write them.
        $collection['features'] = array_map(function ($f) {
            $f['properties'] = array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), (array) ($f['properties'] ?? []));

            return $f;
        }, array_values(array_filter($collection['features'], fn ($f) => ! empty($f['geometry']))));

        [$driver, $ext, $mime] = self::FORMATS[$format];
        $name = Str::slug(str_replace('.', '-', $layer->key())).'-'.now()->format('Ymd-His');
        $dir = storage_path('app/gis-eksport/'.Str::uuid());
        File::ensureDirectoryExists($dir);
        $source = $dir.'/sumber.geojson';
        file_put_contents($source, (string) json_encode($collection));

        if ($format === 'geojson' && $srid === 4326) {
            $file = $source;
        } elseif ($format === 'shp') {
            $shpDir = $dir.'/shp';
            File::ensureDirectoryExists($shpDir);
            $this->ogr->convert($source, $shpDir.'/'.$name.'.shp', $driver, $srid, $name);
            $file = $dir.'/'.$name.'.zip';
            $zip = new ZipArchive;
            $zip->open($file, ZipArchive::CREATE);
            foreach (File::files($shpDir) as $part) {
                $zip->addFile($part->getPathname(), $part->getFilename());
            }
            $zip->close();
        } else {
            $file = $dir.'/'.$name.'.'.$ext;
            $this->ogr->convert($source, $file, $driver, $format === 'kml' ? 4326 : $srid, $name);
        }

        $this->audit->record('eksport', 'gis', 'gis_lapisan', $layer->key(), meta: [
            'format' => $format, 'srid' => $srid, 'bilangan_ciri' => count($collection['features']), 'penapis' => $filters, 'bbox' => $bbox,
        ]);

        register_shutdown_function(fn () => File::deleteDirectory($dir));

        return response()->download($file, $name.'.'.$ext, ['Content-Type' => $mime]);
    }
}
