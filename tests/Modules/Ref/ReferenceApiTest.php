<?php

namespace Tests\Modules\Ref;

use App\Models\User;
use App\Modules\Ref\Models\Pbt;
use App\Modules\Ref\Services\RefVersioning;
use Illuminate\Support\Carbon;

class ReferenceApiTest extends RefTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRef();
    }

    public function test_SRS_GEN_01_ref_api_requires_login_and_the_view_permission(): void
    {
        $this->getJson('/api/v1/ref/daerah')->assertUnauthorized();

        $other = User::factory()->create(['role' => 'user']);
        $this->grant($other, ['dms.dokumen.lihat']);
        $this->actingAs($other)->getJson('/api/v1/ref/daerah')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->actingAs($other)->getJson('/api/v1/ref/daerah/1008/sempadan')->assertForbidden();
        $this->actingAs($other)->getJson('/api/v1/ref/tempat-ibadat')->assertForbidden();
    }

    public function test_SRS_DAT_05_api_lists_districts_with_their_codes(): void
    {
        $this->actingAs($this->viewer())
            ->getJson('/api/v1/ref/daerah')
            ->assertOk()
            ->assertJsonPath('meta.bilangan', 9)
            ->assertJsonPath('data.0.kod', '1001')
            ->assertJsonPath('data.0.nama', 'Klang')
            ->assertJsonPath('data.0.negeri_kod', '10')
            ->assertJsonStructure(['data' => [['kod', 'nama', 'kod_pendek', 'kod_disahkan', 'aktif', 'versi', 'sah_dari', 'sah_hingga', 'sumber']]]);
    }

    public function test_SRS_DAT_05_api_filters_mukim_and_pbt_by_district(): void
    {
        $user = $this->viewer();

        $this->actingAs($user)->getJson('/api/v1/ref/mukim?daerah=1008')
            ->assertOk()
            ->assertJsonPath('meta.bilangan', 4)
            ->assertJsonPath('data.0.kod', '100801')
            ->assertJsonPath('data.0.daerah_kod', '1008');

        $pbt = $this->actingAs($user)->getJson('/api/v1/ref/pbt?daerah=1009')->assertOk()->json('data.*.kod');
        $this->assertEqualsCanonicalizing(['MPAJ', 'MPS'], $pbt);

        $this->actingAs($user)->getJson('/api/v1/ref/mukim?daerah=Petaling')->assertUnprocessable();
        $this->actingAs($user)->getJson('/api/v1/ref/kategori-guna-tanah')->assertOk()->assertJsonPath('meta.bilangan', 4);
        $this->actingAs($user)->getJson('/api/v1/ref/tiada-senarai')->assertNotFound();
    }

    public function test_SRS_DAT_05_api_exposes_versions_and_as_of_dates(): void
    {
        Carbon::setTestNow(now()->addDays(10));
        app(RefVersioning::class)->upsert(Pbt::class, ['kod' => 'MPKJ', 'nama' => 'Majlis Bandaraya Kajang', 'jenis' => 'majlis_bandaraya']);
        $user = $this->viewer();

        $this->actingAs($user)->getJson('/api/v1/ref/pbt/MPKJ/versi')
            ->assertOk()
            ->assertJsonPath('meta.bilangan_versi', 2)
            ->assertJsonPath('data.0.nama', 'Majlis Perbandaran Kajang')
            ->assertJsonPath('data.1.nama', 'Majlis Bandaraya Kajang');

        $yesterday = now()->subDay()->toDateString();
        $names = $this->actingAs($user)->getJson('/api/v1/ref/pbt?tarikh='.$yesterday)->assertOk()->json('data.*.nama');
        $this->assertContains('Majlis Perbandaran Kajang', $names);

        $this->actingAs($user)->getJson('/api/v1/ref/pbt/TIADA/versi')
            ->assertNotFound()
            ->assertJsonPath('message', 'Kod rujukan TIADA tidak dijumpai.');
        Carbon::setTestNow();
    }

    public function test_district_boundary_is_returned_as_geojson(): void
    {
        $response = $this->actingAs($this->viewer())->getJson('/api/v1/ref/daerah/1008/sempadan')->assertOk();

        $response->assertJsonPath('type', 'Feature')
            ->assertJsonPath('properties.kod', '1008')
            ->assertJsonPath('properties.nama', 'Petaling')
            ->assertJsonPath('properties.anggaran', false)
            ->assertJsonPath('geometry.type', 'MultiPolygon');
        $this->assertStringContainsString('OpenStreetMap', (string) $response->json('properties.atribusi'));

        $this->actingAs($this->viewer())->getJson('/api/v1/ref/daerah/9999/sempadan')
            ->assertNotFound()
            ->assertJsonPath('message', 'Sempadan daerah bagi kod 9999 tidak dijumpai.');
    }

    public function test_mukim_boundaries_are_flagged_as_approximate(): void
    {
        $user = $this->viewer();

        $this->actingAs($user)->getJson('/api/v1/ref/mukim/100803/sempadan')->assertOk()->assertJsonPath('properties.anggaran', true);

        $fc = $this->actingAs($user)->getJson('/api/v1/ref/sempadan/mukim?daerah=1009')->assertOk();
        $fc->assertJsonPath('type', 'FeatureCollection')->assertJsonCount(4, 'features');
    }

    public function test_places_of_worship_are_filterable_and_carry_osm_ids(): void
    {
        $user = $this->viewer();

        $all = $this->actingAs($user)->getJson('/api/v1/ref/tempat-ibadat')->assertOk();
        $this->assertGreaterThan(500, count($all->json('features')));

        $masjid = $this->actingAs($user)->getJson('/api/v1/ref/tempat-ibadat?daerah=1008&jenis=masjid')->assertOk()->json('features');
        $this->assertNotEmpty($masjid);
        foreach ($masjid as $f) {
            $this->assertSame('1008', $f['properties']['daerah_kod']);
            $this->assertSame('masjid', $f['properties']['jenis']);
            $this->assertMatchesRegularExpression('#^(node|way|relation)/\d+$#', $f['properties']['osm']);
        }
    }
}
