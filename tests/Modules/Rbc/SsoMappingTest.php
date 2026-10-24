<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Events\UserLoggedIn;
use App\Modules\Rbc\Models\LogLogMasuk;
use App\Modules\Rbc\Models\PemetaanTuntutan;
use App\Modules\Rbc\Models\Sesi;
use App\Modules\Rbc\Services\RbcAccessControl;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User as OidcUser;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\TestCase;

/** SRS-RBC-01/02/03/11: SSO MAIS login, claim → role/scope mapping. */
class SsoMappingTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.keycloak.after_login_redirect' => 'http://localhost:3000/admin', 'services.keycloak.login_page' => 'http://localhost:3000/login']);

        PemetaanTuntutan::query()->create(['jenis_tuntutan' => 'roles', 'nilai' => 'pegawai_daerah', 'peranan_id' => $this->role(BaseRoles::PEGAWAI_OPERASI)->id]);
        PemetaanTuntutan::query()->create(['jenis_tuntutan' => 'unit', 'nilai' => 'Pejabat Agama Islam Daerah *', 'skop_jenis' => 'unit', 'skop_nilai' => '{nilai}']);
        PemetaanTuntutan::query()->create(['jenis_tuntutan' => 'unit', 'nilai' => 'Pejabat Agama Islam Daerah Petaling', 'skop_jenis' => 'daerah', 'skop_nilai' => 'petaling']);
        PemetaanTuntutan::query()->create(['jenis_tuntutan' => 'jawatan', 'nilai' => 'Pengarah', 'peranan_id' => $this->role(BaseRoles::PELULUS)->id]);
        PemetaanTuntutan::query()->create(['jenis_tuntutan' => 'roles', 'nilai' => 'tidak_digunakan', 'peranan_id' => $this->role(BaseRoles::JURUAUDIT)->id, 'aktif' => false]);
    }

    /** @param array<string, mixed> $claims */
    private function fakeIdp(array $claims): void
    {
        $claims = array_merge(['sub' => 'kc-1', 'email' => 'siti.aminah@mais.test', 'name' => 'Siti Aminah', 'preferred_username' => 'siti'], $claims);
        $user = (new OidcUser)->setRaw($claims)->map(['id' => $claims['sub'], 'nickname' => $claims['preferred_username'], 'name' => $claims['name'], 'email' => $claims['email']]);
        $user->setToken('t');
        $user->setAccessTokenResponseBody(['access_token' => 't', 'id_token' => 'id.t']);
        Socialite::fake('keycloak', $user);
    }

    public function test_SRS_RBC_02_claims_map_to_roles_and_scopes_on_sso_login(): void
    {
        Event::fake([UserLoggedIn::class]);
        $this->fakeIdp(['roles' => ['pegawai_daerah', 'tidak_digunakan'], 'bahagian' => 'Bahagian Pengurusan Hartanah', 'unit' => 'Pejabat Agama Islam Daerah Petaling', 'jawatan' => 'Penolong Pegawai']);

        $this->get('/auth/oidc/callback?code=x&state=y')->assertRedirect('http://localhost:3000/admin');

        $user = User::where('email', 'siti.aminah@mais.test')->sole();
        $access = app(AccessControl::class);

        $this->assertSame([BaseRoles::PEGAWAI_OPERASI], $access->roles($user));
        $this->assertSame(['petaling'], $access->scope($user, AccessControl::SCOPE_DAERAH));
        $this->assertSame(['Pejabat Agama Islam Daerah Petaling'], $access->scope($user, 'unit'));
        $this->assertSame(['Bahagian Pengurusan Hartanah', 'Penolong Pegawai', 'sso'], [$user->bahagian, $user->jawatan, $user->jenis_pengesahan]);
        Event::assertDispatched(UserLoggedIn::class, fn ($e) => $e->user->is($user) && $e->method === 'sso');
    }

    public function test_SRS_RBC_02_sso_roles_are_resynced_each_login_and_manual_roles_kept(): void
    {
        $this->fakeIdp(['roles' => ['pegawai_daerah'], 'jawatan' => 'Pengarah']);
        $this->get('/auth/oidc/callback?code=x&state=y');
        $user = User::where('email', 'siti.aminah@mais.test')->sole();
        app(RoleService::class)->assignRoleByCode($user, BaseRoles::JURUAUDIT);

        $this->assertSame([BaseRoles::JURUAUDIT, BaseRoles::PELULUS, BaseRoles::PEGAWAI_OPERASI], array_values(array_intersect([BaseRoles::JURUAUDIT, BaseRoles::PELULUS, BaseRoles::PEGAWAI_OPERASI], app(AccessControl::class)->roles($user))));

        // Next login: no longer Pengarah → Pelulus withdrawn; manual Juruaudit stays.
        $this->app['auth']->forgetGuards();
        $this->fakeIdp(['roles' => ['pegawai_daerah'], 'jawatan' => 'Penolong Pegawai']);
        $this->get('/auth/oidc/callback?code=x&state=y');

        RbcAccessControl::flushAll();
        $this->assertSame([BaseRoles::JURUAUDIT, BaseRoles::PEGAWAI_OPERASI], app(AccessControl::class)->roles($user));
    }

    public function test_SRS_RBC_02_admin_manages_mapping_rules_and_previews_them(): void
    {
        $admin = $this->admin();

        $rule = $this->actingAs($admin)->postJson('/api/v1/rbc/pemetaan', [
            'jenis_tuntutan' => 'bahagian', 'nilai' => 'Bahagian Wakaf', 'peranan_id' => $this->role(BaseRoles::PENGGUNA_PAPARAN)->id,
            'skop_jenis' => 'bahagian', 'skop_nilai' => '{nilai}', 'keterangan' => 'Pegawai Wakaf',
        ])->assertCreated()->assertJsonPath('data.peranan.kod', BaseRoles::PENGGUNA_PAPARAN)->json('data');

        $this->actingAs($admin)->postJson('/api/v1/rbc/pemetaan/uji', ['bahagian' => 'Bahagian Wakaf', 'roles' => ['pegawai_daerah']])
            ->assertOk()
            ->assertJsonPath('data.skop.bahagian', ['Bahagian Wakaf'])
            ->assertJsonCount(2, 'data.peranan');

        $this->actingAs($admin)->putJson("/api/v1/rbc/pemetaan/{$rule['id']}", [...$rule, 'peranan_id' => null, 'aktif' => false])->assertOk()->assertJsonPath('data.aktif', false);
        $this->actingAs($admin)->deleteJson("/api/v1/rbc/pemetaan/{$rule['id']}")->assertNoContent();

        $this->actingAs($admin)->postJson('/api/v1/rbc/pemetaan', ['jenis_tuntutan' => 'emel', 'nilai' => 'x'])->assertUnprocessable();
        $this->actingAs(User::factory()->withRole(BaseRoles::PEGAWAI_OPERASI)->create())->postJson('/api/v1/rbc/pemetaan', ['jenis_tuntutan' => 'roles', 'nilai' => 'x', 'peranan_id' => 1])->assertForbidden();
    }

    public function test_SRS_RBC_15_suspended_account_is_refused_on_sso_login(): void
    {
        User::factory()->sso()->create(['email' => 'siti.aminah@mais.test', 'oidc_sub' => 'kc-1', 'status_akaun' => AccountStatus::GANTUNG]);
        $this->fakeIdp(['roles' => ['pegawai_daerah']]);

        $this->get('/auth/oidc/callback?code=x&state=y')->assertRedirect('http://localhost:3000/login?sso_error=gantung');

        $this->assertGuest();
        $this->assertSame('status_gantung', LogLogMasuk::query()->sole()->sebab);
        $this->assertSame(0, Sesi::count());
    }

    public function test_SRS_RBC_01_sso_login_is_logged_and_opens_a_server_session(): void
    {
        $this->fakeIdp(['roles' => []]);

        $this->withHeader('User-Agent', $this->userAgent)->get('/auth/oidc/callback?code=x&state=y')->assertRedirect();

        $log = LogLogMasuk::query()->sole();
        $this->assertTrue($log->berjaya);
        $this->assertSame('sso', $log->kaedah);
        $this->assertSame('sso', Sesi::query()->sole()->kaedah_log_masuk);
    }

    public function test_SRS_RBC_11_sso_provisioned_user_has_no_password(): void
    {
        $this->fakeIdp([]);
        $this->get('/auth/oidc/callback?code=x&state=y');

        $this->assertNull(User::where('email', 'siti.aminah@mais.test')->sole()->password);
    }

    public function test_SRS_RBC_02_realm_sends_bahagian_unit_and_jawatan_claims(): void
    {
        $realm = json_decode(file_get_contents(base_path('infra/keycloak/realm-mais.json')), true, flags: JSON_THROW_ON_ERROR);
        $client = collect($realm['clients'])->firstWhere('clientId', 'sisdata-web');

        foreach (['bahagian', 'unit', 'jawatan'] as $claim) {
            $mapper = collect($client['protocolMappers'])->first(fn ($m) => ($m['config']['claim.name'] ?? null) === $claim);
            $this->assertNotNull($mapper, "no {$claim} mapper");
            $this->assertSame('oidc-usermodel-attribute-mapper', $mapper['protocolMapper']);
            $this->assertSame('true', $mapper['config']['id.token.claim']);
        }

        $siti = collect($realm['users'])->firstWhere('username', 'siti.aminah');
        $this->assertNotEmpty($siti['attributes']['unit'] ?? null);

        $profile = json_decode(collect($realm['components']['org.keycloak.userprofile.UserProfileProvider'])->first()['config']['kc.user.profile.config'][0], true);
        $this->assertEqualsCanonicalizing(['username', 'email', 'firstName', 'lastName', 'bahagian', 'unit', 'jawatan'], array_column($profile['attributes'], 'name'));
    }
}
