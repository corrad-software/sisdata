<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $this->ensurePasswordLoginEnabled();

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    public function register(Request $request)
    {
        $this->ensurePasswordLoginEnabled();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function logout(Request $request)
    {
        // Must be read before the session is invalidated. Null for password
        // logins, which keep the plain 204 response.
        $logoutUrl = OidcController::endSessionUrl($request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

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
