<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\OidcController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User as OidcUser;
use Tests\TestCase;

/**
 * SRS-RBC-01 (SSO via SSO MAIS OIDC IdP) and SRS-RBC-03 (MFA via the IdP).
 * Socialite is faked throughout — no live Keycloak is needed.
 */
class OidcLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.keycloak.base_url' => 'http://localhost:8180',
            'services.keycloak.realms' => 'mais',
            'services.keycloak.client_id' => 'sisdata-web',
            'services.keycloak.client_secret' => 'test-secret',
            'services.keycloak.redirect' => 'http://localhost:3000/auth/oidc/callback',
            'services.keycloak.after_login_redirect' => 'http://localhost:3000/admin',
            'services.keycloak.login_page' => 'http://localhost:3000/login',
            'services.keycloak.post_logout_redirect' => 'http://localhost:3000/login',
        ]);
    }

    private function fakeIdpUser(array $claims = [], ?string $idToken = 'fake.id.token'): OidcUser
    {
        $claims = array_merge([
            'sub' => 'kc-sub-1111',
            'email' => 'siti.aminah@mais.test',
            'name' => 'Siti Aminah binti Abdullah',
            'preferred_username' => 'siti.aminah',
            'roles' => ['pegawai_daerah'],
        ], $claims);

        $user = (new OidcUser)->setRaw($claims)->map([
            'id' => $claims['sub'],
            'nickname' => $claims['preferred_username'],
            'name' => $claims['name'],
            'email' => $claims['email'],
        ]);

        $user->setToken('fake-access-token');
        $user->setAccessTokenResponseBody(array_filter(['access_token' => 'fake-access-token', 'id_token' => $idToken]));

        Socialite::fake('keycloak', $user);

        return $user;
    }

    public function test_SRS_RBC_01_oidc_redirect_sends_user_to_mais_realm_authorize_endpoint(): void
    {
        $response = $this->get('/auth/oidc/redirect');

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('http://localhost:8180/realms/mais/protocol/openid-connect/auth?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('sisdata-web', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('http://localhost:3000/auth/oidc/callback', $query['redirect_uri']);
        $this->assertStringContainsString('openid', $query['scope']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['code_challenge']);
    }

    public function test_SRS_RBC_03_mock_idp_realm_configures_totp_mfa_and_role_claims(): void
    {
        $realm = json_decode(file_get_contents(base_path('infra/keycloak/realm-mais.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('mais', $realm['realm']);
        $this->assertSame('SSO MAIS', $realm['displayName']);
        $this->assertSame('totp', $realm['otpPolicyType']);

        $client = collect($realm['clients'])->firstWhere('clientId', 'sisdata-web');
        $this->assertFalse($client['publicClient']);
        $this->assertContains('http://localhost:3000/auth/oidc/callback', $client['redirectUris']);
        $this->assertSame('http://localhost:3000/login', $client['attributes']['post.logout.redirect.uris']);

        $roleMapper = collect($client['protocolMappers'])->firstWhere('protocolMapper', 'oidc-usermodel-realm-role-mapper');
        $this->assertSame('roles', $roleMapper['config']['claim.name']);
        $this->assertSame('true', $roleMapper['config']['id.token.claim']);
        $this->assertSame('true', $roleMapper['config']['access.token.claim']);

        $mfaUsers = collect($realm['users'])->filter(fn ($u) => in_array('CONFIGURE_TOTP', $u['requiredActions'] ?? [], true));
        $this->assertNotEmpty($mfaUsers, 'At least one test user must be forced to enrol TOTP');
        $this->assertNotNull(collect($realm['users'])->firstWhere('email', 'admin@example.com'));
    }

    public function test_SRS_RBC_01_oidc_callback_logs_in_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        $this->fakeIdpUser(['sub' => 'kc-admin', 'email' => 'Admin@Example.com', 'roles' => ['pentadbir_sistem']]);

        $this->get('/auth/oidc/callback?code=abc&state=xyz')
            ->assertRedirect('http://localhost:3000/admin')
            ->assertSessionHas(OidcController::SESSION_ID_TOKEN, 'fake.id.token')
            ->assertSessionHas(OidcController::SESSION_ROLES, ['pentadbir_sistem']);

        $this->assertAuthenticatedAs($user);
        $this->assertSame('kc-admin', $user->fresh()->oidc_sub);
        $this->assertSame('admin', $user->fresh()->role);
        $this->assertSame(1, User::count());
    }

    public function test_SRS_RBC_01_oidc_callback_matches_user_by_sub_before_email(): void
    {
        $user = User::factory()->create(['email' => 'old-address@mais.test']);
        $user->forceFill(['oidc_sub' => 'kc-sub-1111'])->save();
        $this->fakeIdpUser(['sub' => 'kc-sub-1111', 'email' => 'new-address@mais.test']);

        $this->get('/auth/oidc/callback?code=abc&state=xyz')->assertRedirect('http://localhost:3000/admin');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_SRS_RBC_01_oidc_callback_creates_user_on_first_login(): void
    {
        $this->fakeIdpUser();

        $this->get('/auth/oidc/callback?code=abc&state=xyz')->assertRedirect('http://localhost:3000/admin');

        $user = User::where('email', 'siti.aminah@mais.test')->firstOrFail();
        $this->assertSame('Siti Aminah binti Abdullah', $user->name);
        $this->assertSame('user', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('kc-sub-1111', $user->oidc_sub);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_SRS_RBC_01_oidc_callback_rejects_email_linked_to_another_subject(): void
    {
        $user = User::factory()->create(['email' => 'siti.aminah@mais.test']);
        $user->forceFill(['oidc_sub' => 'some-other-sub'])->save();
        $this->fakeIdpUser(['sub' => 'kc-sub-1111']);

        $this->get('/auth/oidc/callback?code=abc&state=xyz')
            ->assertRedirect('http://localhost:3000/login?sso_error=konflik');

        $this->assertGuest();
    }

    public function test_SRS_RBC_01_oidc_callback_rejects_inactive_user(): void
    {
        User::factory()->create(['email' => 'siti.aminah@mais.test', 'is_active' => false]);
        $this->fakeIdpUser();

        $this->get('/auth/oidc/callback?code=abc&state=xyz')
            ->assertRedirect('http://localhost:3000/login?sso_error=tidak_aktif');

        $this->assertGuest();
    }

    public function test_SRS_RBC_01_oidc_callback_with_idp_error_redirects_to_login(): void
    {
        $this->get('/auth/oidc/callback?error=access_denied&state=xyz')
            ->assertRedirect('http://localhost:3000/login?sso_error=ditolak');

        $this->assertGuest();
    }

    public function test_SRS_RBC_01_oidc_logout_returns_idp_end_session_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('Referer', 'http://localhost:3000')
            ->withSession([OidcController::SESSION_ID_TOKEN => 'fake.id.token'])
            ->postJson(route('api.logout'));

        $response->assertOk();
        $url = $response->json('logout_url');
        $this->assertStringStartsWith('http://localhost:8180/realms/mais/protocol/openid-connect/logout?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('http://localhost:3000/login', $query['post_logout_redirect_uri']);
        $this->assertSame('sisdata-web', $query['client_id']);
        $this->assertSame('fake.id.token', $query['id_token_hint']);
        $this->assertGuest('web');
    }

    public function test_SRS_RBC_01_password_user_logout_still_returns_204(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Referer', 'http://localhost:3000')
            ->postJson(route('api.logout'))
            ->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_SRS_RBC_01_password_login_disabled_returns_403(): void
    {
        config(['auth.password_login' => false]);
        User::factory()->create(['email' => 'jane@example.com', 'password' => bcrypt('password123')]);

        $this->withHeader('Referer', 'http://localhost:3000')
            ->postJson(route('api.login'), ['email' => 'jane@example.com', 'password' => 'password123'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Log masuk menggunakan kata laluan telah dinyahdayakan. Sila log masuk melalui SSO MAIS.');

        $this->assertGuest();
    }

    public function test_SRS_RBC_01_password_registration_disabled_returns_403(): void
    {
        config(['auth.password_login' => false]);

        $this->withHeader('Referer', 'http://localhost:3000')
            ->postJson(route('api.register'), [
                'name' => 'Orang Baru',
                'email' => 'orang.baru@mais.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'orang.baru@mais.test']);
    }

    public function test_SRS_RBC_01_settings_expose_password_login_flag(): void
    {
        $this->getJson(route('api.settings.show'))->assertJsonPath('data.password_login_enabled', true);

        config(['auth.password_login' => false]);

        $this->getJson(route('api.settings.show'))->assertJsonPath('data.password_login_enabled', false);
    }
}
