<?php

namespace Tests\Modules\Adm;

use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Adm\Contracts\Secrets;
use App\Modules\Adm\Models\Integrasi;
use App\Modules\Adm\Models\KonfigKelulusan;
use App\Modules\Adm\Models\Rahsia;
use App\Modules\Adm\Services\Kesihatan\PemantauKesihatan;
use App\Modules\Adm\Services\Kesihatan\Semakan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Modules\Adm\Fakes\FakeCheck;

class KesihatanIntegrasiTest extends AdmTestCase
{
    private function useChecks(array $checks): void
    {
        $this->app->make(PemantauKesihatan::class)->useChecks($checks);
    }

    private function integration(array $override = []): Integrasi
    {
        return Integrasi::query()->create([
            'kod' => 'jupem', 'nama' => 'JUPEM', 'jenis' => 'rest', 'endpoint' => 'https://api.jupem.test/health',
            'tempoh_tamat_masa' => 5, 'status' => 'aktif', ...$override,
        ]);
    }

    public function test_SRS_ADM_16_single_health_screen_shows_services_queue_integrations_storage_database_and_errors(): void
    {
        $this->useChecks([
            new FakeCheck('pangkalan_data', Semakan::OK, null, ['saiz_bait' => 12345]),
            new FakeCheck('baris_gilir', Semakan::OK, null, ['saiz' => 3, 'ambang' => 1000]),
            new FakeCheck('storan', Semakan::OK, null, ['guna_peratus' => 40.0]),
            new FakeCheck('geoserver', Semakan::GAGAL, 'ConnectionException'),
        ]);
        $this->integration();
        Log::error('Ralat terkini', ['modul' => 'dms']);

        $res = $this->actingAs($this->admin())->getJson('/api/v1/adm/kesihatan?segar=1')->assertOk();

        $res->assertJsonPath('data.status_keseluruhan', 'gagal')
            ->assertJsonPath('data.pangkalan_data.saiz_bait', 12345)
            ->assertJsonPath('data.baris_gilir.saiz', 3)
            ->assertJsonPath('data.storan.guna_peratus', 40)
            ->assertJsonPath('data.integrasi.0.kod', 'jupem')
            ->assertJsonPath('data.integrasi.0.status_kesihatan', 'ok');
        $this->assertContains('Ralat terkini', array_column($res->json('data.ralat_terkini'), 'mesej'));
        $this->assertCount(4, $res->json('data.perkhidmatan'));
    }

    public function test_SRS_ADM_16_default_probes_cover_every_platform_service(): void
    {
        $codes = array_map(fn (Semakan $s) => $s->kod(), $this->app->make(PemantauKesihatan::class)->checks());
        foreach (['pangkalan_data', 'redis', 'baris_gilir', 'storan', 'minio', 'geoserver', 'keycloak', 'mailpit', 'clamav', 'smtp'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function test_SRS_ADM_16_health_screen_requires_permission(): void
    {
        $this->useChecks([]);
        $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/kesihatan')->assertForbidden();
        $this->actingAs($this->userWith(['adm.kesihatan.lihat']))->getJson('/api/v1/adm/kesihatan')->assertOk();
    }

    public function test_SRS_ADM_17_service_down_queue_and_storage_thresholds_alert_ict(): void
    {
        $ict = $this->userWith(['adm.amaran.lihat']);
        $this->useChecks([
            new FakeCheck('redis', Semakan::GAGAL, 'Connection refused'),
            new FakeCheck('baris_gilir', Semakan::AMARAN, 'Baris gilir 5000 melebihi ambang 1000.'),
            new FakeCheck('storan', Semakan::AMARAN, 'Penggunaan storan 95%'),
            new FakeCheck('minio', Semakan::OK),
        ]);

        $this->artisan('adm:semak-kesihatan')->assertSuccessful();

        $alerts = $this->notifier->ofType('adm.amaran_kesihatan');
        $this->assertCount(3, $alerts);
        $this->assertSame([$ict->id], $alerts[0]['recipients']);
        $this->assertNotNull($alerts[0]['dedupe']);
        $this->assertSame(4, DB::table('adm_kesihatan')->count());
    }

    public function test_SRS_ADM_17_an_integration_switched_off_alerts_after_repeated_failures(): void
    {
        $this->userWith(['adm.amaran.lihat']);
        $this->useChecks([]);
        $this->integration();
        Http::fakeSequence('api.jupem.test/*')->push('down', 503)->push('down', 503)->push('down', 503)->push('ok', 200);

        $this->artisan('adm:semak-kesihatan');
        $this->artisan('adm:semak-kesihatan');
        $this->assertCount(0, $this->notifier->ofType('adm.integrasi_gagal'));
        $this->artisan('adm:semak-kesihatan'); // 3rd consecutive failure = default threshold

        $this->assertCount(1, $this->notifier->ofType('adm.integrasi_gagal'));
        $this->assertSame(3, Integrasi::query()->first()->kegagalan_berturut);

        $this->actingAs($this->admin())->getJson('/api/v1/adm/kesihatan?segar=1')->assertJsonPath('data.integrasi.0.status_kesihatan', 'gagal');

        $this->artisan('adm:semak-kesihatan');
        $this->assertSame(0, Integrasi::query()->first()->kegagalan_berturut);
    }

    public function test_SRS_ADM_04_integration_settings_go_through_approval_and_are_readable_by_modules(): void
    {
        $requester = $this->admin();
        $payload = [
            'kod' => 'ebayar', 'nama' => 'e-Bayar', 'jenis' => 'rest', 'endpoint' => 'https://ebayar.test/api',
            'id_rahsia' => 'integrasi.ebayar.kunci', 'tempoh_tamat_masa' => 20, 'kadar_capaian' => 2, 'jadual' => '0 2 * * *',
            'status' => 'aktif', 'justifikasi' => 'Integrasi baharu',
        ];

        $id = $this->actingAs($requester)->postJson('/api/v1/adm/integrasi', $payload)->assertStatus(202)->json('data.id');
        $this->assertNull($this->app->make(Integrations::class)->settings('ebayar'));

        $this->actingAs($this->admin())->postJson("/api/v1/adm/kelulusan/{$id}/lulus")->assertOk();

        $settings = $this->app->make(Integrations::class)->settings('ebayar');
        $this->assertSame('https://ebayar.test/api', $settings['endpoint']);
        $this->assertSame(20, $settings['tempoh_tamat_masa']);
        $this->assertSame('0 2 * * *', $settings['jadual']);
        $this->assertSame('integrasi.ebayar.kunci', $settings['id_rahsia']);

        // Rate limit per minute.
        $integrations = $this->app->make(Integrations::class);
        $this->assertTrue($integrations->allow('ebayar'));
        $this->assertTrue($integrations->allow('ebayar'));
        $this->assertFalse($integrations->allow('ebayar'));

        // Rescheduling (SRS-ADM-05 automation) is also a critical change.
        $integrasi = Integrasi::query()->where('kod', 'ebayar')->first();
        $this->actingAs($requester)->putJson("/api/v1/adm/integrasi/{$integrasi->id}", ['jadual' => '0 3 * * *', 'justifikasi' => 'Pindah slot'])
            ->assertStatus(202)->assertJsonPath('data.nilai_sebelum.jadual', '0 2 * * *')->assertJsonPath('data.nilai_selepas.jadual', '0 3 * * *');
        $this->assertSame('0 2 * * *', $integrasi->fresh()->jadual);
    }

    public function test_SRS_ADM_04_test_connection_logs_the_exchange_and_permissions_apply(): void
    {
        $integrasi = $this->integration();
        Http::fake(['api.jupem.test/*' => Http::response('ok', 200)]);

        $this->actingAs($this->admin())->postJson("/api/v1/adm/integrasi/{$integrasi->id}/uji")->assertOk()->assertJsonPath('data.berjaya', true);
        $this->assertDatabaseHas('adm_log_integrasi', ['kod_integrasi' => 'jupem', 'operasi' => 'uji_sambungan', 'berjaya' => true]);

        $this->actingAs($this->userWith(['adm.integrasi.lihat']))->postJson('/api/v1/adm/integrasi', [])->assertForbidden();
        $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/integrasi')->assertForbidden();
    }

    public function test_SRS_ADM_28_secrets_are_encrypted_at_rest_and_never_returned(): void
    {
        $requester = $this->admin();
        $id = $this->actingAs($requester)->putJson('/api/v1/adm/rahsia/integrasi.jupem.token', [
            'nilai' => 'tok-SANGAT-RAHSIA', 'keterangan' => 'Token JUPEM', 'justifikasi' => 'Token baharu',
        ])->assertStatus(202)->json('data.id');

        // Pending: encrypted, masked in the request record, not yet active.
        $pending = DB::table('adm_konfig_kelulusan')->where('id', $id)->first();
        $this->assertStringNotContainsString('tok-SANGAT-RAHSIA', json_encode($pending));
        $this->assertFalse($this->app->make(Secrets::class)->has('integrasi.jupem.token'));

        $this->actingAs($this->admin())->postJson("/api/v1/adm/kelulusan/{$id}/lulus")->assertOk()
            ->assertJsonMissing(['tok-SANGAT-RAHSIA']);

        $raw = DB::table('adm_rahsia')->where('kunci', 'integrasi.jupem.token')->value('nilai_tersulit');
        $this->assertNotSame('tok-SANGAT-RAHSIA', $raw);
        $this->assertStringNotContainsString('tok-SANGAT-RAHSIA', (string) $raw);
        $this->assertSame('tok-SANGAT-RAHSIA', $this->app->make(Secrets::class)->get('integrasi.jupem.token'));
        $this->assertNull(KonfigKelulusan::query()->find($id)->nilai_rahsia_tersulit);

        $list = $this->actingAs($this->admin())->getJson('/api/v1/adm/rahsia')->assertOk();
        $this->assertSame('Token JUPEM', $list->json('data.0.keterangan'));
        $this->assertStringNotContainsString('tok-SANGAT-RAHSIA', (string) $list->getContent());
        $this->assertArrayNotHasKey('nilai_tersulit', $list->json('data.0'));

        $this->actingAs($this->userWith(['adm.rahsia.lihat']))->putJson('/api/v1/adm/rahsia/x.y', ['nilai' => 'a', 'justifikasi' => 'b'])->assertForbidden();
    }

    public function test_SRS_ADM_28_artisan_adm_secret_stores_an_encrypted_value(): void
    {
        $this->artisan('adm:secret', ['kunci' => 'smtp.kata_laluan', '--keterangan' => 'SMTP'])
            ->expectsQuestion('Value (hidden)', 'p4ss-smtp')
            ->assertSuccessful();

        $this->assertSame('p4ss-smtp', $this->app->make(Secrets::class)->get('smtp.kata_laluan'));
        $this->assertStringNotContainsString('p4ss-smtp', (string) DB::table('adm_rahsia')->value('nilai_tersulit'));
        $this->assertArrayNotHasKey('nilai_tersulit', Rahsia::query()->first()->toArray());

        $this->artisan('adm:secret', ['kunci' => 'Bad Key'])->assertFailed();
        $this->artisan('adm:secret', ['kunci' => 'smtp.kata_laluan', '--padam' => true])->assertSuccessful();
        $this->assertFalse($this->app->make(Secrets::class)->has('smtp.kata_laluan'));
    }
}
