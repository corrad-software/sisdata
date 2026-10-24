<?php

namespace Tests\Modules\Gis;

use Illuminate\Support\Facades\DB;

class AnalisisTest extends GisTestCase
{
    private const ANALIS = ['gis.peta.lihat', 'pro.lot.lihat', 'gis.analisis.cipta', 'gis.analisis.eksport'];

    /** ~111 m per 0.001° at this latitude. */
    private function threeLots(): array
    {
        return [
            'a' => $this->lot($this->square(101.550, 3.100), ['no_lot' => 'A']),
            'b' => $this->lot($this->square(101.552, 3.100), ['no_lot' => 'B']),   // ~110 m east of A
            'c' => $this->lot($this->square(101.580, 3.100), ['no_lot' => 'C']),   // ~3.2 km east
        ];
    }

    public function test_SRS_GIS_15_buffer_lists_features_within_user_defined_distance(): void
    {
        $lots = $this->threeLots();
        $res = $this->actingAs($this->user(self::ANALIS))->postJson('/api/v1/gis/analisis', [
            'jenis' => 'penimbal',
            'parameter' => ['sumber' => ['lot_id' => $lots['a']], 'jarak_m' => 200, 'lapisan' => ['gis.lot', 'gis.tempat_ibadat']],
        ])->assertOk();

        $ids = collect($res->json('hasil'))->where('lapisan', 'gis.lot')->pluck('id')->all();
        $this->assertSame([$lots['b']], $ids);
        $this->assertSame('Polygon', $res->json('geometri.features.0.geometry.type'));
        $this->assertSame(1, $res->json('ringkasan.mengikut_lapisan')['gis.lot']);

        $wide = $this->actingAs($this->user(self::ANALIS))->postJson('/api/v1/gis/analisis', [
            'jenis' => 'penimbal', 'parameter' => ['sumber' => ['lot_id' => $lots['a']], 'jarak_m' => 5000, 'lapisan' => ['gis.lot', 'gis.tempat_ibadat']],
        ])->assertOk();
        $this->assertEqualsCanonicalizing([$lots['b'], $lots['c']], collect($wide->json('hasil'))->where('lapisan', 'gis.lot')->pluck('id')->all());
        $this->assertContains('Masjid Damansara', collect($wide->json('hasil'))->where('lapisan', 'gis.tempat_ibadat')->pluck('label')->all());
    }

    public function test_SRS_GIS_15_buffer_source_outside_scope_is_rejected(): void
    {
        $sepang = $this->lot($this->square(101.65, 3.1), ['kod_mukim' => '100901']);
        $this->actingAs($this->user(self::ANALIS, ['1008']))->postJson('/api/v1/gis/analisis', [
            'jenis' => 'penimbal', 'parameter' => ['sumber' => ['lot_id' => $sepang], 'jarak_m' => 100],
        ])->assertStatus(422);
        $this->actingAs($this->user(['gis.peta.lihat', 'pro.lot.lihat']))->postJson('/api/v1/gis/analisis', ['jenis' => 'penimbal', 'parameter' => ['sumber' => ['lot_id' => $sepang], 'jarak_m' => 100]])->assertForbidden();
    }

    public function test_SRS_GIS_16_overlay_lists_lots_intersecting_another_layer(): void
    {
        $inside = $this->lot($this->square(101.55, 3.10));
        $straddle = $this->lot($this->square(101.5995, 3.10)); // crosses the Petaling / Sepang line
        $res = $this->actingAs($this->user(self::ANALIS))->postJson('/api/v1/gis/analisis', [
            'jenis' => 'tindihan',
            'parameter' => ['lapisan_a' => 'gis.lot', 'lapisan_b' => ['gis.sempadan_daerah']],
        ])->assertOk();

        $rows = collect($res->json('hasil'));
        $this->assertSame(['1008'], $rows->where('id', $inside)->pluck('id_ciri_b')->all());
        $this->assertEqualsCanonicalizing(['1008', '1009'], $rows->where('id', $straddle)->pluck('id_ciri_b')->all());
        $this->assertEqualsWithDelta(100, $rows->where('id', $inside)->first()['peratus'], 0.5);
        $this->assertSame(2, $res->json('ringkasan.lot_unik'));
    }

    public function test_SRS_GIS_17_spatial_query_by_drawn_polygon_boundary_and_attribute(): void
    {
        $lots = $this->threeLots();
        $this->lot($this->square(101.65, 3.10), ['kod_mukim' => '100901', 'kategori_guna_tanah' => 'PERTANIAN']);
        $user = $this->user(self::ANALIS);

        $polygon = ['type' => 'Polygon', 'coordinates' => [[[101.549, 3.099], [101.5535, 3.099], [101.5535, 3.102], [101.549, 3.102], [101.549, 3.099]]]];
        $res = $this->actingAs($user)->postJson('/api/v1/gis/analisis', ['jenis' => 'pertanyaan', 'parameter' => ['kawasan' => ['poligon' => $polygon], 'hubungan' => 'di_dalam']])->assertOk();
        $this->assertEqualsCanonicalizing([$lots['a'], $lots['b']], collect($res->json('hasil'))->pluck('id')->all());

        $mukim = $this->actingAs($user)->postJson('/api/v1/gis/analisis', ['jenis' => 'pertanyaan', 'parameter' => ['kawasan' => ['sempadan' => ['jenis' => 'mukim', 'kod' => '100901']], 'penapis' => ['kategori_guna_tanah' => 'PERTANIAN']]])->assertOk();
        $this->assertSame(1, $mukim->json('ringkasan.bilangan'));

        $radius = $this->actingAs($user)->postJson('/api/v1/gis/analisis', ['jenis' => 'pertanyaan', 'parameter' => ['kawasan' => ['titik' => ['lng' => 101.5805, 'lat' => 3.1005], 'jarak_m' => 50]]])->assertOk();
        $this->assertSame([$lots['c']], collect($radius->json('hasil'))->pluck('id')->all());

        $this->actingAs($user)->postJson('/api/v1/gis/analisis', ['jenis' => 'pertanyaan', 'parameter' => ['kawasan' => []]])->assertStatus(422);
    }

    public function test_SRS_GIS_18_area_calculation_in_geodesic_and_cassini_with_units(): void
    {
        $lot = $this->lot($this->square(101.55, 3.10, 0.001), ['keluasan' => 12000]);
        $res = $this->actingAs($this->user(self::ANALIS))->postJson('/api/v1/gis/analisis', ['jenis' => 'keluasan', 'parameter' => ['sumber' => ['lot_id' => $lot]]])->assertOk();

        $s = $res->json('ringkasan');
        $this->assertEqualsWithDelta(12250, $s['luas_m2'], 250);              // 0.001° ≈ 110.6 m × 110.9 m
        $this->assertEqualsWithDelta($s['luas_m2'], $s['luas_cassini_m2'], $s['luas_m2'] * 0.01);
        $this->assertEqualsWithDelta($s['luas_m2'] / 10000, $s['luas_hektar'], 0.0001);
        $this->assertEqualsWithDelta(443, $s['perimeter_m'], 5);
        $this->assertSame(12000.0, (float) $s['keluasan_diisytihar_m2']);
        $this->assertStringContainsString('EPSG:3380', $s['unjuran']['satah']);
    }

    public function test_SRS_GIS_19_proximity_nearest_and_lots_within_distance_of_mosque(): void
    {
        $near = $this->lot($this->square(101.5525, 3.1003, 0.0005), ['no_lot' => 'Dekat masjid']);  // ~ 60 m from Masjid Damansara
        $far = $this->lot($this->square(101.59, 3.15), ['no_lot' => 'Jauh']);
        $user = $this->user(self::ANALIS);

        $within = $this->actingAs($user)->postJson('/api/v1/gis/analisis', ['jenis' => 'kedekatan', 'parameter' => [
            'mod' => 'dalam_jarak', 'lapisan_sumber' => 'gis.lot', 'lapisan_sasaran' => 'gis.tempat_ibadat', 'jarak_m' => 500,
        ]])->assertOk();
        $this->assertSame([$near], collect($within->json('hasil'))->pluck('id')->all());
        $this->assertSame('Masjid Damansara', $within->json('hasil.0.sasaran_terdekat'));
        $this->assertLessThan(500, $within->json('hasil.0.jarak_m'));

        $nearest = $this->actingAs($user)->postJson('/api/v1/gis/analisis', ['jenis' => 'kedekatan', 'parameter' => [
            'mod' => 'terdekat', 'sumber' => ['lot_id' => $far], 'lapisan_sasaran' => 'gis.tempat_ibadat', 'bilangan' => 2,
        ]])->assertOk();
        $this->assertCount(2, $nearest->json('hasil'));
        $this->assertLessThanOrEqual($nearest->json('hasil.1.jarak_m'), $nearest->json('hasil.0.jarak_m'));
    }

    public function test_SRS_GIS_20_scenario_saved_shared_by_permission_and_exported(): void
    {
        $lots = $this->threeLots();
        $owner = $this->user(self::ANALIS);
        $colleague = $this->user(self::ANALIS);
        $stranger = $this->user(self::ANALIS);
        $params = ['jenis' => 'penimbal', 'parameter' => ['sumber' => ['lot_id' => $lots['a']], 'jarak_m' => 200]];

        $id = $this->actingAs($owner)->postJson('/api/v1/gis/senario', $params + ['nama' => 'Lot sekitar A', 'kebenaran_kongsi' => 'pengguna', 'dikongsi_dengan' => [$colleague->id]])
            ->assertCreated()->assertJsonPath('data.ringkasan.bilangan', 1)->json('data.id');

        $this->actingAs($colleague)->getJson("/api/v1/gis/senario/{$id}")->assertOk()->assertJsonPath('hasil.hasil.0.id', $lots['b']);
        $this->actingAs($stranger)->getJson("/api/v1/gis/senario/{$id}")->assertNotFound();
        $this->assertSame([$id], collect($this->actingAs($colleague)->getJson('/api/v1/gis/senario')->json('data'))->pluck('id')->all());
        $this->actingAs($colleague)->deleteJson("/api/v1/gis/senario/{$id}")->assertForbidden();

        // Shared scenario re-runs in the viewer's own scope: a Sepang-only colleague sees nothing of Petaling.
        $sepang = $this->user(self::ANALIS, ['1009']);
        DB::table('gis_senario_analisis')->where('id', $id)->update(['kebenaran_kongsi' => 'semua']);
        $this->actingAs($sepang)->getJson("/api/v1/gis/senario/{$id}")->assertStatus(422);

        $csv = $this->actingAs($owner)->post('/api/v1/gis/analisis/eksport', $params + ['format' => 'csv'])->assertOk();
        $this->assertStringContainsString($lots['b'], $csv->streamedContent());
        $this->assertAudited('eksport', ['modul' => 'gis', 'nama_entiti' => 'gis_senario_analisis']);
        $this->actingAs($this->user(['gis.peta.lihat', 'pro.lot.lihat', 'gis.analisis.cipta']))->post('/api/v1/gis/analisis/eksport', $params + ['format' => 'csv'])->assertForbidden();
        $this->actingAs($owner)->post('/api/v1/gis/analisis/eksport', $params + ['format' => 'xlsx'])->assertOk();
    }

    public function test_SRS_GIS_21_clustering_groups_nearby_lots(): void
    {
        foreach ([0, 1, 2, 3] as $i) {
            $this->lot($this->square(101.550 + $i * 0.002, 3.100));
        }
        foreach ([0, 1, 2] as $i) {
            $this->lot($this->square(101.590 + $i * 0.002, 3.150));
        }
        $this->lot($this->square(101.520, 3.180)); // noise

        $res = $this->actingAs($this->user(self::ANALIS))->postJson('/api/v1/gis/analisis', ['jenis' => 'kluster', 'parameter' => ['jarak_m' => 400, 'minimum' => 3]])->assertOk();
        $this->assertSame(2, $res->json('ringkasan.bilangan_kluster'));
        $this->assertSame([4, 3], collect($res->json('hasil'))->pluck('bilangan')->all());
    }

    public function test_SRS_GIS_20_saved_map_views_are_personal_unless_shared(): void
    {
        $me = $this->user();
        $other = $this->user();
        $id = $this->actingAs($me)->postJson('/api/v1/gis/paparan', ['nama' => 'Petaling', 'lapisan' => ['gis.lot'], 'pusat' => [101.55, 3.1], 'zum' => 13])->assertCreated()->json('data.id');
        $this->assertCount(1, $this->actingAs($me)->getJson('/api/v1/gis/paparan')->json('data'));
        $this->assertCount(0, $this->actingAs($other)->getJson('/api/v1/gis/paparan')->json('data'));
        $this->actingAs($other)->deleteJson("/api/v1/gis/paparan/{$id}")->assertNotFound();
        $this->actingAs($me)->deleteJson("/api/v1/gis/paparan/{$id}")->assertOk();
    }
}
