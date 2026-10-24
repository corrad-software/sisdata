<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Events\SessionTerminated;
use App\Modules\Rbc\Models\Sesi;
use App\Modules\Rbc\Services\AccountStatusService;
use App\Modules\Rbc\Services\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\TestCase;

/** SRS-RBC-17..20: server-side sessions on Laravel's database session driver. */
class SessionTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_SRS_RBC_18_session_ends_after_default_15_minutes_idle(): void
    {
        $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();

        $this->travel(14)->minutes();
        $this->api('GET', '/api/v1/user')->assertOk();

        $this->travel(16)->minutes();
        $this->api('GET', '/api/v1/user')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SESSION_ENDED')
            ->assertJsonPath('reason', SessionManager::TAMAT_MASA);

        $this->assertSame(SessionManager::TAMAT_MASA, Sesi::query()->sole()->kaedah_tamat);
        $this->api('GET', '/api/v1/user')->assertUnauthorized();
    }

    public function test_SRS_RBC_18_timeout_is_changed_by_admin_without_a_release(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/rbc/polisi', ['nilai' => ['sesi_tamat_minit' => 30]])
            ->assertOk()
            ->assertJsonFragment(['kunci' => 'sesi_tamat_minit', 'nilai' => 30]);

        $this->assertDatabaseHas('polisi_keselamatan', ['kunci' => 'sesi_tamat_minit']);

        $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();

        $this->travel(20)->minutes();
        $this->api('GET', '/api/v1/user')->assertOk();

        $this->travel(31)->minutes();
        $this->api('GET', '/api/v1/user')->assertUnauthorized();
    }

    public function test_SRS_RBC_18_status_poll_reports_remaining_time_without_extending_it(): void
    {
        $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();

        $this->travel(10)->minutes();
        $this->api('GET', '/api/v1/rbc/sesi/status')
            ->assertOk()
            ->assertJsonPath('data.tamat_minit', 15)
            ->assertJsonPath('data.amaran_saat', 120)
            ->assertJsonPath('data.baki_saat', 300);

        $this->travel(6)->minutes();
        $this->api('GET', '/api/v1/rbc/sesi/status')->assertUnauthorized();
    }

    public function test_SRS_RBC_18_extend_button_resets_the_idle_timer(): void
    {
        $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();

        $this->travel(14)->minutes();
        $this->api('POST', '/api/v1/rbc/sesi/lanjut')->assertOk()->assertJsonPath('data.baki_saat', 900);

        $this->travel(14)->minutes();
        $this->api('GET', '/api/v1/user')->assertOk();
    }

    public function test_SRS_RBC_18_scheduled_sweep_closes_idle_sessions(): void
    {
        $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();
        $sessionId = Sesi::query()->sole()->session_id;

        $this->travel(20)->minutes();
        $this->artisan('rbc:tamat-sesi')->assertSuccessful();

        $this->assertSame(SessionManager::TAMAT_MASA, Sesi::query()->sole()->kaedah_tamat);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
    }

    public function test_SRS_RBC_17_suspending_a_user_ends_their_sessions_and_tokens_immediately(): void
    {
        $user = $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();
        $sessionId = Sesi::query()->sole()->session_id;
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);

        Event::fake([SessionTerminated::class]);
        $admin = $this->admin();
        $cookie = $this->sessionCookie;

        $this->actingAs($admin)->putJson("/api/v1/rbc/pengguna/{$user->id}/status", ['status' => AccountStatus::GANTUNG, 'sebab' => 'Siasatan dalaman'])
            ->assertOk()
            ->assertJsonPath('data.status_akaun', AccountStatus::GANTUNG);

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertSame(SessionManager::STATUS_AKAUN, Sesi::query()->where('user_id', $user->id)->sole()->kaedah_tamat);
        Event::assertDispatched(SessionTerminated::class, fn ($e) => $e->user->is($user) && $e->method === SessionManager::STATUS_AKAUN);

        // Acceptance #2: the very next request on the old cookie is refused (well within one minute).
        $this->sessionCookie = $cookie;
        $this->api('GET', '/api/v1/user')->assertUnauthorized();
    }

    public function test_SRS_RBC_17_status_change_outside_the_service_still_logs_the_session_out(): void
    {
        $user = $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();

        // e.g. a direct DB fix by an operator — EnforceSession re-checks status on every request.
        DB::table('users')->where('id', $user->id)->update(['status_akaun' => AccountStatus::NYAHAKTIF]);

        $this->api('GET', '/api/v1/user')->assertUnauthorized()->assertJsonPath('reason', SessionManager::STATUS_AKAUN);
    }

    public function test_SRS_RBC_17_admin_can_end_all_sessions_of_a_user(): void
    {
        $user = $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();
        $cookie = $this->sessionCookie;

        $this->actingAs($this->admin())->deleteJson("/api/v1/rbc/pengguna/{$user->id}/sesi")
            ->assertOk()->assertJsonPath('data.ditamatkan', 1);

        $this->sessionCookie = $cookie;
        // Its Laravel session row is gone, so the cookie no longer authenticates at all.
        $this->api('GET', '/api/v1/user')->assertUnauthorized();
    }

    public function test_SRS_RBC_19_concurrent_session_limit_ends_the_oldest_session(): void
    {
        $this->setPolicy(['sesi_had_serentak' => 2]);
        $this->userWithPassword();

        $this->login('pegawai@mais.test')->assertOk();
        $first = $this->sessionCookie;
        $this->travel(1)->minutes();
        $this->login('pegawai@mais.test')->assertOk();
        $second = $this->sessionCookie;
        $this->travel(1)->minutes();
        $this->login('pegawai@mais.test')->assertOk();

        $this->assertSame(2, Sesi::query()->aktif()->count());
        $this->assertSame(SessionManager::HAD_SERENTAK, Sesi::query()->orderBy('tarikh_mula')->first()->kaedah_tamat);

        $this->sessionCookie = $first;
        $this->api('GET', '/api/v1/user')->assertUnauthorized();
        $this->sessionCookie = $second;
        $this->api('GET', '/api/v1/user')->assertOk();
    }

    public function test_SRS_RBC_19_admin_lists_and_force_terminates_a_session(): void
    {
        $user = $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();
        $cookie = $this->sessionCookie;
        $sesi = Sesi::query()->sole();

        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/v1/rbc/sesi')
            ->assertOk()
            ->assertJsonPath('data.0.id', $sesi->id)
            ->assertJsonPath('data.0.pengguna.email', $user->email)
            ->assertJsonPath('data.0.pelayar', 'Chrome');

        $this->actingAs($admin)->deleteJson("/api/v1/rbc/sesi/{$sesi->id}")
            ->assertOk()->assertJsonPath('data.kaedah_tamat', SessionManager::PAKSA);

        $this->assertSame($admin->id, $sesi->fresh()->ditamatkan_oleh);
        $this->sessionCookie = $cookie;
        $this->api('GET', '/api/v1/user')->assertUnauthorized();
    }

    public function test_SRS_RBC_19_logout_ends_the_session_on_the_server(): void
    {
        $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();
        $cookie = $this->sessionCookie;
        $sesi = Sesi::query()->sole();

        $this->api('POST', '/api/v1/logout')->assertNoContent();

        $this->assertSame(SessionManager::LOG_KELUAR, $sesi->fresh()->kaedah_tamat);
        $this->assertDatabaseMissing('sessions', ['id' => $sesi->session_id]);

        // Replaying the old cookie does not resurrect the session.
        $this->sessionCookie = $cookie;
        $this->api('GET', '/api/v1/user')->assertUnauthorized();
    }

    public function test_SRS_RBC_19_user_without_permission_cannot_see_or_end_sessions(): void
    {
        $other = User::factory()->create();
        $sesi = Sesi::query()->create(['session_id' => 'x', 'user_id' => $other->id, 'tarikh_mula' => now(), 'tarikh_aktiviti_akhir' => now()]);

        $this->actingAs(User::factory()->create())->getJson('/api/v1/rbc/sesi')->assertForbidden();
        $this->actingAs(User::factory()->withPermissions(['rbc.sesi.lihat'])->create())->deleteJson("/api/v1/rbc/sesi/{$sesi->id}")->assertForbidden();
        $this->assertNull($sesi->fresh()->tarikh_tamat);
    }

    public function test_SRS_RBC_20_session_id_is_regenerated_on_login(): void
    {
        $this->userWithPassword();

        $this->api('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $before = DB::table('sessions')->sole()->id;

        $this->api('POST', '/api/v1/login', ['email' => 'pegawai@mais.test', 'password' => self::STRONG_PASSWORD])->assertOk();

        $after = Sesi::query()->sole()->session_id;
        $this->assertNotSame($before, $after);
        $this->assertDatabaseMissing('sessions', ['id' => $before]);
        $this->assertDatabaseHas('sessions', ['id' => $after]);
    }

    public function test_SRS_RBC_20_session_cookie_is_httponly_samesite_and_secure_over_https(): void
    {
        $this->userWithPassword();
        $cookie = $this->login('pegawai@mais.test')->assertOk()->getCookie(config('session.cookie'), false);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertTrue(config('session.http_only'));
        $this->assertSame((bool) str_starts_with((string) config('app.url'), 'https://'), $cookie->isSecure());
    }

    public function test_SRS_RBC_20_session_is_bound_to_the_browser_fingerprint(): void
    {
        $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();

        $this->api('GET', '/api/v1/user', ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0')
            ->assertUnauthorized()
            ->assertJsonPath('reason', SessionManager::IKATAN_GAGAL);
    }

    public function test_SRS_RBC_20_session_is_bound_to_the_ip_range(): void
    {
        $this->userWithPassword();
        $this->login('pegawai@mais.test', ip: '192.0.2.10')->assertOk();

        $this->api('GET', '/api/v1/user', ip: '192.0.2.77')->assertOk(); // same /24
        $this->api('GET', '/api/v1/user', ip: '198.51.100.5')
            ->assertUnauthorized()
            ->assertJsonPath('reason', SessionManager::IKATAN_GAGAL);
    }

    public function test_SRS_RBC_20_ip_binding_can_be_made_exact_or_switched_off(): void
    {
        $this->userWithPassword();

        $this->setPolicy(['sesi_ikat_ip' => 'tiada']);
        $this->login('pegawai@mais.test', ip: '192.0.2.10')->assertOk();
        $this->api('GET', '/api/v1/user', ip: '198.51.100.5')->assertOk();

        $this->setPolicy(['sesi_ikat_ip' => 'tepat']);
        $this->login('pegawai@mais.test', ip: '192.0.2.10')->assertOk();
        $this->api('GET', '/api/v1/user', ip: '192.0.2.11')->assertUnauthorized();
    }

    public function test_SRS_RBC_17_reactivated_user_can_log_in_again(): void
    {
        $user = $this->userWithPassword();
        app(AccountStatusService::class)->change($user, AccountStatus::GANTUNG, 'ujian');
        $this->login('pegawai@mais.test')->assertUnprocessable();

        app(AccountStatusService::class)->change($user->fresh(), AccountStatus::AKTIF, 'ujian');
        $this->login('pegawai@mais.test')->assertOk();
    }
}
