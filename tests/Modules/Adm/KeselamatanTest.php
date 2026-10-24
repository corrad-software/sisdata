<?php

namespace Tests\Modules\Adm;

use App\Modules\Adm\Services\ParameterService;
use App\Modules\Adm\Services\Siem\PenghantarSyslog;
use App\Modules\Rbc\Events\AccessDenied;
use App\Modules\Rbc\Events\LoginFailed;
use App\Modules\Rbc\Events\UserLoggedIn;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class KeselamatanTest extends AdmTestCase
{
    private string $siemFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siemFile = storage_path('framework/testing/siem-'.uniqid().'.log');
        config(['adm.siem.fail' => $this->siemFile]);

        Route::middleware('api')->get('api/v1/adm/__ujian-ip', fn () => ['ip' => request()->ip()]);
        Route::middleware(['api', 'auth:sanctum'])->post('api/v1/adm/__ujian-transaksi', fn () => ['ok' => true]);
        Route::middleware(['api', 'auth:sanctum'])->get('api/v1/adm/__ujian-baca', fn () => ['ok' => true]);
    }

    protected function tearDown(): void
    {
        @unlink($this->siemFile);
        parent::tearDown();
    }

    private function param(string $key, mixed $value): void
    {
        $this->app->make(ParameterService::class)->store($key, $value, 'ujian', null);
    }

    /** @return list<array<string, mixed>> */
    private function siemEvents(): array
    {
        return is_file($this->siemFile)
            ? array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", (string) file_get_contents($this->siemFile))))
            : [];
    }

    public function test_SRS_ADM_05_admin_scoped_api_token_can_automate_configuration(): void
    {
        $admin = $this->admin();
        $plain = $this->actingAs($admin)->postJson('/api/v1/adm/token-api', ['nama' => 'Skrip import', 'tamat_hari' => 30])
            ->assertCreated()->json('data.token');
        $this->app['auth']->forgetGuards();

        $this->withToken($plain)->postJson('/api/v1/adm/kod-rujukan/import', [
            'jenis_kod' => 'daerah', 'rekod' => [['kod' => '10', 'keterangan' => 'Petaling'], ['kod' => '11', 'keterangan' => 'Klang']],
        ])->assertOk()->assertJsonPath('data.diterima', 2);

        // A token can't mint further tokens.
        $this->withToken($plain)->postJson('/api/v1/adm/token-api', ['nama' => 'x'])->assertForbidden();
    }

    public function test_SRS_ADM_05_tokens_without_the_admin_ability_or_permission_are_refused(): void
    {
        $admin = $this->admin();
        $other = $admin->createToken('integrasi', ['pro:baca'])->plainTextToken;
        $this->withToken($other)->getJson('/api/v1/adm/parameter')->assertForbidden();

        $plainUser = $this->userWith([]);
        $userToken = $plainUser->createToken('x', ['adm:pentadbir'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($userToken)->getJson('/api/v1/adm/parameter')->assertForbidden(); // ability alone isn't permission

        $this->actingAs($this->userWith([]))->postJson('/api/v1/adm/token-api', ['nama' => 'x'])->assertForbidden();
    }

    public function test_SRS_ADM_05_tokens_can_be_listed_and_revoked(): void
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/v1/adm/token-api', ['nama' => 'A'])->json('data.id');
        $this->actingAs($admin)->getJson('/api/v1/adm/token-api')->assertOk()->assertJsonPath('data.0.name', 'A')->assertJsonMissingPath('data.0.token');
        $this->actingAs($admin)->deleteJson("/api/v1/adm/token-api/{$id}")->assertNoContent();
        $this->assertSame('token_api_dibatalkan', collect($this->siemEvents())->last()['event']['action']);
    }

    public function test_SRS_ADM_26_real_client_ip_comes_from_x_forwarded_for_only_behind_a_trusted_proxy(): void
    {
        $headers = ['X-Forwarded-For' => '203.0.113.7'];
        $proxy = ['REMOTE_ADDR' => '10.20.0.5'];

        $this->withServerVariables($proxy)->getJson('/api/v1/adm/__ujian-ip', $headers)->assertJsonPath('ip', '10.20.0.5');

        $this->param('adm.proksi_dipercayai', '10.20.0.0/16');
        $this->withServerVariables($proxy)->getJson('/api/v1/adm/__ujian-ip', $headers)->assertJsonPath('ip', '203.0.113.7');

        // A spoofed header from an untrusted address is ignored.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])->getJson('/api/v1/adm/__ujian-ip', $headers)->assertJsonPath('ip', '198.51.100.9');
    }

    public function test_SRS_ADM_26_loopback_is_always_trusted_for_the_nextjs_rewrite(): void
    {
        $headers = ['X-Forwarded-For' => '203.0.113.7'];

        // Default (empty parameter): the same-host Next.js proxy passes the browser IP.
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->getJson('/api/v1/adm/__ujian-ip', $headers)->assertJsonPath('ip', '203.0.113.7');
        $this->withServerVariables(['REMOTE_ADDR' => '::1'])->getJson('/api/v1/adm/__ujian-ip', $headers)->assertJsonPath('ip', '203.0.113.7');

        // Configuring a WAF range keeps loopback trusted.
        $this->param('adm.proksi_dipercayai', '10.20.0.0/16');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->getJson('/api/v1/adm/__ujian-ip', $headers)->assertJsonPath('ip', '203.0.113.7');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])->getJson('/api/v1/adm/__ujian-ip', $headers)->assertJsonPath('ip', '198.51.100.9');
    }

    public function test_SRS_ADM_27_security_events_are_shipped_to_the_siem_stream(): void
    {
        $user = $this->admin();
        event(new UserLoggedIn($user, 'oidc'));
        event(new LoginFailed(null, 'x@mais.gov.my', 'kata_laluan_salah'));
        event(new AccessDenied($user, 'pro.lot.lihat', 'lot_tanah:1'));
        Log::channel('keselamatan')->warning('Percubaan token tak sah Bearer abc.def.ghi', ['tindakan' => 'token_tidak_sah']);

        $events = $this->siemEvents();
        $this->assertSame(['log_masuk', 'log_masuk_gagal', 'capaian_ditolak', 'token_tidak_sah'], array_column(array_column($events, 'event'), 'action'));
        $this->assertSame('failure', $events[1]['event']['outcome']);
        $this->assertSame($user->id, $events[0]['user']['id']);
        $this->assertArrayHasKey('@timestamp', $events[0]);
        $this->assertStringNotContainsString('abc.def.ghi', json_encode($events[3]));
    }

    public function test_SRS_ADM_27_syslog_adapter_emits_rfc5424_with_cef_payload(): void
    {
        $line = (new PenghantarSyslog('127.0.0.1'))->format([
            '@timestamp' => '2026-10-24T10:00:00+08:00',
            'event' => ['action' => 'log_masuk_gagal', 'outcome' => 'failure'],
            'user' => ['id' => 5], 'source' => ['ip' => '10.0.0.1'], 'trace' => ['id' => 'req-1'], 'message' => 'Log masuk gagal',
        ]);

        $this->assertMatchesRegularExpression('/^<84>1 \S+ \S+ sisdata - - - CEF:0\|MAIS\|SISDATA\|1\.0\|log_masuk_gagal\|/', $line);
        $this->assertStringContainsString('src=10.0.0.1', $line);
        $this->assertStringContainsString('requestId=req-1', $line);

        // Driver chosen by parameter; "tiada" switches shipping off.
        $this->param('adm.siem_pemacu', 'tiada');
        event(new UserLoggedIn($this->admin(), 'oidc'));
        $this->assertSame([], $this->siemEvents());
    }

    public function test_SRS_ADM_29_maintenance_mode_shows_notice_blocks_new_transactions_and_lets_admins_in(): void
    {
        $user = $this->userWith([]);
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/adm/penyelenggaraan', ['aktif' => true, 'notis' => 'Naik taraf pelayan 10pm-12am', 'tamat_dijangka' => '2026-10-25 00:00'])
            ->assertOk()->assertJsonPath('data.aktif', true);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/adm/penyelenggaraan')->assertOk()
            ->assertJsonPath('data.notis', 'Naik taraf pelayan 10pm-12am')
            ->assertJsonPath('data.tamat_dijangka', '2026-10-25 00:00');

        $this->actingAs($user)->postJson('/api/v1/adm/__ujian-transaksi')->assertStatus(503)
            ->assertJsonPath('code', 'MAINTENANCE')
            ->assertJsonPath('message', 'Naik taraf pelayan 10pm-12am')
            ->assertHeader('X-Mod-Penyelenggaraan', '1');
        $this->actingAs($user)->getJson('/api/v1/adm/__ujian-baca')->assertOk()->assertHeader('X-Mod-Penyelenggaraan', '1');
        $this->actingAs($admin)->postJson('/api/v1/adm/__ujian-transaksi')->assertOk();

        $this->assertSame('mod_penyelenggaraan_hidup', collect($this->siemEvents())->last()['event']['action']);

        $this->actingAs($admin)->putJson('/api/v1/adm/penyelenggaraan', ['aktif' => false])->assertOk();
        $this->actingAs($user)->postJson('/api/v1/adm/__ujian-transaksi')->assertOk()->assertHeaderMissing('X-Mod-Penyelenggaraan');
    }

    public function test_SRS_ADM_29_only_authorised_admins_switch_maintenance_mode(): void
    {
        $this->actingAs($this->userWith(['adm.parameter.kemas_kini']))->putJson('/api/v1/adm/penyelenggaraan', ['aktif' => true])->assertForbidden();
    }
}
