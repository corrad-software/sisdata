<?php

namespace Tests\Modules\Adm;

use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Adm\Models\IndeksCarian;
use App\Modules\Adm\Models\KodRujukan;
use Tests\Modules\Adm\Fakes\BrokenSearchProvider;
use Tests\Modules\Adm\Fakes\CukaiSearchProvider;
use Tests\Modules\Adm\Fakes\LotTanahSearchProvider;

class CarianTest extends AdmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $providers = $this->app->make(SearchProviders::class);
        $providers->register(LotTanahSearchProvider::class);
        $providers->register(CukaiSearchProvider::class);
        $providers->register(BrokenSearchProvider::class);
        LotTanahSearchProvider::$reindexed = 0;
    }

    public function test_SRS_ADM_21_global_search_by_lot_number_returns_records_from_several_modules(): void
    {
        $res = $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/carian?q=PT 1234')->assertOk();

        $entities = array_column($res->json('data'), 'entiti');
        $this->assertContains('lot_tanah', $entities);
        $this->assertContains('cukai', $entities);
        $this->assertNotContains('rosak', $entities); // a failing provider doesn't break the search
        $this->assertSame(3, $res->json('jumlah'));
    }

    public function test_SRS_ADM_21_results_outside_the_users_scope_are_not_returned(): void
    {
        $officer = $this->userWith([]);
        $this->access->daerah[$officer->id] = ['PETALING'];

        $lots = collect($this->actingAs($officer)->getJson('/api/v1/adm/carian?q=PT 1234&entiti[]=lot_tanah')->json('data.0.hasil'));
        $this->assertSame(['L1'], $lots->pluck('id')->all());

        // ADM's own provider requires its permission.
        KodRujukan::query()->create(['jenis_kod' => 'daerah', 'kod' => 'PT', 'keterangan' => 'PT 1234 kod', 'tarikh_mula' => now()]);
        $this->assertNotContains('kod_rujukan', array_column($this->actingAs($officer)->getJson('/api/v1/adm/carian?q=PT 1234')->json('data'), 'entiti'));
        $this->assertContains('kod_rujukan', array_column($this->actingAs($this->admin())->getJson('/api/v1/adm/carian?q=PT 1234')->json('data'), 'entiti'));

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/adm/carian?q=PT')->assertUnauthorized();
    }

    public function test_SRS_ADM_21_detailed_search_by_filters_uses_filterable_providers_only(): void
    {
        $res = $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/carian?tapis[daerah]=KLANG')->assertOk();

        $this->assertSame(['lot_tanah'], array_column($res->json('data'), 'entiti'));
        $this->assertSame(['L2'], array_column($res->json('data.0.hasil'), 'id'));
    }

    public function test_SRS_ADM_20_weights_synonyms_fields_and_enable_switch_are_admin_configurable(): void
    {
        $admin = $this->admin();
        $settings = collect($this->actingAs($admin)->getJson('/api/v1/adm/carian/tetapan')->assertOk()->json('data'))->keyBy('entiti');
        $this->assertTrue($settings['lot_tanah']['berdaftar']);

        // Weight: cukai first.
        $this->actingAs($admin)->putJson('/api/v1/adm/carian/tetapan/'.$settings['cukai']['id'], ['pemberat' => 5])->assertOk();
        $this->assertSame('cukai', $this->actingAs($admin)->getJson('/api/v1/adm/carian?q=PT 1234')->json('data.0.entiti'));

        // Synonym: "bil" finds "BIL-2026-01" via the synonym "bil-2026".
        $this->actingAs($admin)->postJson('/api/v1/adm/carian/sinonim', ['istilah' => 'bil cukai', 'sinonim' => ['BIL-2026-01']])->assertCreated();
        $res = $this->actingAs($admin)->getJson('/api/v1/adm/carian?q=bil cukai')->assertOk();
        $this->assertContains('bil-2026-01', $res->json('istilah'));
        $this->assertSame('C9', $res->json('data.0.hasil.0.id'));

        // Switch an entity off.
        $this->actingAs($admin)->putJson('/api/v1/adm/carian/tetapan/'.$settings['lot_tanah']['id'], ['aktif' => false])->assertOk();
        $this->assertNotContains('lot_tanah', array_column($this->actingAs($admin)->getJson('/api/v1/adm/carian?q=PT 1234')->json('data'), 'entiti'));

        // Searchable fields per entity reach the provider (ADM's own honours them).
        $kod = IndeksCarian::query()->where('entiti', 'kod_rujukan')->first();
        KodRujukan::query()->create(['jenis_kod' => 'daerah', 'kod' => 'X1', 'keterangan' => 'Sabak Bernam', 'tarikh_mula' => now()]);
        $this->assertSame(1, $this->actingAs($admin)->getJson('/api/v1/adm/carian?q=Sabak')->json('jumlah'));
        $this->actingAs($admin)->putJson('/api/v1/adm/carian/tetapan/'.$kod->id, ['medan' => ['kod']])->assertOk();
        $this->assertSame(0, $this->actingAs($admin)->getJson('/api/v1/adm/carian?q=Sabak')->json('jumlah'));
    }

    public function test_SRS_ADM_20_reindex_is_triggered_and_tracked(): void
    {
        $admin = $this->admin();
        $settings = collect($this->actingAs($admin)->getJson('/api/v1/adm/carian/tetapan')->json('data'))->keyBy('entiti');

        $this->actingAs($admin)->postJson('/api/v1/adm/carian/tetapan/'.$settings['lot_tanah']['id'].'/indeks-semula')->assertStatus(202);
        $this->assertSame(1, LotTanahSearchProvider::$reindexed);
        $this->assertSame('siap', IndeksCarian::query()->where('entiti', 'lot_tanah')->value('status_indeks'));

        $this->actingAs($admin)->postJson('/api/v1/adm/carian/tetapan/'.$settings['cukai']['id'].'/indeks-semula');
        $this->assertSame('tidak_disokong', IndeksCarian::query()->where('entiti', 'cukai')->value('status_indeks'));

        $this->actingAs($this->userWith(['adm.carian.lihat']))->postJson('/api/v1/adm/carian/tetapan/'.$settings['cukai']['id'].'/indeks-semula')->assertForbidden();
    }

    public function test_SRS_ADM_22_deep_link_resolves_to_the_source_record_with_access_enforced(): void
    {
        $officer = $this->userWith([]);
        $this->access->daerah[$officer->id] = ['PETALING'];

        $this->actingAs($officer)->getJson('/api/v1/adm/pautan/lot_tanah/L1')->assertOk()->assertJsonPath('data.pautan', '/admin/pro/lot/L1');
        $this->actingAs($officer)->getJson('/api/v1/adm/pautan/lot_tanah/L2')->assertNotFound(); // other district
        $this->actingAs($officer)->getJson('/api/v1/adm/pautan/lot_tanah/TIADA')->assertNotFound();
        $this->actingAs($officer)->getJson('/api/v1/adm/pautan/entiti_lain/1')->assertNotFound();

        // Provider without DeepLinkProvider: resolved through its search.
        $this->actingAs($officer)->getJson('/api/v1/adm/pautan/cukai/C9')->assertOk()->assertJsonPath('data.pautan', '/admin/cki/bil/C9');
        $this->actingAs($officer)->getJson('/api/v1/adm/pautan/cukai/C10')->assertNotFound();
    }

    public function test_SRS_ADM_22_search_result_links_open_the_right_record(): void
    {
        $admin = $this->admin();
        $kod = KodRujukan::query()->create(['jenis_kod' => 'daerah', 'kod' => '10', 'keterangan' => 'Petaling', 'tarikh_mula' => now()]);

        $hit = $this->actingAs($admin)->getJson('/api/v1/adm/carian?q=Petaling&entiti[]=kod_rujukan')->json('data.0.hasil.0');
        $this->assertSame((string) $kod->id, $hit['id']);
        $this->assertSame($hit['link'], $this->actingAs($admin)->getJson('/api/v1/adm/pautan/kod_rujukan/'.$kod->id)->json('data.pautan'));
        $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/pautan/kod_rujukan/'.$kod->id)->assertNotFound();
    }
}
