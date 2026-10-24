<?php

namespace Tests\Modules\Gis;

use App\Modules\Gis\Services\LayerService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** OGC proxy, print/export, statistics, metadata, basemaps. */
class PerkhidmatanPetaTest extends GisTestCase
{
    private const OGC = ['gis.peta.lihat', 'pro.lot.lihat', 'gis.ogc.lihat'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['gis.geoserver.url' => 'http://geoserver.test/geoserver', 'gis.geoserver.workspace' => 'sisdata']);
        Http::fake(['geoserver.test/*' => Http::response('<WMS_Capabilities><OnlineResource xlink:href="http://geoserver.test/geoserver/sisdata/wms"/></WMS_Capabilities>', 200, ['Content-Type' => 'application/xml'])]);
    }

    public function test_SRS_GIS_25_wms_getmap_proxied_with_scope_injected_as_cql_filter(): void
    {
        $user = $this->user(self::OGC, ['1008']);
        $this->actingAs($user)->get('/api/v1/gis/ogc/wms?SERVICE=WMS&REQUEST=GetMap&LAYERS=sisdata:lot&BBOX=101,3,102,4&WIDTH=256&HEIGHT=256&FORMAT=image/png&SRS=EPSG:4326')->assertOk();

        Http::assertSent(function (HttpRequest $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return str_starts_with($r->url(), 'http://geoserver.test/geoserver/sisdata/wms')
                && $q['LAYERS'] === 'lot'
                && $q['CQL_FILTER'] === "kod_daerah IN ('1008') AND klasifikasi IN ('awam','dalaman')"
                && $r->hasHeader('Authorization');
        });
    }

    public function test_SRS_GIS_25_capabilities_urls_rewritten_to_the_proxy(): void
    {
        $res = $this->actingAs($this->user(self::OGC))->get('/api/v1/gis/ogc/wms?SERVICE=WMS&REQUEST=GetCapabilities')->assertOk();
        $this->assertStringNotContainsString('geoserver.test', $res->getContent());
        $this->assertStringContainsString('/api/v1/gis/ogc/wms', $res->getContent());
    }

    public function test_SRS_GIS_32_ogc_proxy_refuses_filter_injection_unpublished_layers_and_unauthorised_users(): void
    {
        $user = $this->user(self::OGC);
        $this->actingAs($user)->getJson('/api/v1/gis/ogc/wfs?SERVICE=WFS&REQUEST=GetFeature&TYPENAMES=sisdata:lot&CQL_FILTER=1=1) OR (1=1')->assertStatus(422);
        $this->actingAs($user)->getJson('/api/v1/gis/ogc/wfs?SERVICE=WFS&REQUEST=GetFeature&TYPENAMES=sisdata:lot&VIEWPARAMS=x:1')->assertStatus(422);
        $this->assertAudited('akses_ditolak', ['modul' => 'gis', 'nama_entiti' => 'ogc', 'status_transaksi' => 'gagal']);
        $this->actingAs($user)->getJson('/api/v1/gis/ogc/wfs?SERVICE=WFS&REQUEST=Transaction&TYPENAMES=sisdata:lot')->assertStatus(422);
        $this->actingAs($user)->getJson('/api/v1/gis/ogc/wms?SERVICE=WMS&REQUEST=GetMap&LAYERS=sisdata:rahsia')->assertNotFound();
        $this->actingAs($user)->getJson('/api/v1/gis/ogc/wmts?REQUEST=GetTile')->assertNotFound();

        // No OGC permission, or OGC but no lot permission (layer-level check, audited).
        $this->actingAs($this->user(['gis.peta.lihat', 'pro.lot.lihat']))->getJson('/api/v1/gis/ogc/wms?SERVICE=WMS&REQUEST=GetMap&LAYERS=lot')->assertForbidden();
        $this->actingAs($this->user(['gis.peta.lihat', 'gis.ogc.lihat']))->getJson('/api/v1/gis/ogc/wms?SERVICE=WMS&REQUEST=GetMap&LAYERS=lot')->assertForbidden();
        $this->assertAudited('akses_ditolak', ['modul' => 'gis', 'nama_entiti' => 'gis_lapisan', 'id_rekod' => 'gis.lot']);

        // Unpublished from OGC by the administrator → gone.
        DB::table('gis_lapisan')->where('kod_lapisan', 'gis.lot')->update(['terbit_ogc' => false]);
        app(LayerService::class)->forget();
        $this->actingAs($user)->getJson('/api/v1/gis/ogc/wms?SERVICE=WMS&REQUEST=GetMap&LAYERS=lot')->assertNotFound();
        Http::assertSentCount(0);
    }

    public function test_SRS_GIS_25_wfs_getfeature_access_audited_and_sulit_levels_follow_permission(): void
    {
        $user = $this->user([...self::OGC, 'pro.sulit.lihat']);
        $this->actingAs($user)->get('/api/v1/gis/ogc/wfs?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=lot&OUTPUTFORMAT=application/json')->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), "klasifikasi IN ('awam','dalaman','sulit')"));
        $this->assertAudited('muat_turun', ['modul' => 'gis', 'nama_entiti' => 'ogc']);
        $this->actingAs($user)->getJson('/api/v1/gis/ogc')->assertOk()->assertJsonPath('lapisan', ['lot']);
    }

    public function test_SRS_GIS_33_map_printed_to_pdf_with_legend_scale_north_timestamp_and_user_and_audited(): void
    {
        $png = 'data:image/png;base64,'.base64_encode((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $user = $this->user(['gis.peta.lihat', 'gis.peta.eksport']);
        $res = $this->actingAs($user)->post('/api/v1/gis/cetak/pdf', [
            'tajuk' => 'Taburan Lot Wakaf Petaling', 'imej' => $png, 'skala' => '1:25 000', 'lapisan' => ['gis.lot'],
            'legenda' => [['nama' => 'Lot Tanah MAIS', 'entri' => [['label' => 'Wakaf', 'warna' => '#059669']]]],
            'atribut' => [['no_lot' => 'Lot 1', 'mukim' => 'Damansara']],
        ])->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertAudited('cetak', ['modul' => 'gis', 'nama_entiti' => 'peta']);

        $this->actingAs($user)->postJson('/api/v1/gis/cetak/log', ['tajuk' => 'Peta', 'format' => 'png'])->assertOk()->assertJsonPath('dijana_oleh', $user->name);
        $this->assertAudited('eksport', ['modul' => 'gis', 'nama_entiti' => 'peta']);

        $this->actingAs($user)->postJson('/api/v1/gis/cetak/pdf', ['tajuk' => 'X', 'imej' => 'data:image/png;base64,bm90IGFuIGltYWdl'])->assertStatus(422);
        $this->actingAs($this->user())->postJson('/api/v1/gis/cetak/pdf', ['tajuk' => 'X', 'imej' => $png])->assertForbidden();
    }

    public function test_SRS_GIS_34_distribution_by_daerah_mukim_and_category_in_scope(): void
    {
        $this->lot($this->square(101.55, 3.10), ['bahagian_pemilik' => 'wakaf']);
        $this->lot($this->square(101.56, 3.10), ['bahagian_pemilik' => 'baitulmal', 'kategori_guna_tanah' => 'PERTANIAN']);
        $this->lot(null, ['kod_mukim' => '100901']);
        $user = $this->user();

        $res = $this->actingAs($user)->getJson('/api/v1/gis/statistik?peringkat=daerah&geometri=1')->assertOk();
        $rows = collect($res->json('baris'))->keyBy('kod');
        $this->assertSame(2, $rows['1008']['bilangan_lot']);
        $this->assertSame(2, $rows['1008']['ada_geometri']);
        $this->assertSame('Petaling', $rows['1008']['nama']);
        $this->assertSame(1, $rows['1009']['bilangan_lot']);
        $this->assertSame('Polygon', $rows['1008']['geometri']['type']);
        $this->assertSame(3, $res->json('jumlah.bilangan_lot'));

        $scoped = $this->actingAs($this->user(GisTestCase::PENGGUNA, ['1009']))->getJson('/api/v1/gis/statistik?peringkat=mukim')->assertOk();
        $this->assertSame(['100901'], collect($scoped->json('baris'))->pluck('kod')->all());
    }

    public function test_SRS_GIS_29_layer_and_dataset_metadata_recorded_and_reported(): void
    {
        $admin = $this->specialist();
        $this->actingAs($admin)->putJson('/api/v1/gis/pentadbiran/lapisan/gis.lot/metadata', [
            'sumber_data' => 'PTG Selangor / pelan akui JUPEM', 'pemilik_data' => 'Bahagian Pengurusan Hartanah', 'tarikh_kemas_kini' => '2026-10-01',
            'status_pengesahan' => 'disahkan', 'sistem_unjuran' => 'EPSG:4326', 'tahap_ketepatan' => '±0.5 m', 'had_penggunaan' => 'Dalaman MAIS sahaja',
        ])->assertOk();

        $layer = collect($this->actingAs($admin)->getJson('/api/v1/gis/lapisan')->json('lapisan'))->firstWhere('kod', 'gis.lot');
        $this->assertSame('±0.5 m', $layer['metadata']['tahap_ketepatan']);
        $report = collect($this->actingAs($admin)->getJson('/api/v1/gis/metadata')->assertOk()->json('data'));
        $this->assertSame('Bahagian Pengurusan Hartanah', $report->firstWhere('kod', 'gis.lot')['pemilik_data']);

        $this->actingAs($this->user())->putJson('/api/v1/gis/pentadbiran/lapisan/gis.lot/metadata', ['status_pengesahan' => 'disahkan', 'sistem_unjuran' => 'x'])->assertForbidden();
    }

    public function test_SRS_GIS_08_basemaps_osm_with_attribution_and_licensed_satellite_adapter(): void
    {
        $maps = collect($this->actingAs($this->user())->getJson('/api/v1/gis/lapisan')->json('peta_asas'))->keyBy('kod');
        $this->assertTrue($maps['osm']['aktif']);
        $this->assertStringContainsString('OpenStreetMap', $maps['osm']['atribusi']);
        $this->assertFalse($maps['satelit']['aktif']);
        $this->assertTrue($maps['satelit']['perlu_lesen']);

        $this->params->values['gis.peta_asas_satelit_url'] = 'https://imej.example/{z}/{x}/{y}.jpg';
        $maps = collect($this->actingAs($this->user())->getJson('/api/v1/gis/lapisan')->json('peta_asas'))->keyBy('kod');
        $this->assertTrue($maps['satelit']['aktif']);
    }
}
