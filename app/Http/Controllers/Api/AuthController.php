<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\Rbc\Events\UserLoggedOut;
use App\Modules\Rbc\Services\LoginService;
use App\Modules\Rbc\Services\PasswordPolicy;
use App\Modules\Rbc\Services\SessionManager;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Password login / registration / logout. The rules (status, lockout,
 * logging, session) live in MOD-RBC's LoginService.
 */
class AuthController extends Controller
{
    public function login(Request $request, LoginService $login)
    {
        $this->ensurePasswordLoginEnabled();

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $login->attemptPassword($request, $credentials['email'], $credentials['password']);

        return new UserResource($user);
    }

    public function register(Request $request, PasswordPolicy $passwords, SessionManager $sessions)
    {
        $this->ensurePasswordLoginEnabled();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', 'max:255', $passwords->rule()],
        ]);

        // SRS-RBC-09: a self-registered internal account starts with no role.
        $user = DB::transaction(function () use ($validated, $passwords) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'jenis_pengesahan' => 'dalaman',
            ]);
            $passwords->setPassword($user, $validated['password']);

            return $user;
        });

        event(new Registered($user));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $sessions->start($request, $user, LoginService::METHOD_PASSWORD);

        return new UserResource($user);
    }

    public function logout(Request $request, SessionManager $sessions)
    {
        // Must be read before the session is invalidated. Null for password
        // logins, which keep the plain 204 response.
        $logoutUrl = OidcController::endSessionUrl($request);

        $user = $request->user();

        // SRS-RBC-19: end the session on the server, not just in the browser.
        if ($sesi = $sessions->current($request)) {
            $sessions->terminate($sesi, SessionManager::LOG_KELUAR, $user instanceof User ? $user : null);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user instanceof User) {
            UserLoggedOut::dispatch($user);
        }

        if ($logoutUrl) {
            // SRS-RBC-01: the frontend navigates here to end the IdP session too.
            return response()->json(['logout_url' => $logoutUrl]);
        }

        return response()->noContent();
    }

    /**
     * SRS-RBC-01: local password login can be switched off so that SSO MAIS
     * is the only way in (config auth.password_login / AUTH_PASSWORD_LOGIN).
     */
    protected function ensurePasswordLoginEnabled(): void
    {
        abort_unless(
            config('auth.password_login'),
            403,
            'Log masuk menggunakan kata laluan telah dinyahdayakan. Sila log masuk melalui SSO MAIS.'
        );
    }

    public function user(Request $request)
    {
        return new UserResource($request->user());
    }
}
