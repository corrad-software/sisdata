<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Rbc\Services\ClaimMapper;
use App\Modules\Rbc\Services\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * SRS-RBC-01 (SSO via the MAIS OIDC IdP) / SRS-RBC-03 (MFA via the IdP).
 *
 * Authorization-code flow handled entirely by Laravel, so the session cookie
 * is issued exactly as for the password login. The frontend reaches these
 * routes through the Next.js /auth/* rewrite, keeping everything on the
 * :3000 origin. MFA (TOTP) is enforced by the IdP's own login flow; by the
 * time the callback runs the user has already satisfied it.
 */
class OidcController extends Controller
{
    /** Session keys used to build the IdP end-session URL on logout. */
    public const SESSION_ID_TOKEN = 'oidc.id_token';

    public const SESSION_ROLES = 'oidc.roles';

    public function redirect(): SymfonyRedirectResponse
    {
        return $this->driver()->redirect();
    }

    /**
     * PKCE (S256) is required by the sisdata-web client, and must be enabled
     * on both legs: redirect() stores the verifier, user() sends it.
     */
    protected function driver(): Provider
    {
        $driver = Socialite::driver('keycloak');

        // Socialite::fake() in tests hands back a non-OAuth2 fake; skip PKCE there.
        return $driver instanceof AbstractProvider
            ? $driver->scopes(['openid', 'profile', 'email'])->enablePKCE()
            : $driver;
    }

    public function callback(Request $request, LoginService $login, ClaimMapper $mapper): RedirectResponse
    {
        if ($request->filled('error')) {
            $login->failSso($request, null, null, 'sso_ditolak');

            return $this->failed('ditolak');
        }

        try {
            $oidcUser = $this->driver()->user();
        } catch (Throwable $e) {
            Log::warning('OIDC callback failed', ['exception' => $e->getMessage()]);
            $login->failSso($request, null, null, 'sso_gagal');

            return $this->failed('gagal');
        }

        $sub = (string) $oidcUser->getId();
        $email = $oidcUser->getEmail() ? Str::lower($oidcUser->getEmail()) : null;

        if ($sub === '') {
            $login->failSso($request, null, $email, 'sso_gagal');

            return $this->failed('gagal');
        }

        $user = User::where('oidc_sub', $sub)->first();

        if (! $user && $email) {
            $user = User::whereRaw('lower(email) = ?', [$email])->first();

            // The email belongs to an account already linked to a *different*
            // IdP subject — never silently re-link it.
            if ($user && $user->oidc_sub !== null && $user->oidc_sub !== $sub) {
                $login->failSso($request, $user, $email, 'sso_konflik');

                return $this->failed('konflik');
            }
        }

        if (! $user) {
            if (! $email) {
                $login->failSso($request, null, null, 'sso_tiada_emel');

                return $this->failed('tiada_emel');
            }

            // SRS-RBC-11: SSO-provisioned accounts have no SISDATA password at
            // all; SRS-RBC-09: no role until a claim-mapping rule grants one.
            $user = new User([
                'name' => $oidcUser->getName() ?: ($oidcUser->getNickname() ?: $email),
                'email' => $email,
                'jenis_pengesahan' => 'sso',
            ]);
            $user->forceFill(['email_verified_at' => now(), 'password' => null]);
        }

        if ($user->oidc_sub === null) {
            $user->forceFill(['oidc_sub' => $sub]);
        }

        $user->save();

        $tokenBody = property_exists($oidcUser, 'accessTokenResponseBody')
            ? (array) $oidcUser->accessTokenResponseBody
            : [];
        $raw = method_exists($oidcUser, 'getRaw') ? (array) $oidcUser->getRaw() : [];

        // SRS-RBC-02: identity claims (roles, bahagian, unit, jawatan) feed the
        // admin-configured mapping rules; status is checked first (SRS-RBC-15).
        if ($reason = $login->completeSso($request, $user, $raw, $mapper)) {
            return $this->failed($reason);
        }

        $request->session()->put(self::SESSION_ID_TOKEN, $tokenBody['id_token'] ?? '');
        $request->session()->put(self::SESSION_ROLES, array_values((array) ($raw['roles'] ?? [])));

        return redirect()->away(config('services.keycloak.after_login_redirect'));
    }

    /**
     * Keycloak RP-initiated logout URL, or null when the current session did
     * not come from the IdP (password login).
     */
    public static function endSessionUrl(Request $request): ?string
    {
        if (! $request->hasSession() || ! $request->session()->has(self::SESSION_ID_TOKEN)) {
            return null;
        }

        $config = config('services.keycloak');
        $params = [
            'post_logout_redirect_uri' => $config['post_logout_redirect'],
            'client_id' => $config['client_id'],
        ];

        $idToken = $request->session()->get(self::SESSION_ID_TOKEN);
        if ($idToken) {
            $params['id_token_hint'] = $idToken;
        }

        return rtrim($config['base_url'], '/').'/realms/'.$config['realms']
            .'/protocol/openid-connect/logout?'.http_build_query($params);
    }

    protected function failed(string $reason): RedirectResponse
    {
        return redirect()->away(config('services.keycloak.login_page').'?sso_error='.$reason);
    }
}
