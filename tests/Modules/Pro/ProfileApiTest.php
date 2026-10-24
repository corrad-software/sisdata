<?php

namespace Tests\Modules\Pro;

use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Pro\Services\LotSearchProvider;
use App\Modules\Pro\Services\ProfileSectionRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Modules\Pro\Support\FakeTitleSection;

class ProfileApiTest extends ProTestCase
{
    public function test_SRS_PRO_26_profile_has_six_tabs_own_sections_and_placeholders_for_missing_modules(): void
    {
        $user = $this->officer(['pro.lot.lihat', 'pro.jejak.lihat']);
        $lot = $this->makeLot();
        $this->addGeometry($lot, $this->square());

        $profile = $this->actingAs($user)->getJson("/api/v1/pro/lot/{$lot->id}/profil")->assertOk();
        $tabs = collect($profile->json('data.tab'))->keyBy('kod');

        $this->assertSame(['identiti', 'kewangan', 'pemanfaatan', 'risiko', 'ruang', 'dokumen'], $tabs->keys()->all());
        $sections = fn (string $tab) => collect($tabs[$tab]['seksyen'])->keyBy('key');
        $this->assertSame('ada', $sections('identiti')['lot_identiti']['status']);
        // MOD-HKM / MOD-PER register their own sections now; modules not built yet stay placeholders.
        $this->assertSame('belum_tersedia', $sections('kewangan')['penilaian']['status']);
        $this->assertArrayNotHasKey('aset_fizikal', $sections('ruang')->all()); // registered by MOD-AST; hidden without ast.aset.lihat
        $this->assertArrayNotHasKey('cukai', $sections('kewangan')->all()); // registered by MOD-CKI; hidden without cki.bil.lihat
        // 'aduan' / 'pajakan' / 'kes_mahkamah' are registered by MOD-ADU / MOD-PJK / MOD-KES; kes_mahkamah is Rahsia → hidden without kes.rahsia.lihat.
        $this->assertArrayNotHasKey('kes_mahkamah', $sections('risiko')->all());
        $this->assertSame('ada', $sections('ruang')['spatial']['status']);
        $this->assertArrayHasKey('jejak_audit', $sections('dokumen')->all());
        $this->assertArrayHasKey('log_akses', $sections('dokumen')->all());
        $profile->assertJsonPath('data.geojson.type', 'Feature')->assertJsonPath('data.lot.no_lot', 'Lot 1234');
        $this->assertFalse($profile->json('data.kewangan.tersedia'));
    }

    public function test_SRS_PRO_26_sections_registered_by_other_modules_replace_their_placeholder(): void
    {
        app(ProfileSections::class)->register(FakeTitleSection::class);
        $this->assertSame(app(ProfileSectionRegistry::class), app(ProfileSections::class), 'registry is a singleton');
        $lot = $this->makeLot();

        $profile = $this->actingAs($this->officer(['pro.lot.lihat']))->getJson("/api/v1/pro/lot/{$lot->id}/profil")->assertOk();
        $identiti = collect(collect($profile->json('data.tab'))->firstWhere('kod', 'identiti')['seksyen'])->keyBy('key');

        $this->assertSame('ada', $identiti['hak_milik']['status']);
        $this->assertSame('GRN 12345', $identiti['hak_milik']['items'][0]['nilai']);
        $this->assertContains('Hak milik tamat dalam 90 hari', array_column($profile->json('data.bendera'), 'label'));
    }

    public function test_SRS_PRO_27_timeline_aggregates_events_from_sections_newest_first_with_links(): void
    {
        app(ProfileSections::class)->register(FakeTitleSection::class);
        $user = $this->officer();
        $lot = $this->makeLot();
        $this->addGeometry($lot, $this->square());

        $timeline = $this->actingAs($user)->getJson("/api/v1/pro/lot/{$lot->id}/profil")->assertOk()->json('data.garis_masa');
        $types = array_column($timeline, 'type');
        $this->assertContains('rekod_dicipta', $types);
        $this->assertContains('geometri', $types);
        $this->assertSame('pendaftaran_hak_milik', end($timeline)['type'], 'oldest event last');
        $dates = array_column($timeline, 'date');
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);
        foreach ($timeline as $event) {
            $this->assertStringStartsWith('/admin/', $event['link']);
        }
    }

    public function test_SRS_PRO_28_revenue_and_cost_are_bucketed_monthly_and_yearly_with_net_return(): void
    {
        app(ProfileSections::class)->register(FakeTitleSection::class);
        $lot = $this->makeLot();

        $money = $this->actingAs($this->officer(['pro.lot.lihat']))->getJson("/api/v1/pro/lot/{$lot->id}/kewangan")->assertOk()->json('data');

        $this->assertTrue($money['tersedia']);
        $months = collect($money['bulanan'])->keyBy('tempoh');
        $this->assertEquals(1000, $months['2026-01']['hasil_sewaan']);
        $this->assertEquals(850, $months['2026-01']['pulangan_bersih']);
        $years = collect($money['tahunan'])->keyBy('tempoh');
        $this->assertEquals(-300, $years['2025']['pulangan_bersih']);
        $this->assertEquals(1850, $years['2026']['pulangan_bersih']);
        $this->assertEquals(1550, $money['jumlah']['pulangan_bersih']);
    }

    public function test_SRS_PRO_30_every_view_export_and_print_is_logged_and_visible_to_authorised_roles(): void
    {
        $viewer = $this->officer(['pro.lot.lihat', 'pro.lot.eksport']);
        $auditor = $this->officer(['pro.lot.lihat', 'pro.jejak.lihat']);
        $lot = $this->makeLot();

        $this->actingAs($viewer)->getJson("/api/v1/pro/lot/{$lot->id}/profil")->assertOk();
        $this->actingAs($viewer)->postJson("/api/v1/pro/lot/{$lot->id}/cetak")->assertOk();
        $this->actingAs($viewer)->get("/api/v1/pro/lot/{$lot->id}/profil/eksport?format=pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertEqualsCanonicalizing(['lihat', 'cetak', 'eksport'], DB::table('log_akses_profil')->where('lot_id', $lot->id)->where('id_pengguna', $viewer->id)->pluck('jenis_capaian')->all());
        $this->assertAudited('cetak', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $lot->id, 'id_pengguna' => $viewer->id]);
        $this->assertAudited('eksport', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $lot->id]);

        $dokumen = fn ($user) => collect(collect($this->actingAs($user)->getJson("/api/v1/pro/lot/{$lot->id}/profil")->json('data.tab'))->firstWhere('kod', 'dokumen')['seksyen'])->keyBy('key');
        $this->assertArrayNotHasKey('log_akses', $dokumen($viewer)->all(), 'access log hidden without pro.jejak.lihat');
        $log = $dokumen($auditor)['log_akses']['items'];
        $this->assertContains($viewer->name, array_column($log, 'nilai'));
        $this->assertContains('Cetak', array_column($log, 'label'));
    }

    public function test_SRS_PRO_26_acceptance_profile_shows_everything_in_one_view_and_logs_access(): void
    {
        app(ProfileSections::class)->register(FakeTitleSection::class);
        $user = $this->officer(['pro.lot.lihat', 'pro.jejak.lihat', 'pro.lot.kemas_kini']);
        $lot = $this->makeLot();
        $this->addGeometry($lot, $this->square());
        $this->documents->store(UploadedFile::fake()->create('geran.pdf', 10, 'application/pdf'), 'lot_tanah', $lot->id, 'geran', $user);
        $this->actingAs($user)->putJson("/api/v1/pro/lot/{$lot->id}", ['zoning' => 'Kediaman'])->assertOk();

        $data = $this->actingAs($user)->getJson("/api/v1/pro/lot/{$lot->id}/profil")->assertOk()->json('data');
        $all = collect($data['tab'])->flatMap(fn ($t) => collect($t['seksyen'])->mapWithKeys(fn ($s) => [$s['key'] => $s]));

        $this->assertSame('ada', $all['hak_milik']['status']);                       // hak milik
        $this->assertSame('MultiPolygon', $data['geojson']['geometry']['type']);       // lokasi GIS
        $this->assertArrayNotHasKey('cukai', $all->all());                             // MOD-CKI section hidden without cki.bil.lihat
        $this->assertArrayNotHasKey('pajakan', $all->all());                           // MOD-PJK section hidden without pjk.kontrak.lihat
        $this->assertSame('geran.pdf', $all['dokumen']['items'][0]['label']);          // dokumen
        $this->assertArrayHasKey('kualiti_data', $all->all());                         // risiko
        $this->assertContains('kemas_kini', array_map(fn ($i) => $i['label'] === 'Kemas kini' ? 'kemas_kini' : $i['label'], $all['jejak_audit']['items'])); // sejarah transaksi
        $this->assertSame(1, DB::table('log_akses_profil')->where('lot_id', $lot->id)->count());
    }

    public function test_SRS_PRO_18_export_formats_respect_role_and_classification(): void
    {
        $plain = $this->officer(['pro.lot.lihat', 'pro.lot.eksport']);
        $cleared = $this->officer(['pro.lot.lihat', 'pro.lot.eksport', 'pro.sulit.lihat', 'pro.jejak.lihat']);
        $this->makeLot(['syarat_nyata' => 'Syarat rahsia ujian']);
        $this->makeLot(['no_lot' => 'Lot 2', 'klasifikasi' => 'sulit']);

        $csv = $this->actingAs($plain)->get('/api/v1/pro/lot/eksport?format=csv')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Syarat Nyata', $csv);
        $this->assertStringNotContainsString('Lot 2', $csv);
        $this->assertStringNotContainsString('Kunci Asal', $csv);
        $this->assertStringContainsString('Lot 1234', $csv);

        $csv = $this->actingAs($cleared)->get('/api/v1/pro/lot/eksport?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Syarat rahsia ujian', $csv);
        $this->assertStringContainsString('Lot 2', $csv);
        $this->assertStringContainsString('Kunci Asal', $csv);

        $json = $this->actingAs($plain)->get('/api/v1/pro/lot/eksport?format=json')->assertOk()->json();
        $this->assertSame('Lot 1234', $json['data'][0]['no_lot']);
        $this->actingAs($plain)->get('/api/v1/pro/lot/eksport?format=xlsx')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($plain)->get('/api/v1/pro/lot/eksport?format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($plain)->get('/api/v1/pro/lot/eksport?format=doc')->assertStatus(422);
        $this->actingAs($this->officer(['pro.lot.lihat']))->get('/api/v1/pro/lot/eksport?format=csv')->assertForbidden();
        $this->assertAudited('eksport', ['modul' => 'pro', 'nama_entiti' => 'lot_tanah', 'id_pengguna' => $plain->id]);
    }

    public function test_SRS_PRO_29_scoped_api_keys_with_expiry_rate_limit_revocation_and_usage_log(): void
    {
        $owner = $this->officer(['pro.lot.lihat', 'pro.api.cipta'], ['1008']);
        $lot = $this->makeLot();
        $this->addGeometry($lot, $this->square());
        $other = $this->makeLot(['no_lot' => 'Lot 1', 'kod_mukim' => '100101']);

        $created = $this->actingAs($owner)->postJson('/api/v1/pro/kunci-api', ['nama' => 'GRP', 'sistem_pemanggil' => 'GRP MAIS', 'skop' => ['pro:lot'], 'tamat_hari' => 30, 'had_kadar_seminit' => 3])
            ->assertCreated()->json('data');
        $token = $created['token'];
        $this->app['auth']->forgetGuards();

        $api = fn (string $url) => $this->withHeader('Authorization', 'Bearer '.$token)->getJson($url);
        $api('/api/v1/pro/lot')->assertOk()->assertJsonCount(1, 'data')->assertHeader('X-RateLimit-Limit', '3');  // owner's district scope applies
        $api("/api/v1/pro/lot/{$other->id}")->assertNotFound();
        $api("/api/v1/pro/lot/{$lot->id}/geojson")->assertForbidden();                                          // scope not granted
        $api("/api/v1/pro/lot/{$lot->id}")->assertOk();
        $api('/api/v1/pro/lot')->assertStatus(429);                                                              // 4th call in a minute
        $this->assertSame(1, DB::table('log_akses_profil')->where('lot_id', $lot->id)->where('jenis_capaian', 'api')->count());

        $usage = collect($this->actingAs($owner)->getJson('/api/v1/pro/kunci-api/penggunaan')->assertOk()->json('data'))->firstWhere('id_token', $created['id']);
        $this->assertEquals(5, $usage['bil_panggilan']);
        $this->assertEquals(3, $usage['bil_gagal']); // 404 + 403 + 429
        $this->assertSame('GRP MAIS', $usage['sistem_pemanggil']);

        // Keys can't mint keys.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/pro/kunci-api', ['nama' => 'x', 'sistem_pemanggil' => 'x', 'skop' => ['pro:lot']])->assertForbidden();

        // Immediate revocation.
        $this->app['auth']->forgetGuards();
        $this->withHeaders([])->actingAs($owner)->deleteJson('/api/v1/pro/kunci-api/'.$created['id'])->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/pro/lot/{$lot->id}")->assertUnauthorized();
        $this->assertAudited('batal_kunci_api', ['nama_entiti' => 'kunci_api_pro', 'id_rekod' => $created['id']]);
        $this->assertSame('dibatalkan', $this->actingAs($owner)->getJson('/api/v1/pro/kunci-api')->json('data.0.status'));
    }

    public function test_SRS_PRO_29_expired_key_is_refused_and_openapi_document_is_served(): void
    {
        $owner = $this->officer(['pro.lot.lihat', 'pro.api.cipta']);
        $token = $this->actingAs($owner)->postJson('/api/v1/pro/kunci-api', ['nama' => 'X', 'sistem_pemanggil' => 'Portal', 'skop' => ['pro:lot', 'pro:geojson']])->json('data.token');
        DB::table('personal_access_tokens')->update(['expires_at' => now()->subDay()]);
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/pro/lot')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $yaml = $this->withHeaders([])->actingAs($owner)->get('/api/v1/pro/openapi.yaml')->assertOk()->getContent();
        $this->assertStringContainsString('openapi: 3.0', (string) $yaml);
        $this->assertStringContainsString('/lot/{id}/kewangan:', (string) $yaml);
    }

    public function test_SRS_PRO_29_api_key_cannot_exceed_its_owners_permissions(): void
    {
        $owner = $this->officer(['pro.api.cipta']); // may mint, but cannot read lots
        $this->makeLot();
        Sanctum::actingAs($owner, ['pro:api', 'pro:lot']);
        DB::table('kunci_api_pro')->insert(['id_token' => 0, 'id_pemilik' => $owner->id, 'sistem_pemanggil' => 'x', 'skop' => '["pro:lot"]', 'created_at' => now(), 'updated_at' => now()]);

        $this->getJson('/api/v1/pro/lot')->assertForbidden();
    }

    public function test_SRS_GEN_05_lots_join_global_and_detailed_search_within_scope(): void
    {
        $lot = $this->makeLot(['no_lot' => 'Lot 4321']);
        $this->makeLot(['no_lot' => 'Lot 4321', 'kod_mukim' => '100101']);
        $provider = app(LotSearchProvider::class);
        $user = $this->officer(['pro.lot.lihat'], ['1008']);
        $this->actingAs($user);

        $hits = $provider->search('lot 4321', $user);
        $this->assertSame([$lot->id], array_column($hits, 'id'));
        $this->assertSame('/admin/pro/lot/'.$lot->id, $hits[0]['link']);
        $this->assertSame([$lot->id], array_column($provider->searchWithFilters('', ['no_lot' => '4321', 'mukim' => '100803'], $user), 'id'));
        $this->assertSame([], $provider->search('4321', $this->officer([])));
        $this->assertSame('/admin/pro/lot/'.$lot->id, $provider->link($lot->id, $user));
    }
}
