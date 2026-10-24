<?php

namespace App\Modules\Gis\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Thin wrapper over GDAL's ogrinfo / ogr2ogr (array arguments — never a
 * shell string). Used for spatial import (SHP, GeoJSON, KML/KMZ, DXF,
 * GeoPackage) and export with reprojection (SRS-GIS-03/22/26).
 */
class Ogr
{
    public function __construct(private string $ogr2ogr = 'ogr2ogr', private string $ogrinfo = 'ogrinfo') {}

    public function available(): bool
    {
        $p = new Process([$this->ogr2ogr, '--version']);
        $p->run();

        return $p->isSuccessful();
    }

    /**
     * @return list<array{nama: string, bilangan: int|null, srid: int|null}>
     */
    public function layers(string $path): array
    {
        $p = new Process([$this->ogrinfo, '-ro', '-json', '-so', '-al', $path]);
        $p->setTimeout(60);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException(trim($p->getErrorOutput()) ?: 'ogrinfo gagal');
        }
        $json = json_decode($p->getOutput(), true);
        $out = [];
        foreach ((array) ($json['layers'] ?? []) as $layer) {
            $id = $layer['geometryFields'][0]['coordinateSystem']['projjson']['id'] ?? null;
            $out[] = [
                'nama' => (string) ($layer['name'] ?? ''),
                'bilangan' => isset($layer['featureCount']) ? (int) $layer['featureCount'] : null,
                'srid' => is_array($id) && ($id['authority'] ?? '') === 'EPSG' ? (int) $id['code'] : null,
            ];
        }

        return $out;
    }

    /**
     * Convert one layer of $source to GeoJSON (EPSG:4326, 2D).
     *
     * @param  int|null  $sourceSrid  override / assign the source CRS
     */
    public function toGeoJson(string $source, string $layer, string $target, ?int $sourceSrid = null): void
    {
        $args = [$this->ogr2ogr, '-f', 'GeoJSON', '-t_srs', 'EPSG:4326', '-dim', 'XY', '-lco', 'RFC7946=NO', '-lco', 'COORDINATE_PRECISION=8'];
        if ($sourceSrid !== null) {
            array_push($args, '-s_srs', 'EPSG:'.$sourceSrid);
        }
        array_push($args, $target, $source, $layer);
        $this->exec($args);
    }

    /**
     * Convert a GeoJSON file to another format/projection.
     *
     * @param  string  $driver  GeoJSON | KML | ESRI Shapefile | GPKG | CSV | DXF
     */
    public function convert(string $source, string $target, string $driver, int $targetSrid = 4326, ?string $layerName = null): void
    {
        $args = [$this->ogr2ogr, '-f', $driver, '-s_srs', 'EPSG:4326', '-t_srs', 'EPSG:'.$targetSrid];
        if ($driver === 'CSV') {
            array_push($args, '-lco', 'GEOMETRY=AS_WKT');
        }
        if ($driver === 'DXF') {
            // DXF has a fixed schema: geometry only (attributes can't be written).
            array_push($args, '-select', '');
        }
        if ($driver === 'ESRI Shapefile') {
            array_push($args, '-lco', 'ENCODING=UTF-8');
        }
        if ($layerName !== null) {
            array_push($args, '-nln', $layerName);
        }
        array_push($args, $target, $source);
        $this->exec($args);
    }

    /** @param list<string> $args */
    private function exec(array $args): void
    {
        $p = new Process($args);
        $p->setTimeout(180);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException(trim($p->getErrorOutput()) ?: 'ogr2ogr gagal');
        }
    }
}
