<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->failed('ditolak');
        }

        try {
            $oidcUser = $this->driver()->user();
        } catch (Throwable $e) {
            Log::warning('OIDC callback failed', ['exception' => $e->getMessage()]);

            return $this->failed('gagal');
        }

        $sub = (string) $oidcUser->getId();
        $email = $oidcUser->getEmail() ? Str::lower($oidcUser->getEmail()) : null;

        if ($sub === '') {
            return $this->failed('gagal');
        }

        $user = User::where('oidc_sub', $sub)->first();

        if (! $user && $email) {
            $user = User::whereRaw('lower(email) = ?', [$email])->first();

            // The email belongs to an account already linked to a *different*
            // IdP subject — never silently re-link it.
            if ($user && $user->oidc_sub !== null && $user->oidc_sub !== $sub) {
                return $this->failed('konflik');
            }
        }

        if (! $user) {
            if (! $email) {
                return $this->failed('tiada_emel');
            }

            $user = User::create([
                'name' => $oidcUser->getName() ?: ($oidcUser->getNickname() ?: $email),
                'email' => $email,
                // Unusable random password: SSO-provisioned accounts sign in
                // through the IdP only.
                'password' => Str::random(64),
                'role' => 'user',
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()]);
        }

        if (! $user->is_active) {
            return $this->failed('tidak_aktif');
        }

        if ($user->oidc_sub === null) {
            $user->forceFill(['oidc_sub' => $sub]);
        }

        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        $tokenBody = property_exists($oidcUser, 'accessTokenResponseBody')
            ? (array) $oidcUser->accessTokenResponseBody
            : [];
        $raw = method_exists($oidcUser, 'getRaw') ? (array) $oidcUser->getRaw() : [];

        $request->session()->put(self::SESSION_ID_TOKEN, $tokenBody['id_token'] ?? '');
        // Realm roles from the IdP ("roles" claim) — kept for the upcoming
        // SISDATA role mapping; not used for authorization yet.
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
