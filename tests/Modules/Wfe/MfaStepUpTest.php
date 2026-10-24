<?php

namespace Tests\Modules\Wfe;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\StepUp;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User as OidcUser;

/** SRS-WFE-19: MFA step-up for critical stages via SSO MAIS (Keycloak). */
class MfaStepUpTest extends WfeTestCase
{
    private User $approver;

    private string $instanceId;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.keycloak.base_url' => 'http://localhost:8180',
            'services.keycloak.realms' => 'mais',
            'services.keycloak.client_id' => 'sisdata-web',
            'services.keycloak.client_secret' => 'test-secret',
            'services.keycloak.redirect' => 'http://localhost:3000/auth/oidc/callback',
        ]);

        $this->approver = $this->user(null, [], null, ['oidc_sub' => 'kc-sub-approver']);
        $this->activeFlow($this->structure('ujian.kritikal', [
            $this->stage('pelupusan', [$this->approver], ['perlu_mfa' => true]),
        ]));
        $this->instanceId = $this->engine()->start('ujian.kritikal', 'rekod_ujian', 1, $this->user());
    }

    private function task(): Tugasan
    {
        return Tugasan::where('id_instans', $this->instanceId)->firstOrFail();
    }

    /** Acceptance criterion 4. */
    public function test_SRS_WFE_19_critical_stage_cannot_be_approved_without_mfa(): void
    {
        $this->act($this->approver, $this->task()->id, 'lulus')
            ->assertForbidden()->assertJsonPath('code', 'MFA_DIPERLUKAN');
        $this->assertSame(Instans::BERJALAN, Instans::find($this->instanceId)->status);
    }

    public function test_SRS_WFE_19_stale_step_up_is_not_accepted(): void
    {
        $this->withMfa(now()->subMinutes(11));
        $this->act($this->approver, $this->task()->id, 'lulus')->assertForbidden()->assertJsonPath('code', 'MFA_DIPERLUKAN');
    }

    public function test_SRS_WFE_19_fresh_step_up_allows_the_decision_and_is_recorded(): void
    {
        $this->withMfa(now()->subMinutes(2));
        $this->act($this->approver, $this->task()->id, 'lulus')->assertOk();

        $this->assertSame(Instans::LULUS, Instans::find($this->instanceId)->status);
        $entry = Instans::find($this->instanceId)->sejarah()->where('peristiwa', 'lulus')->firstOrFail();
        $this->assertNotNull($entry->mfa_pada);
    }

    public function test_SRS_WFE_19_step_up_redirects_to_idp_with_forced_reauthentication(): void
    {
        $response = $this->actingAs($this->approver)->get('/auth/wfe/stepup?kembali=/admin/wfe/tugasan/5');
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('http://localhost:8180/realms/mais/protocol/openid-connect/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('login', $query['prompt']);
        $this->assertSame('0', $query['max_age']);
        $this->assertSame('http://localhost:3000/auth/wfe/stepup/callback', $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame($this->approver->email, $query['login_hint']);
    }

    public function test_SRS_WFE_19_callback_records_step_up_for_same_subject_with_fresh_auth_time(): void
    {
        $this->actingAs($this->approver)->withSession([StepUp::SESSION_PENDING => now()->timestamp, StepUp::SESSION_RETURN => '/admin/wfe/tugasan/5']);
        $this->fakeIdp('kc-sub-approver', now()->timestamp + 5);

        $this->get('/auth/wfe/stepup/callback?code=x&state=y')->assertRedirect('http://localhost:3000/admin/wfe/tugasan/5?mfa=ok');
        $this->assertNotNull(session(StepUp::SESSION_AT));
        $this->assertSame('mfa_step_up', collect($this->audited)->last()['action']);

        $this->getJson('/api/v1/wfe/mfa')->assertOk()->assertJsonPath('data.sah', true);
        $this->act($this->approver, $this->task()->id, 'lulus')->assertOk();
    }

    public function test_SRS_WFE_19_callback_rejects_other_subject_or_old_authentication(): void
    {
        $this->actingAs($this->approver)->withSession([StepUp::SESSION_PENDING => now()->timestamp, StepUp::SESSION_RETURN => '/admin/wfe/tugasan']);
        $this->fakeIdp('kc-sub-someone-else', now()->timestamp);
        $this->get('/auth/wfe/stepup/callback?code=x&state=y')->assertRedirect('http://localhost:3000/admin/wfe/tugasan?mfa=gagal');
        $this->assertNull(session(StepUp::SESSION_AT));

        // an SSO-cookie login from an hour ago does not count as a step-up
        $this->withSession([StepUp::SESSION_PENDING => now()->timestamp, StepUp::SESSION_RETURN => '/admin/wfe/tugasan']);
        $this->fakeIdp('kc-sub-approver', now()->subHour()->timestamp);
        $this->get('/auth/wfe/stepup/callback?code=x&state=y')->assertRedirect('http://localhost:3000/admin/wfe/tugasan?mfa=gagal');
        $this->assertNull(session(StepUp::SESSION_AT));
    }

    public function test_SRS_WFE_19_otp_evidence_can_be_required(): void
    {
        /** @var Parameters $parameters */
        $parameters = $this->app->make(Parameters::class);
        $parameters->declare('wfe.mfa_wajib_otp', 'bool', true, 'x', true);
        $stepUp = app(StepUp::class);

        $this->assertSame('tiada_otp', $stepUp->rejectReason(['sub' => 's', 'auth_time' => now()->timestamp, 'acr' => '1', 'amr' => ['pwd']], 's', now()->timestamp));
        $this->assertNull($stepUp->rejectReason(['sub' => 's', 'auth_time' => now()->timestamp, 'acr' => '1', 'amr' => ['pwd', 'otp']], 's', now()->timestamp));
        $this->assertNull($stepUp->rejectReason(['sub' => 's', 'auth_time' => now()->timestamp, 'acr' => 'mfa'], 's', now()->timestamp));
    }

    public function test_SRS_WFE_19_password_only_accounts_cannot_step_up(): void
    {
        $local = $this->user(null, [], null, ['oidc_sub' => null]);
        $this->actingAs($local)->get('/auth/wfe/stepup?kembali=/admin/wfe/tugasan/1')
            ->assertRedirect('http://localhost:3000/admin/wfe/tugasan/1?mfa=tiada_sso');
        $this->actingAs($local)->getJson('/api/v1/wfe/mfa')->assertJsonPath('data.sso', false)->assertJsonPath('data.sah', false);
    }

    public function test_SRS_WFE_19_return_path_cannot_be_an_open_redirect(): void
    {
        $local = $this->user(null, [], null, ['oidc_sub' => null]);
        $this->actingAs($local)->get('/auth/wfe/stepup?kembali=//evil.example.com/x')
            ->assertRedirect('http://localhost:3000/admin/wfe/tugasan?mfa=tiada_sso');
    }

    private function fakeIdp(string $sub, int $authTime): void
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'auth_time' => $authTime, 'acr' => '1', 'amr' => ['pwd', 'otp']])), '+/', '-_'), '=');
        $user = (new OidcUser)->setRaw(['sub' => $sub])->map(['id' => $sub, 'email' => $this->approver->email]);
        $user->setToken('t');
        $user->setAccessTokenResponseBody(['access_token' => 't', 'id_token' => 'eyJhbGciOiJSUzI1NiJ9.'.$payload.'.sig']);
        Socialite::fake('keycloak', $user);
        Carbon::setTestNow(now());
    }
}
