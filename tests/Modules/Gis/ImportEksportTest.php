<?php

namespace Tests\Modules\Gis;

use App\Models\User;
use App\Modules\Gis\Services\Ogr;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ImportEksportTest extends GisTestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(Ogr::class)->available()) {
            $this->markTestSkipped('GDAL ogr2ogr tidak dipasang.');
        }
        $this->tmp = storage_path('framework/testing/gis-'.Str::random(8));
        File::ensureDirectoryExists($this->tmp);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    /** @param list<array{0: array<string, mixed>, 1: array<string, mixed>|null}> $features */
    private function geojson(array $features, ?int $srid = null): string
    {
        $fc = ['type' => 'FeatureCollection', 'features' => array_map(fn ($f) => ['type' => 'Feature', 'properties' => $f[0], 'geometry' => $f[1]], $features)];
        if ($srid !== null) {
            $fc['crs'] = ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:EPSG::'.$srid]];
        }

        return (string) json_encode($fc);
    }

    /** @return array<string, mixed> */
    private function poly(float $lng, float $lat, float $s = 0.001): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$lng, $lat], [$lng + $s, $lat], [$lng + $s, $lat + $s], [$lng, $lat + $s], [$lng, $lat]]]];
    }

    private function file(string $name, string $content): UploadedFile
    {
        $path = $this->tmp.'/'.$name;
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function importer(): User
    {
        return $this->user(['gis.peta.lihat', 'pro.lot.lihat', 'gis.import.lihat', 'gis.import.cipta', 'gis.peta.eksport']);
    }

    public function test_SRS_GIS_22_valid_geojson_validated_accepted_and_published_as_dataset_layer(): void
    {
        $lot = $this->lot(null, ['no_lot' => 'Lot 77']);
        $user = $this->importer();
        $content = $this->geojson([[['no_lot' => 'Lot 77', 'nama' => 'Zon A'], $this->poly(101.55, 3.10)], [['no_lot' => 'Lot 78', 'nama' => 'Zon B'], $this->poly(101.56, 3.10)]]);

        $res = $this->actingAs($user)->post('/api/v1/gis/import', [
            'fail' => $this->file('zon.geojson', $content), 'nama_set_data' => 'Zon JPBD Petaling', 'kategori' => 'zoning',
            'medan_no_lot' => 'no_lot', 'kod_mukim' => '100803', 'medan_wajib' => ['nama'],
        ])->assertCreated();
        $id = $res->json('data.id');
        $res->assertJsonPath('data.status', 'sedia')->assertJsonPath('data.bilangan_ciri', 2)->assertJsonPath('data.bilangan_ralat', 0);
        $this->assertCount(1, $this->documents->stored, 'Fail asal disimpan dalam DMS');
        $this->assertSame($lot, DB::table('gis_ciri_import')->where('id_import', $id)->where('no_ciri', 1)->value('lot_id'));
        $this->assertNotEmpty($res->json('data.amaran'), 'Lot 78 tidak dipadankan');

        // Not visible on the map until accepted.
        $this->assertSame([], $this->actingAs($user)->getJson('/api/v1/gis/lapisan/gis.set_data/ciri')->json('features'));
        $this->actingAs($user)->postJson("/api/v1/gis/import/{$id}/terima", ['pemilik_data' => 'JPBD Selangor'])->assertOk()->assertJsonPath('data.status', 'diterima');
        $features = $this->actingAs($user)->getJson("/api/v1/gis/lapisan/gis.set_data/ciri?penapis[id_import]={$id}")->json('features');
        $this->assertCount(2, $features);
        $this->assertSame('zoning', $features[0]['properties']['kategori']);
        $this->assertSame('JPBD Selangor', DB::table('gis_metadata_lapisan')->where('id_import', $id)->value('pemilik_data'));
        $this->assertAudited('kemas_kini', ['modul' => 'gis', 'nama_entiti' => 'gis_log_import', 'id_rekod' => $id]);
    }

    public function test_SRS_GIS_22_invalid_geometry_and_outside_selangor_rejected_naming_each_feature(): void
    {
        $bowtie = ['type' => 'Polygon', 'coordinates' => [[[101.55, 3.10], [101.56, 3.11], [101.56, 3.10], [101.55, 3.11], [101.55, 3.10]]]];
        $content = $this->geojson([
            [['nama' => 'Baik'], $this->poly(101.55, 3.10)],
            [['nama' => 'Bowtie'], $bowtie],
            [['nama' => 'Pulau Pinang'], $this->poly(100.30, 5.40)],
            [['nama' => ''], $this->poly(101.57, 3.10)],
        ]);
        $user = $this->importer();
        $res = $this->actingAs($user)->post('/api/v1/gis/import', ['fail' => $this->file('lot.geojson', $content), 'nama_set_data' => 'Uji', 'kategori' => 'umum', 'medan_wajib' => ['nama']])
            ->assertCreated()->assertJsonPath('data.status', 'ditolak')->assertJsonPath('data.bilangan_ralat', 3);

        $id = $res->json('data.id');
        $errors = collect($this->actingAs($user)->getJson("/api/v1/gis/import/{$id}")->assertOk()->json('ralat_ciri'))->keyBy('no_ciri');
        $this->assertStringContainsString('tidak sah', $errors[2]['ralat'][0]);
        $this->assertStringContainsString('Selangor', $errors[3]['ralat'][0]);
        $this->assertStringContainsString('nama', $errors[4]['ralat'][0]);
        $this->actingAs($user)->postJson("/api/v1/gis/import/{$id}/terima")->assertStatus(422);

        $report = $this->actingAs($user)->get("/api/v1/gis/import/{$id}/laporan?format=csv")->assertOk()->streamedContent();
        $this->assertStringContainsString('Self-intersection', $report);
    }

    public function test_SRS_GIS_22_virus_found_by_dms_scan_stops_the_import(): void
    {
        $this->documents->rejectReason = 'virus';
        $this->actingAs($this->importer())->post('/api/v1/gis/import', ['fail' => $this->file('a.geojson', $this->geojson([[['n' => 1], $this->poly(101.55, 3.1)]])), 'nama_set_data' => 'X', 'kategori' => 'umum'])
            ->assertCreated()->assertJsonPath('data.status', 'ditolak')->assertJsonPath('data.bilangan_ciri', 0);
        $this->assertSame(0, DB::table('gis_ciri_import')->count());
    }

    public function test_SRS_GIS_22_shapefile_kml_and_dxf_are_imported(): void
    {
        $user = $this->importer();
        $ogr = app(Ogr::class);
        $src = $this->tmp.'/src.geojson';
        file_put_contents($src, $this->geojson([[['no_lot' => 'L1'], $this->poly(101.55, 3.10)], [['no_lot' => 'L2'], $this->poly(101.56, 3.10)]]));

        // Shapefile bundle (.zip)
        File::ensureDirectoryExists($this->tmp.'/shp');
        $ogr->convert($src, $this->tmp.'/shp/lot.shp', 'ESRI Shapefile');
        $zip = new ZipArchive;
        $zip->open($this->tmp.'/lot.zip', ZipArchive::CREATE);
        foreach (File::files($this->tmp.'/shp') as $part) {
            $zip->addFile($part->getPathname(), 'lot/'.$part->getFilename());
        }
        $zip->addFromString('../../evil.php', '<?php echo 1;');
        $zip->close();
        $shp = new UploadedFile($this->tmp.'/lot.zip', 'lot.zip', null, null, true);
        $this->actingAs($user)->post('/api/v1/gis/import', ['fail' => $shp, 'nama_set_data' => 'SHP', 'kategori' => 'lot'])
            ->assertCreated()->assertJsonPath('data.status', 'sedia')->assertJsonPath('data.format', 'shp')->assertJsonPath('data.bilangan_ciri', 2);
        $this->assertFileDoesNotExist(storage_path('evil.php'));

        // KML
        $ogr->convert($src, $this->tmp.'/lot.kml', 'KML');
        $this->actingAs($user)->post('/api/v1/gis/import', ['fail' => new UploadedFile($this->tmp.'/lot.kml', 'lot.kml', null, null, true), 'nama_set_data' => 'KML', 'kategori' => 'lot'])
            ->assertCreated()->assertJsonPath('data.status', 'sedia')->assertJsonPath('data.bilangan_ciri', 2);

        // DXF (no CRS in the file → the given SRID, here Selangor Cassini)
        $ogr->convert($src, $this->tmp.'/pelan.dxf', 'DXF', 3380);
        $dxf = $this->actingAs($user)->post('/api/v1/gis/import', ['fail' => new UploadedFile($this->tmp.'/pelan.dxf', 'pelan.dxf', null, null, true), 'nama_set_data' => 'Pelan akui', 'kategori' => 'lot', 'srid' => 3380])
            ->assertCreated();
        $dxf->assertJsonPath('data.status', 'sedia')->assertJsonPath('data.srid_asal', 3380);
        $x = (float) DB::selectOne('SELECT ST_X(ST_Centroid(ST_Collect(geom))) AS x FROM gis_ciri_import WHERE id_import = ?', [$dxf->json('data.id')])->x;
        $this->assertEqualsWithDelta(101.5555, $x, 0.002);
    }

    public function test_SRS_GIS_03_geojson_in_cassini_is_reprojected_to_wgs84(): void
    {
        // Point 101.55,3.10 expressed in EPSG:3380 metres.
        $p = DB::selectOne('SELECT ST_X(g) AS x, ST_Y(g) AS y FROM (SELECT ST_Transform(ST_SetSRID(ST_MakePoint(101.55, 3.10), 4326), 3380) AS g) t');
        $s = 100.0;
        $poly = ['type' => 'Polygon', 'coordinates' => [[[$p->x, $p->y], [$p->x + $s, $p->y], [$p->x + $s, $p->y + $s], [$p->x, $p->y + $s], [$p->x, $p->y]]]];
        $res = $this->actingAs($this->importer())->post('/api/v1/gis/import', [
            'fail' => $this->file('cassini.geojson', $this->geojson([[['n' => 1], $poly]], 3380)), 'nama_set_data' => 'Cassini', 'kategori' => 'lot',
        ])->assertCreated()->assertJsonPath('data.status', 'sedia')->assertJsonPath('data.srid_asal', 3380);

        $g = DB::selectOne('SELECT ST_XMin(geom) AS x, ST_YMin(geom) AS y, ST_Area(geom::geography) AS a FROM gis_ciri_import WHERE id_import = ?', [$res->json('data.id')]);
        $this->assertEqualsWithDelta(101.55, (float) $g->x, 0.00001);
        $this->assertEqualsWithDelta(3.10, (float) $g->y, 0.00001);
        $this->assertEqualsWithDelta(10000, (float) $g->a, 30);
    }

    public function test_SRS_GIS_22_import_requires_permission_and_unsupported_format_rejected(): void
    {
        $this->actingAs($this->user())->post('/api/v1/gis/import', ['fail' => $this->file('a.geojson', '{}'), 'nama_set_data' => 'X', 'kategori' => 'umum'])->assertForbidden();
        $this->actingAs($this->importer())->post('/api/v1/gis/import', ['fail' => $this->file('a.exe', 'MZ'), 'nama_set_data' => 'X', 'kategori' => 'umum'], ['Accept' => 'application/json'])->assertStatus(422);
    }

    private function downloaded(TestResponse $response): string
    {
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);

        return (string) file_get_contents($file->getFile()->getPathname());
    }

    public function test_SRS_GIS_26_layer_export_in_open_formats_with_reprojection_scoped_and_audited(): void
    {
        $this->lot($this->square(101.55, 3.10), ['no_lot' => 'PETALING-1']);
        $this->lot($this->square(101.65, 3.10), ['no_lot' => 'SEPANG-1', 'kod_mukim' => '100901']);
        $user = $this->user(['gis.peta.lihat', 'pro.lot.lihat', 'gis.peta.eksport'], ['1008']);

        $content = $this->downloaded($this->actingAs($user)->get('/api/v1/gis/lapisan/gis.lot/eksport?format=geojson')->assertOk());
        $this->assertStringContainsString('PETALING-1', $content);
        $this->assertStringNotContainsString('SEPANG-1', $content);
        $this->assertAudited('eksport', ['modul' => 'gis', 'nama_entiti' => 'gis_lapisan', 'id_rekod' => 'gis.lot']);

        foreach (['shp', 'gpkg', 'kml', 'csv'] as $format) {
            $this->assertNotSame('', $this->downloaded($this->actingAs($user)->get("/api/v1/gis/lapisan/gis.lot/eksport?format={$format}&srid=3380")->assertOk()), $format);
        }
        $csv = $this->downloaded($this->actingAs($user)->get('/api/v1/gis/lapisan/gis.lot/eksport?format=csv&srid=3380'));
        $this->assertMatchesRegularExpression('/MULTIPOLYGON \(\(\(-?\d{3,}/', $csv, 'Koordinat dalam meter Cassini');

        $this->actingAs($this->user())->get('/api/v1/gis/lapisan/gis.lot/eksport?format=geojson')->assertForbidden();
    }
}
