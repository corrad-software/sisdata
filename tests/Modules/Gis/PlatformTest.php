<?php

namespace Tests\Modules\Gis;

use App\Modules\Gis\Services\Ogr;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** Platform-level GIS requirements: components (02), measurement (10), government sources (23). */
class PlatformTest extends GisTestCase
{
    public function test_SRS_GIS_02_platform_components_layer_management_search_attributes_filter_and_analysis(): void
    {
        $this->lot($this->square(101.55, 3.10), ['no_lot' => 'Lot 9001', 'bahagian_pemilik' => 'wakaf']);
        $this->lot($this->square(101.56, 3.10), ['no_lot' => 'Lot 9002', 'bahagian_pemilik' => 'baitulmal']);
        $user = $this->specialist();

        // Layer management + legend catalogue.
        $catalogue = $this->actingAs($user)->getJson('/api/v1/gis/lapisan')->assertOk();
        $this->assertGreaterThanOrEqual(13, count($catalogue->json('lapisan')));
        // Attribute filter ("tapisan maklumat").
        $filtered = $this->actingAs($user)->getJson('/api/v1/gis/lapisan/gis.lot/ciri?penapis[bahagian_pemilik]=wakaf')->assertOk();
        $this->assertSame(['Lot 9001'], collect($filtered->json('features'))->pluck('properties.no_lot')->all());
        // Location search, attribute display, analysis and spatial update path (import) are reachable.
        $this->actingAs($user)->getJson('/api/v1/gis/cari?q=9002')->assertOk()->assertJsonPath('data.0.jenis', 'lot');
        $this->actingAs($user)->getJson('/api/v1/gis/pentadbiran/lapisan')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/gis/import')->assertOk();
    }

    public function test_SRS_GIS_10_measure_drawn_polygon_area_and_perimeter(): void
    {
        $polygon = ['type' => 'Polygon', 'coordinates' => [[[101.55, 3.10], [101.551, 3.10], [101.551, 3.101], [101.55, 3.101], [101.55, 3.10]]]];
        $res = $this->actingAs($this->user(['gis.peta.lihat', 'pro.lot.lihat', 'gis.analisis.cipta']))
            ->postJson('/api/v1/gis/analisis', ['jenis' => 'keluasan', 'parameter' => ['sumber' => ['geometri' => $polygon]]])->assertOk();
        $this->assertEqualsWithDelta(12250, $res->json('ringkasan.luas_m2'), 250);
        $this->assertEqualsWithDelta(443, $res->json('ringkasan.perimeter_m'), 5);
        $this->assertNull($res->json('ringkasan.keluasan_diisytihar_m2'));
    }

    public function test_SRS_GIS_23_government_source_dataset_imported_with_provenance_and_usable_in_overlay(): void
    {
        if (! app(Ogr::class)->available()) {
            $this->markTestSkipped('GDAL ogr2ogr tidak dipasang.');
        }
        $lot = $this->lot($this->square(101.55, 3.10));
        $dir = storage_path('framework/testing/gis-'.Str::random(6));
        File::ensureDirectoryExists($dir);
        $fc = ['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'properties' => ['nama' => 'Zon Banjir JPS'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[101.549, 3.099], [101.553, 3.099], [101.553, 3.103], [101.549, 3.103], [101.549, 3.099]]]],
        ]]];
        file_put_contents($dir.'/banjir.geojson', (string) json_encode($fc));
        $user = $this->user(['gis.peta.lihat', 'pro.lot.lihat', 'gis.import.lihat', 'gis.import.cipta', 'gis.analisis.cipta']);

        $id = $this->actingAs($user)->post('/api/v1/gis/import', [
            'fail' => new UploadedFile($dir.'/banjir.geojson', 'banjir.geojson', null, null, true), 'nama_set_data' => 'Kawasan banjir (MyGDI)', 'kategori' => 'risiko',
        ])->assertCreated()->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/gis/import/{$id}/terima", ['sumber_data' => 'MyGDI / JPS', 'pemilik_data' => 'JPS Selangor', 'had_penggunaan' => 'Dalaman MAIS, tertakluk perjanjian perkongsian data'])->assertOk();
        File::deleteDirectory($dir);

        $overlay = $this->actingAs($user)->postJson('/api/v1/gis/analisis', ['jenis' => 'tindihan', 'parameter' => [
            'lapisan_a' => 'gis.lot', 'lapisan_b' => ['gis.set_data'], 'penapis' => ['gis.set_data' => ['id_import' => $id]],
        ]])->assertOk();
        $this->assertSame([$lot], collect($overlay->json('hasil'))->pluck('id')->all());
        $this->assertSame('Zon Banjir JPS', $overlay->json('hasil.0.label_b'));

        $meta = collect($this->actingAs($user)->getJson('/api/v1/gis/metadata')->json('data'))->firstWhere('kod', $id);
        $this->assertSame('MyGDI / JPS', $meta['sumber_data']);
        $this->assertSame('JPS Selangor', $meta['pemilik_data']);
    }
}
