<?php

namespace Tests\Modules\Pro;

use App\Modules\Pro\Models\DataSpatial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SpatialTest extends ProTestCase
{
    public function test_SRS_PRO_08_geometry_in_gdm2000_selangor_cassini_is_reprojected_to_wgs84(): void
    {
        $user = $this->officer();
        $lot = $this->makeLot(['keluasan' => 10000, 'unit_keluasan' => 'm2']);
        // A 100 m × 100 m square expressed in EPSG:3380 (GDM2000 / Selangor Grid).
        $origin = DB::selectOne('SELECT ST_X(p) AS x, ST_Y(p) AS y FROM ST_Transform(ST_SetSRID(ST_MakePoint(101.60, 3.10), 4326), 3380) AS p');
        [$x, $y] = [round($origin->x, 2), round($origin->y, 2)];
        $wkt = sprintf('POLYGON((%1$s %2$s, %3$s %2$s, %3$s %4$s, %1$s %4$s, %1$s %2$s))', $x, $y, $x + 100, $y + 100);

        $response = $this->actingAs($user)->postJson("/api/v1/pro/lot/{$lot->id}/spatial", [
            'format' => 'wkt', 'geometri' => $wkt, 'srid' => 3380, 'sumber_data' => 'juruukur', 'tarikh_perolehan_data' => '2026-01-15', 'rujukan_fail_sumber' => 'PA 123',
        ]);

        $response->assertCreated()->assertJsonPath('data.srid_asal', 3380)->assertJsonPath('data.status_pengesahan', 'belum_disahkan');
        $this->assertEqualsWithDelta(10000, $response->json('data.keluasan_dikira'), 50);
        $this->assertLessThan(1, $response->json('data.peratus_beza_keluasan'));
        $coords = $response->json('geojson.geometry.coordinates.0.0.0');
        $this->assertEqualsWithDelta(101.60, $coords[0], 0.001);
        $this->assertEqualsWithDelta(3.10, $coords[1], 0.001);
        $this->assertSame(4326, (int) DB::selectOne('SELECT ST_SRID(geometri) AS s FROM data_spatial WHERE lot_id = ?', [$lot->id])->s);
        $this->assertNotNull(DB::table('lot_tanah')->where('id', $lot->id)->value('koordinat_pusat'));
    }

    public function test_SRS_PRO_09_source_acquisition_date_uploader_and_file_are_recorded(): void
    {
        $user = $this->officer();
        $lot = $this->makeLot();
        $geojson = json_encode(['type' => 'Feature', 'properties' => [], 'geometry' => json_decode((string) DB::selectOne('SELECT ST_AsGeoJSON(ST_GeomFromText(?)) AS g', [$this->square()])->g, true)]);
        $file = UploadedFile::fake()->createWithContent('lot1234.geojson', (string) $geojson);

        $response = $this->actingAs($user)->post("/api/v1/pro/lot/{$lot->id}/spatial", [
            'format' => 'geojson', 'fail' => $file, 'sumber_data' => 'jupem', 'tarikh_perolehan_data' => '2025-12-01',
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.sumber_data', 'jupem')->assertJsonPath('data.tarikh_perolehan_data', '2025-12-01')
            ->assertJsonPath('data.id_pemuat_naik', $user->id)->assertJsonPath('data.rujukan_fail_sumber', 'lot1234.geojson');
        $doc = $response->json('data.id_dokumen_sumber');
        $this->assertSame(['lot_tanah', $lot->id, 'fail_gis'], [$this->documents->stored[$doc]['owner_type'], $this->documents->stored[$doc]['owner_id'], $this->documents->stored[$doc]['category']]);
    }

    public function test_SRS_PRO_08_kml_and_geojson_feature_collections_are_accepted(): void
    {
        $user = $this->officer();
        $lot = $this->makeLot();
        $kml = '<?xml version="1.0"?><kml xmlns="http://www.opengis.net/kml/2.2"><Placemark><Polygon><outerBoundaryIs><LinearRing><coordinates>101.6,3.1 101.601,3.1 101.601,3.101 101.6,3.101 101.6,3.1</coordinates></LinearRing></outerBoundaryIs></Polygon></Placemark></kml>';
        $this->actingAs($user)->postJson("/api/v1/pro/lot/{$lot->id}/spatial", ['format' => 'kml', 'geometri' => $kml, 'sumber_data' => 'pbt'])->assertCreated();

        $fc = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[101.7, 3.2], [101.701, 3.2], [101.701, 3.201], [101.7, 3.201], [101.7, 3.2]]]]],
            ['type' => 'Feature', 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[101.71, 3.2], [101.711, 3.2], [101.711, 3.201], [101.71, 3.201], [101.71, 3.2]]]]],
        ]];
        $response = $this->actingAs($user)->postJson("/api/v1/pro/lot/{$lot->id}/spatial", ['format' => 'geojson', 'geometri' => json_encode($fc), 'sumber_data' => 'lakaran'])->assertCreated();
        $this->assertSame('MultiPolygon', $response->json('geojson.geometry.type'));
        $this->assertCount(2, $response->json('geojson.geometry.coordinates'));

        // Two versions kept, only the latest is current.
        $this->assertSame(2, DataSpatial::query()->where('lot_id', $lot->id)->count());
        $this->assertSame(1, DataSpatial::query()->where('lot_id', $lot->id)->where('semasa', true)->count());
        $this->assertSame(2, DataSpatial::query()->where('lot_id', $lot->id)->where('semasa', true)->value('versi'));
    }

    public function test_SRS_PRO_08_invalid_geometry_wrong_srid_or_non_polygon_are_rejected_in_malay(): void
    {
        $user = $this->officer();
        $lot = $this->makeLot();

        $this->actingAs($user)->postJson("/api/v1/pro/lot/{$lot->id}/spatial", ['format' => 'wkt', 'geometri' => 'POLYGON((bukan', 'sumber_data' => 'pbt'])
            ->assertStatus(422)->assertJsonPath('errors.geometri.0', 'Geometri WKT tidak sah atau tidak boleh dibaca.');
        $this->actingAs($user)->postJson("/api/v1/pro/lot/{$lot->id}/spatial", ['format' => 'wkt', 'geometri' => 'POINT(101.6 3.1)', 'sumber_data' => 'pbt'])
            ->assertStatus(422)->assertJsonPath('errors.geometri.0', 'Geometri mesti poligon atau multipoligon.');
        $this->actingAs($user)->postJson("/api/v1/pro/lot/{$lot->id}/spatial", ['format' => 'wkt', 'geometri' => $this->square(), 'srid' => 2000, 'sumber_data' => 'pbt'])
            ->assertStatus(422)->assertJsonValidationErrors(['srid']);
        // WGS84 degrees declared as Peninsula RSO metres fall outside Malaysia → caught.
        $this->actingAs($user)->postJson("/api/v1/pro/lot/{$lot->id}/spatial", ['format' => 'wkt', 'geometri' => $this->square(), 'srid' => 3375, 'sumber_data' => 'pbt'])
            ->assertStatus(422)->assertJsonValidationErrors(['srid']);
        $this->actingAs($user)->postJson("/api/v1/pro/lot/{$lot->id}/spatial", ['format' => 'wkt', 'geometri' => $this->square(), 'sumber_data' => 'google'])
            ->assertStatus(422)->assertJsonValidationErrors(['sumber_data']);
    }

    public function test_SRS_PRO_10_overlap_marks_geometry_bertindih_and_area_mismatch_is_warned(): void
    {
        $user = $this->officer();
        $a = $this->makeLot(['no_lot' => 'Lot 1', 'keluasan' => 1, 'unit_keluasan' => 'm2']);
        $b = $this->makeLot(['no_lot' => 'Lot 2']);
        $this->addGeometry($a, $this->square(101.60, 3.10, 0.001));

        $response = $this->actingAs($user)->postJson("/api/v1/pro/lot/{$b->id}/spatial", ['format' => 'wkt', 'geometri' => $this->square(101.6005, 3.10, 0.001), 'sumber_data' => 'lakaran']);

        $response->assertCreated()->assertJsonPath('data.status_pengesahan', 'bertindih');
        $this->assertStringContainsString('Lot 1', (string) $response->json('data.catatan_pertindihan'));
        $this->assertSame('bertindih', DataSpatial::query()->where('lot_id', $a->id)->value('status_pengesahan'));
        $this->assertStringContainsString('Lot 2', (string) DataSpatial::query()->where('lot_id', $a->id)->value('catatan_pertindihan'));
        // Lot A declared 1 m² but its polygon is ~12,000 m².
        $this->assertGreaterThan(1000, (float) DataSpatial::query()->where('lot_id', $a->id)->value('peratus_beza_keluasan'));

        // Neighbours that only touch are not overlaps.
        $c = $this->makeLot(['no_lot' => 'Lot 3']);
        $this->actingAs($user)->postJson("/api/v1/pro/lot/{$c->id}/spatial", ['format' => 'wkt', 'geometri' => $this->square(101.602, 3.10, 0.001), 'sumber_data' => 'lakaran'])
            ->assertCreated()->assertJsonPath('data.status_pengesahan', 'belum_disahkan');
    }

    public function test_SRS_PRO_10_unverified_geometry_is_flagged_on_list_profile_and_map(): void
    {
        $user = $this->officer();
        $verified = $this->makeLot(['no_lot' => 'Lot 1']);
        $unverified = $this->makeLot(['no_lot' => 'Lot 2']);
        $none = $this->makeLot(['no_lot' => 'Lot 3']);
        $sid = $this->addGeometry($verified, $this->square(101.60, 3.10));
        $this->addGeometry($unverified, $this->square(101.62, 3.10));
        DataSpatial::query()->whereKey($sid)->update(['status_pengesahan' => 'disahkan_ptg']);

        $rows = collect($this->actingAs($user)->getJson('/api/v1/pro/lot')->json('data'))->keyBy('id');
        $this->assertTrue($rows[$verified->id]['spatial']['disahkan']);
        $this->assertSame('belum_disahkan', $rows[$unverified->id]['spatial']['status']);
        $this->assertFalse($rows[$unverified->id]['spatial']['disahkan']);
        $this->assertSame('tiada_geometri', $rows[$none->id]['spatial']['status']);

        $this->assertSame([$unverified->id], array_column($this->actingAs($user)->getJson('/api/v1/pro/lot?status_pengesahan=belum_disahkan')->json('data'), 'id'));
        $this->assertSame([$none->id], array_column($this->actingAs($user)->getJson('/api/v1/pro/lot?status_pengesahan=tiada_geometri')->json('data'), 'id'));

        $profile = $this->actingAs($user)->getJson("/api/v1/pro/lot/{$unverified->id}/profil")->assertOk();
        $this->assertContains('Belum disahkan', array_column($profile->json('data.bendera'), 'label'));

        $map = $this->actingAs($user)->getJson('/api/v1/pro/geojson')->assertOk();
        $features = collect($map->json('features'))->keyBy('id');
        $this->assertFalse($features[$unverified->id]['properties']['disahkan']);
        $this->assertTrue($features[$verified->id]['properties']['disahkan']);
    }

    public function test_SRS_PRO_10_authorised_officer_verifies_but_not_their_own_upload(): void
    {
        $uploader = $this->officer([...self::PEGAWAI, 'pro.spatial.lulus']);
        $verifier = $this->officer(['pro.lot.lihat', 'pro.spatial.lulus']);
        $lot = $this->makeLot();
        $sid = $this->addGeometry($lot, $this->square(), $uploader);

        $this->actingAs($this->officer())->postJson("/api/v1/pro/spatial/{$sid}/sahkan", ['status_pengesahan' => 'disahkan_ptg'])->assertForbidden();
        $this->actingAs($uploader)->postJson("/api/v1/pro/spatial/{$sid}/sahkan", ['status_pengesahan' => 'disahkan_ptg'])->assertStatus(422);
        $this->actingAs($verifier)->postJson("/api/v1/pro/spatial/{$sid}/sahkan", ['status_pengesahan' => 'disahkan_juruukur', 'catatan' => 'Sepadan pelan akui'])
            ->assertOk()->assertJsonPath('data.status_pengesahan', 'disahkan_juruukur')->assertJsonPath('data.id_pegawai_pengesah', $verifier->id);

        $this->assertAudited('sahkan', ['nama_entiti' => 'data_spatial', 'id_rekod' => $sid, 'id_pengguna' => $verifier->id]);
    }

    public function test_SRS_PRO_29_geojson_of_one_lot(): void
    {
        $user = $this->officer(['pro.lot.lihat']);
        $lot = $this->makeLot();
        $this->actingAs($user)->getJson("/api/v1/pro/lot/{$lot->id}/geojson")->assertNotFound();
        $this->addGeometry($lot, $this->square());

        $this->actingAs($user)->getJson("/api/v1/pro/lot/{$lot->id}/geojson")->assertOk()
            ->assertJsonPath('type', 'Feature')->assertJsonPath('geometry.type', 'MultiPolygon')->assertJsonPath('properties.no_lot', 'Lot 1234')
            ->assertHeader('Content-Type', 'application/geo+json');
    }
}
