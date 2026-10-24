<?php

namespace Tests\Modules\Gis;

use App\Models\User;
use App\Modules\Gis\Contracts\MapLayer;
use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Gis\Services\LayerRegistry;
use App\Modules\Gis\Services\LayerService;
use App\Modules\Gis\Support\Palet;
use Illuminate\Support\Facades\DB;

class LayersTest extends GisTestCase
{
    public function test_SRS_GIS_01_lot_layer_shows_lots_with_geometry_category_and_status(): void
    {
        $lot = $this->lot($this->square(101.55, 3.10), ['no_lot' => 'Lot 101', 'kategori_guna_tanah' => 'PERTANIAN']);
        $this->lot(null, ['no_lot' => 'Lot tanpa geometri']);

        $res = $this->actingAs($this->user())->getJson('/api/v1/gis/lapisan/gis.lot/ciri?bbox=101.5,3.0,101.7,3.2')->assertOk();

        $this->assertCount(1, $res->json('features'));
        $props = $res->json('features.0.properties');
        $this->assertSame($lot, $props['id']);
        $this->assertSame('Lot 101', $props['no_lot']);
        $this->assertSame('100803', $props['kod_mukim']);
        $this->assertSame('1008', $props['kod_daerah']);
        $this->assertSame('PERTANIAN', $props['kategori_guna_tanah']);
        $this->assertSame('aktif', $props['status_lot']);
        $this->assertSame('MultiPolygon', $res->json('features.0.geometry.type'));
    }

    public function test_SRS_GIS_09_feature_attributes_carry_deep_link_to_360_profile(): void
    {
        $lot = $this->lot($this->square(101.55, 3.10));

        $res = $this->actingAs($this->user())->getJson('/api/v1/gis/lapisan/gis.lot/ciri')->assertOk();

        $this->assertSame('/admin/pro/lot/'.$lot, $res->json('features.0.properties.pautan'));
        $this->assertNotEmpty($res->json('features.0.properties.label'));
    }

    public function test_SRS_GIS_08_features_filtered_by_bbox_and_simplified_by_zoom(): void
    {
        // A many-vertex circle polygon: at low zoom it must come back with fewer vertices.
        $id = $this->lot();
        $wkt = DB::selectOne('SELECT ST_AsText(ST_Multi(ST_Buffer(ST_SetSRID(ST_MakePoint(101.55, 3.1), 4326), 0.01, 64))) AS w')->w;
        $this->geometry($id, $wkt);
        $this->lot($this->square(101.65, 3.15));
        $user = $this->user();

        $inside = $this->actingAs($user)->getJson('/api/v1/gis/lapisan/gis.lot/ciri?bbox=101.5,3.0,101.6,3.2')->assertOk();
        $this->assertCount(1, $inside->json('features'));

        $full = count($this->actingAs($user)->getJson('/api/v1/gis/lapisan/gis.lot/ciri?bbox=101.5,3.0,101.6,3.2&zoom=18')->json('features.0.geometry.coordinates.0.0'));
        $coarse = count($this->actingAs($user)->getJson('/api/v1/gis/lapisan/gis.lot/ciri?bbox=101.5,3.0,101.6,3.2&zoom=8')->json('features.0.geometry.coordinates.0.0'));
        $this->assertGreaterThan($coarse, $full);

        $this->actingAs($user)->getJson('/api/v1/gis/lapisan/gis.lot/ciri?bbox=1,2,3')->assertStatus(422);
    }

    public function test_SRS_GIS_08_vector_tiles_are_served_via_st_asmvt_and_scoped(): void
    {
        $this->lot($this->square(101.55, 3.10), ['no_lot' => 'LOTPETALING']);
        $this->lot($this->square(101.65, 3.10), ['no_lot' => 'LOTSEPANG', 'kod_mukim' => '100901']);
        [$x, $y] = $this->tileFor(101.6, 3.1, 8);

        $all = $this->actingAs($this->user())->get("/api/v1/gis/tiles/gis.lot/8/{$x}/{$y}.pbf")->assertOk();
        $this->assertSame('application/vnd.mapbox-vector-tile', $all->headers->get('Content-Type'));
        $this->assertStringContainsString('LOTPETALING', $all->getContent());
        $this->assertStringContainsString('LOTSEPANG', $all->getContent());

        // A Petaling officer's tile never contains Sepang lots (SRS-RBC-08).
        $scoped = $this->actingAs($this->user(self::PENGGUNA, ['1008']))->get("/api/v1/gis/tiles/gis.lot/8/{$x}/{$y}.pbf")->assertOk();
        $this->assertStringContainsString('LOTPETALING', $scoped->getContent());
        $this->assertStringNotContainsString('LOTSEPANG', $scoped->getContent());

        $this->actingAs($this->user())->get('/api/v1/gis/tiles/gis.lot/3/99/0.pbf')->assertNotFound();
    }

    public function test_SRS_GIS_08_tiles_are_cached_per_scope_signature(): void
    {
        $this->params->values['gis.tile_cache_saat'] = 300;
        $this->lot($this->square(101.55, 3.10), ['no_lot' => 'LOTPETALING']);
        $this->lot($this->square(101.65, 3.10), ['no_lot' => 'LOTSEPANG', 'kod_mukim' => '100901']);
        [$x, $y] = $this->tileFor(101.6, 3.1, 8);

        $this->actingAs($this->user())->get("/api/v1/gis/tiles/gis.lot/8/{$x}/{$y}.pbf")->assertOk();
        // Same tile, narrower scope: must not get the cached state-wide tile.
        $scoped = $this->actingAs($this->user(self::PENGGUNA, ['1009']))->get("/api/v1/gis/tiles/gis.lot/8/{$x}/{$y}.pbf")->assertOk();
        $this->assertStringNotContainsString('LOTPETALING', $scoped->getContent());
        $this->assertStringContainsString('LOTSEPANG', $scoped->getContent());
    }

    public function test_SRS_GIS_32_out_of_scope_district_and_classification_hidden_from_map_services(): void
    {
        $this->lot($this->square(101.55, 3.10), ['no_lot' => 'Dalaman Petaling']);
        $this->lot($this->square(101.65, 3.10), ['no_lot' => 'Sepang', 'kod_mukim' => '100901']);
        $this->lot($this->square(101.56, 3.12), ['no_lot' => 'Sulit Petaling', 'klasifikasi' => 'sulit']);

        $labels = fn (User $u) => collect($this->actingAs($u)->getJson('/api/v1/gis/lapisan/gis.lot/ciri')->assertOk()->json('features'))->pluck('properties.no_lot')->sort()->values()->all();

        $this->assertSame(['Dalaman Petaling'], $labels($this->user(self::PENGGUNA, ['1008'])));
        $this->assertSame(['Dalaman Petaling', 'Sepang'], $labels($this->user()));
        $this->assertSame(['Dalaman Petaling', 'Sepang', 'Sulit Petaling'], $labels($this->user([...self::PENGGUNA, 'pro.sulit.lihat'])));
    }

    public function test_SRS_GIS_32_sulit_layer_refused_on_direct_request_and_attempt_audited(): void
    {
        $this->lot($this->square(101.55, 3.10));
        // hkm.hak_milik.lihat but no gis.sulit.lihat: the Sulit title layer is refused at service level.
        $user = $this->user([...self::PENGGUNA, 'hkm.hak_milik.lihat']);

        $layers = collect($this->actingAs($user)->getJson('/api/v1/gis/lapisan')->assertOk()->json('lapisan'))->pluck('kod');
        $this->assertNotContains('gis.hak_milik', $layers);

        $this->actingAs($user)->getJson('/api/v1/gis/lapisan/gis.hak_milik/ciri')->assertForbidden();
        $this->actingAs($user)->get('/api/v1/gis/tiles/gis.hak_milik/10/812/503.pbf')->assertForbidden();
        $this->assertAudited('akses_ditolak', ['modul' => 'gis', 'nama_entiti' => 'gis_lapisan', 'id_rekod' => 'gis.hak_milik', 'status_transaksi' => 'gagal']);

        $allowed = $this->user([...self::PENGGUNA, 'hkm.hak_milik.lihat', 'gis.sulit.lihat']);
        $this->actingAs($allowed)->getJson('/api/v1/gis/lapisan/gis.hak_milik/ciri')->assertOk();
    }

    public function test_SRS_GIS_32_user_without_map_permission_is_refused(): void
    {
        $this->actingAs($this->user(['pro.lot.lihat']))->getJson('/api/v1/gis/lapisan')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/gis/lapisan')->assertUnauthorized();
        // Map permission but not the lot permission: lot layer absent and refused.
        $u = $this->user(['gis.peta.lihat']);
        $this->assertNotContains('gis.lot', collect($this->actingAs($u)->getJson('/api/v1/gis/lapisan')->json('lapisan'))->pluck('kod'));
        $this->actingAs($u)->getJson('/api/v1/gis/lapisan/gis.lot/ciri')->assertForbidden();
    }

    public function test_SRS_GIS_11_ten_mandatory_layers_listed_with_legends_and_filled_by_module_layers(): void
    {
        $res = $this->actingAs($this->specialist())->getJson('/api/v1/gis/lapisan')->assertOk();
        $slots = collect($res->json('wajib'));
        $this->assertCount(10, $slots);
        foreach ($slots as $slot) {
            $this->assertNotEmpty($slot['legenda'], $slot['nama']);
        }
        $this->assertSame(['gis.hak_milik'], $slots->firstWhere('kod', 'hak_milik')['lapisan']);
        // MOD-KES (G4) now fills its slot with its own layer.
        $this->assertSame('tersedia', $slots->firstWhere('kod', 'kes_mahkamah')['status']);
        $this->assertSame(['kes.kes_mahkamah'], $slots->firstWhere('kod', 'kes_mahkamah')['lapisan']);

        // A module registers its layer through the MapLayers contract → slot 4 is filled.
        app(MapLayers::class)->register(FakePajakanLayer::class);
        $slots = collect($this->actingAs($this->specialist())->getJson('/api/v1/gis/lapisan')->json('wajib'));
        $this->assertSame(['pjk.pajakan'], $slots->firstWhere('kod', 'status_pajakan')['lapisan']);
        $this->assertSame('tersedia', $slots->firstWhere('kod', 'status_pajakan')['status']);
    }

    public function test_SRS_GIS_11_registered_module_layer_is_served_as_geojson_with_its_own_permission(): void
    {
        app(MapLayers::class)->register(FakePajakanLayer::class);

        $this->actingAs($this->user([...self::PENGGUNA, 'pjk.pajakan.lihat']))->getJson('/api/v1/gis/lapisan/pjk.pajakan/ciri')
            ->assertOk()->assertJsonPath('features.0.properties.status', 'aktif');
        $this->actingAs($this->user())->getJson('/api/v1/gis/lapisan/pjk.pajakan/ciri')->assertForbidden();
        // Not a vector-tile layer.
        $this->actingAs($this->user([...self::PENGGUNA, 'pjk.pajakan.lihat']))->get('/api/v1/gis/tiles/pjk.pajakan/10/812/503.pbf')->assertNotFound();
    }

    public function test_SRS_GIS_12_layer_order_visibility_opacity_configurable_but_classification_never_lowered(): void
    {
        $admin = $this->specialist();
        $this->actingAs($admin)->putJson('/api/v1/gis/pentadbiran/lapisan/gis.lot', ['urutan_paparan' => 5, 'ketelusan_lalai' => 0.3, 'papar_lalai' => false])->assertOk()
            ->assertJsonPath('data.urutan', 5)->assertJsonPath('data.ketelusan', 0.3);
        $this->actingAs($admin)->putJson('/api/v1/gis/pentadbiran/lapisan/gis.hak_milik', ['klasifikasi_keselamatan' => 'dalaman'])->assertStatus(422);

        // Raising a layer to Sulit hides it from users without gis.sulit.lihat.
        $this->actingAs($admin)->putJson('/api/v1/gis/pentadbiran/lapisan/gis.tempat_ibadat', ['klasifikasi_keselamatan' => 'sulit'])->assertOk();
        app(LayerService::class)->forget();
        $this->actingAs($this->user())->getJson('/api/v1/gis/lapisan/gis.tempat_ibadat/ciri')->assertForbidden();

        $this->actingAs($this->user())->putJson('/api/v1/gis/pentadbiran/lapisan/gis.lot', ['urutan_paparan' => 1])->assertForbidden();
    }

    public function test_SRS_GIS_12_legends_match_the_shared_frontend_legend_files(): void
    {
        $legend = (string) file_get_contents(base_path('frontend/lib/legend.ts'));
        $channels = (string) file_get_contents(base_path('frontend/lib/legend-pemerolehan.ts'));
        $check = function (string $source, string $const, array $palette) {
            preg_match('/'.$const.'[^=]*=\s*\{(.*?)\n\};/s', $source, $m);
            $this->assertNotEmpty($m, $const);
            foreach ($palette as $code => [$colour]) {
                $this->assertMatchesRegularExpression('/'.preg_quote((string) $code, '/').":\s*\{[^}]*warna:\s*'".preg_quote($colour, '/')."'/i", $m[1], "{$const}.{$code}");
            }
        };
        $check($legend, 'WARNA_JENIS_HAK_MILIK', Palet::JENIS_HAK_MILIK);
        $check($legend, 'WARNA_KATEGORI_GUNA_TANAH', Palet::KATEGORI_GUNA_TANAH);
        $check($legend, 'WARNA_BAHAGIAN_PEMILIK', Palet::BAHAGIAN_PEMILIK);
        $check($channels, 'LEGEND_SALURAN', Palet::SALURAN);
    }

    public function test_SRS_GIS_13_land_status_layer_classifies_lots(): void
    {
        $wakaf = $this->lot($this->square(101.55, 3.10), ['bahagian_pemilik' => 'wakaf']);
        $kosong = $this->lot($this->square(101.56, 3.10), ['bahagian_pemilik' => 'hartanah']);
        $potensi = $this->lot($this->square(101.57, 3.10), ['bahagian_pemilik' => 'hartanah', 'tag' => json_encode(['berpotensi_dibangunkan'])]);
        $bermasalah = $this->lot(null, ['bahagian_pemilik' => 'hartanah']);
        $this->geometry($bermasalah, $this->square(101.58, 3.10), 'bertindih');

        $status = collect($this->actingAs($this->user())->getJson('/api/v1/gis/lapisan/gis.status_tanah/ciri')->assertOk()->json('features'))
            ->mapWithKeys(fn ($f) => [$f['properties']['id'] => $f['properties']['status_tanah']]);

        $this->assertSame('wakaf', $status[$wakaf]);
        $this->assertSame('kosong', $status[$kosong]);
        $this->assertSame('berpotensi', $status[$potensi]);
        $this->assertSame('bermasalah', $status[$bermasalah]);
    }

    public function test_SRS_GIS_14_boundary_and_public_facility_layers(): void
    {
        $user = $this->user();
        $daerah = $this->actingAs($user)->getJson('/api/v1/gis/lapisan/gis.sempadan_daerah/ciri')->assertOk();
        $this->assertEqualsCanonicalizing(['1008', '1009'], collect($daerah->json('features'))->pluck('properties.kod')->all());
        $mosques = $this->actingAs($user)->getJson('/api/v1/gis/lapisan/gis.tempat_ibadat/ciri?penapis[jenis]=masjid')->assertOk();
        $this->assertSame(['Masjid Damansara'], collect($mosques->json('features'))->pluck('properties.label')->all());
    }

    public function test_SRS_GIS_11_physical_asset_layer_points_crud_scoped_and_audited(): void
    {
        $officer = $this->specialist(['1008']);
        $id = $this->actingAs($officer)->postJson('/api/v1/gis/aset', ['nama' => 'Dewan Wakaf', 'kategori' => 'bangunan', 'lng' => 101.555, 'lat' => 3.105])
            ->assertCreated()->json('data.id');
        $this->assertSame('1008', DB::table('gis_aset_titik')->where('id', $id)->value('kod_daerah'));

        $this->actingAs($officer)->putJson("/api/v1/gis/aset/{$id}", ['status_keadaan' => 'kritikal'])->assertOk();
        $this->assertAudited('kemas_kini', ['modul' => 'gis', 'nama_entiti' => 'gis_aset_titik'])->assertFieldChanged('status_keadaan', 'baik', 'kritikal');

        $features = $this->actingAs($officer)->getJson('/api/v1/gis/lapisan/gis.aset_fizikal/ciri')->assertOk()->json('features');
        $this->assertSame('kritikal', $features[0]['properties']['status_keadaan']);
        // Sepang officer neither sees nor edits it.
        $sepang = $this->specialist(['1009']);
        $this->assertSame([], $this->actingAs($sepang)->getJson('/api/v1/gis/lapisan/gis.aset_fizikal/ciri')->json('features'));
        $this->actingAs($sepang)->putJson("/api/v1/gis/aset/{$id}", ['status_keadaan' => 'baik'])->assertNotFound();
    }

    public function test_SRS_GIS_08_location_search_by_lot_number_mukim_and_mosque(): void
    {
        $lot = $this->lot($this->square(101.55, 3.10), ['no_lot' => 'Lot 4455']);
        $user = $this->user();

        $hits = collect($this->actingAs($user)->getJson('/api/v1/gis/cari?q=4455')->assertOk()->json('data'));
        $this->assertSame($lot, $hits->firstWhere('jenis', 'lot')['id']);
        $this->assertEqualsWithDelta(101.5505, $hits->firstWhere('jenis', 'lot')['lng'], 0.001);

        $this->assertSame('100803', collect($this->actingAs($user)->getJson('/api/v1/gis/cari?q=Damansara')->json('data'))->firstWhere('jenis', 'mukim')['id']);
        $this->assertNotNull(collect($this->actingAs($user)->getJson('/api/v1/gis/cari?q=Surau')->json('data'))->firstWhere('jenis', 'tempat_ibadat'));
        // Out-of-scope lots are not found.
        $this->assertNull(collect($this->actingAs($this->user(self::PENGGUNA, ['1009']))->getJson('/api/v1/gis/cari?q=4455')->json('data'))->firstWhere('jenis', 'lot'));
    }

    public function test_SRS_GIS_03_geometry_master_is_4326_with_gist_indexes(): void
    {
        $this->assertSame(4326, (int) DB::selectOne("SELECT Find_SRID('public', 'data_spatial', 'geometri') AS s")->s);
        foreach (['gis_ciri_import' => 'geom', 'gis_ralat_kualiti' => 'geom', 'gis_aset_titik' => 'geom'] as $table => $column) {
            $this->assertSame(4326, (int) DB::selectOne('SELECT Find_SRID(?, ?, ?) AS s', ['public', $table, $column])->s);
            $this->assertNotNull(DB::selectOne("SELECT 1 AS x FROM pg_indexes WHERE tablename = ? AND indexdef ILIKE '%gist%'", [$table]), $table);
        }
        $this->assertInstanceOf(LayerRegistry::class, app(MapLayers::class));
        $this->assertSame(app(MapLayers::class), app(LayerRegistry::class));
    }

    /** @return array{0: int, 1: int} slippy tile containing lng/lat */
    private function tileFor(float $lng, float $lat, int $z): array
    {
        $n = 2 ** $z;
        $x = (int) floor(($lng + 180) / 360 * $n);
        $y = (int) floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n);

        return [$x, $y];
    }
}

/** Stand-in for a MOD-PJK layer registered through the contract. */
class FakePajakanLayer implements MapLayer
{
    public function key(): string
    {
        return 'pjk.pajakan';
    }

    public function label(): string
    {
        return 'Status Pajakan';
    }

    public function group(): string
    {
        return 'operasi';
    }

    public function geometryType(): string
    {
        return 'point';
    }

    public function permission(): string
    {
        return 'pjk.pajakan.lihat';
    }

    public function legend(): array
    {
        return ['medan' => 'status', 'entri' => [['nilai' => 'aktif', 'label' => 'Aktif', 'warna' => '#16A34A']]];
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        return ['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [101.555, 3.105]],
            'properties' => ['id' => 'k-1', 'label' => 'Kontrak 1', 'pautan' => '/admin/pjk/kontrak/k-1', 'status' => 'aktif'],
        ]]];
    }
}
