<?php

namespace App\Modules\Wfe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Wfe\Services\StepUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * SRS-WFE-19 MFA step-up through SSO MAIS (Keycloak realm "mais").
 *
 * GET /auth/wfe/stepup?kembali=/admin/wfe/tugasan/12 sends the signed-in
 * user back to the IdP with prompt=login and max_age=0, so the IdP runs its
 * full login flow again (password + TOTP for users with OTP configured) even
 * if an SSO session exists. The callback checks the new ID token belongs to
 * the same IdP subject and that auth_time is after the step-up began, then
 * stores the step-up time in the session for the engine to check.
 *
 * Password-only accounts (no oidc_sub) cannot step up: they are sent back
 * with ?mfa=tiada_sso and cannot act on perlu_mfa stages.
 *
 * Kept separate from RBC's OidcController (login) on purpose; it needs the
 * redirect URI {FRONTEND_URL}/auth/wfe/stepup/callback registered on the
 * Keycloak client sisdata-web.
 */
class StepUpController extends Controller
{
    public function __construct(private StepUp $stepUp, private AuditLogger $audit) {}

    /** JSON: is there a fresh step-up in this session? */
    public function status(Request $request): JsonResponse
    {
        $at = $this->stepUp->verifiedAt($request);
        $fresh = $this->stepUp->isFresh($at);

        return response()->json(['data' => [
            'sah' => $fresh,
            'pada' => $at?->toIso8601String(),
            'tamat_pada' => $fresh ? $at->copy()->addMinutes($this->stepUp->windowMinutes())->toIso8601String() : null,
            'tempoh_minit' => $this->stepUp->windowMinutes(),
            'sso' => $request->user()->oidc_sub !== null,
        ]]);
    }

    public function redirect(Request $request): SymfonyRedirectResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $back = $this->safeReturn((string) $request->query('kembali', '/admin/wfe/tugasan'));

        if (! $user) {
            return redirect()->away($this->frontend('/login'));
        }
        if ($user->oidc_sub === null) {
            return redirect()->away($this->frontend($back, 'tiada_sso'));
        }

        $request->session()->put(StepUp::SESSION_PENDING, now()->timestamp);
        $request->session()->put(StepUp::SESSION_RETURN, $back);

        $params = ['prompt' => 'login', 'max_age' => '0'];
        if ($user->email) {
            $params['login_hint'] = $user->email;
        }
        $acr = $this->stepUp->acrValues();
        if ($acr !== '') {
            $params['acr_values'] = $acr;
        }

        $driver = $this->driver();
        if ($driver instanceof AbstractProvider) {
            $driver->with($params);
        }

        return $driver->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $back = $this->safeReturn((string) $request->session()->pull(StepUp::SESSION_RETURN, '/admin/wfe/tugasan'));
        $startedAt = (int) $request->session()->pull(StepUp::SESSION_PENDING, 0);

        if (! $user || $user->oidc_sub === null || $startedAt === 0 || $request->filled('error')) {
            return redirect()->away($this->frontend($back, 'gagal'));
        }

        try {
            $oidcUser = $this->driver()->user();
        } catch (Throwable $e) {
            Log::warning('WFE step-up callback failed', ['exception' => $e->getMessage()]);

            return redirect()->away($this->frontend($back, 'gagal'));
        }

        $body = property_exists($oidcUser, 'accessTokenResponseBody') ? (array) $oidcUser->accessTokenResponseBody : [];
        $claims = isset($body['id_token']) && is_string($body['id_token']) ? StepUp::decodeJwtPayload($body['id_token']) : [];
        $claims['sub'] ??= (string) $oidcUser->getId();

        $reason = $this->stepUp->rejectReason($claims, (string) $user->oidc_sub, $startedAt);
        if ($reason !== null) {
            $this->audit->record('mfa_step_up', 'wfe', 'users', $user->id, status: 'gagal', reason: $reason);

            return redirect()->away($this->frontend($back, $reason === 'tiada_otp' ? 'tiada_otp' : 'gagal'));
        }

        $request->session()->put(StepUp::SESSION_AT, now()->timestamp);
        $request->session()->put(StepUp::SESSION_INFO, ['acr' => $claims['acr'] ?? null, 'amr' => $claims['amr'] ?? null]);
        $this->audit->record('mfa_step_up', 'wfe', 'users', $user->id, meta: ['acr' => $claims['acr'] ?? null, 'amr' => $claims['amr'] ?? null]);

        return redirect()->away($this->frontend($back, 'ok'));
    }

    private function driver(): Provider
    {
        $driver = Socialite::driver('keycloak');

        return $driver instanceof AbstractProvider
            ? $driver->redirectUrl($this->frontend('/auth/wfe/stepup/callback'))->scopes(['openid', 'profile', 'email'])->enablePKCE()
            : $driver;
    }

    /** Only same-site admin paths may be returned to (no open redirect). */
    private function safeReturn(string $path): string
    {
        return preg_match('#^/admin(/[A-Za-z0-9_\-/]*)?$#', $path) === 1 ? $path : '/admin/wfe/tugasan';
    }

    private function frontend(string $path, ?string $mfa = null): string
    {
        $redirect = (string) config('services.keycloak.redirect');
        $parts = parse_url($redirect);
        $origin = isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : 'http://localhost:3000';

        return $origin.$path.($mfa !== null ? '?mfa='.$mfa : '');
    }
}
