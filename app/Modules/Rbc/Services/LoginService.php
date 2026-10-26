<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccountStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Both login paths (password and SSO MAIS) end here so the same rules
 * apply: only Aktif accounts get in (SRS-RBC-15/16), every attempt is
 * logged (SRS-RBC-21), internal accounts are locked after N failures
 * (SRS-RBC-13), the session id is regenerated and a SESI row is opened
 * (SRS-RBC-19/20).
 */
class LoginService
{
    public const METHOD_PASSWORD = 'kata_laluan';

    public const METHOD_SSO = 'sso';

    /** Session flag: the user must change the password before anything else. */
    public const MUST_CHANGE_KEY = 'rbc.wajib_tukar_kata_laluan';

    public function __construct(
        private LoginMonitor $monitor,
        private SessionManager $sessions,
        private AccountStatusService $statuses,
        private SecurityPolicy $policy,
        private PasswordPolicy $passwords,
    ) {}

    /**
     * @throws ValidationException (errors on "email", the field the login form shows)
     */
    public function attemptPassword(Request $request, string $email, string $password): User
    {
        // Coarse per-IP flood guard; per-account lockout is below.
        $ipKey = 'rbc-login|'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 30)) {
            $this->monitor->failure($request, null, $email, self::METHOD_PASSWORD, 'had_kadar');
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($ipKey), 'minutes' => ceil(RateLimiter::availableIn($ipKey) / 60)]),
            ]);
        }
        RateLimiter::hit($ipKey, 60);

        $user = User::query()->whereRaw('lower(email) = ?', [Str::lower($email)])->first();

        if (! $user) {
            Hash::check($password, '$2y$04$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); // equalise timing; dummy hash, not a secret // nosemgrep: detected-bcrypt-hash
            $this->fail($request, null, $email, 'pengguna_tidak_wujud', trans('auth.failed'));
        }

        if ($user->jenis_pengesahan === 'sso' || ! $user->password) {
            $this->fail($request, $user, $email, 'akaun_sso', __('rbc::rbc.log_masuk.akaun_sso'));
        }

        if ($user->dikunci_hingga && $user->dikunci_hingga->isFuture()) {
            $this->fail($request, $user, $email, 'akaun_dikunci', $this->lockedMessage($user));
        }

        if (! Hash::check($password, $user->password)) {
            $locked = $this->registerFailure($user);
            $this->fail($request, $user, $email, $locked ? 'akaun_dikunci' : 'kata_laluan_salah', $locked ? $this->lockedMessage($user) : trans('auth.failed'));
        }

        $status = $this->statuses->applyAutomaticRules($user);
        if ($status !== AccountStatus::AKTIF) {
            $this->fail($request, $user, $email, 'status_'.$status, __('rbc::rbc.log_masuk.status_'.$status));
        }

        RateLimiter::clear($ipKey);
        $this->completeLogin($request, $user, self::METHOD_PASSWORD);

        if ($this->passwords->mustChange($user)) {
            $request->session()->put(self::MUST_CHANGE_KEY, true);
        }

        return $user;
    }

    /**
     * SSO MAIS login (SRS-RBC-01/02): the IdP already authenticated the user
     * (and MFA). Returns null on success or a failure reason for the login page.
     *
     * @param  array<string, mixed>  $claims
     */
    public function completeSso(Request $request, User $user, array $claims, ClaimMapper $mapper): ?string
    {
        $status = $this->statuses->applyAutomaticRules($user);
        if ($status !== AccountStatus::AKTIF) {
            $this->monitor->failure($request, $user, $user->email, self::METHOD_SSO, 'status_'.$status);

            // Same detail the password login gives (status_* messages): gantung,
            // tamat_tempoh, or tidak_aktif for nyahaktif.
            return in_array($status, [AccountStatus::GANTUNG, AccountStatus::TAMAT_TEMPOH], true) ? $status : 'tidak_aktif';
        }

        $mapper->apply($user, $claims);

        $this->completeLogin($request, $user, self::METHOD_SSO);

        return null;
    }

    public function failSso(Request $request, ?User $user, ?string $identifier, string $reason): void
    {
        $this->monitor->failure($request, $user, $identifier, self::METHOD_SSO, $reason);
    }

    private function completeLogin(Request $request, User $user, string $method): void
    {
        // Never "remember me": a remember cookie would outlive the idle timeout (SRS-RBC-18).
        Auth::guard('web')->login($user);
        $request->session()->regenerate(); // SRS-RBC-20

        $user->forceFill([
            'tarikh_log_masuk_akhir' => Carbon::now(),
            'cubaan_gagal' => 0,
            'cubaan_gagal_pertama' => null,
            'dikunci_hingga' => null,
        ])->save();

        $this->sessions->start($request, $user, $method);
        $this->monitor->success($request, $user, $method);
    }

    /** SRS-RBC-13: N consecutive failures within the window lock the account. */
    private function registerFailure(User $user): bool
    {
        $window = $this->policy->int('kunci_tetingkap_minit');
        $threshold = $this->policy->int('kunci_ambang_cubaan');
        $now = Carbon::now();

        $first = $user->cubaan_gagal_pertama;
        $count = ($first && $first->gt($now->copy()->subMinutes($window))) ? $user->cubaan_gagal + 1 : 1;

        $attributes = ['cubaan_gagal' => $count, 'cubaan_gagal_pertama' => $count === 1 ? $now : $first];

        if ($count >= $threshold) {
            $attributes = [
                'cubaan_gagal' => 0,
                'cubaan_gagal_pertama' => null,
                'dikunci_hingga' => $now->copy()->addMinutes($this->policy->int('kunci_tempoh_minit')),
            ];
        }

        $user->forceFill($attributes)->save();

        return isset($attributes['dikunci_hingga']);
    }

    public function unlock(User $user): void
    {
        $user->forceFill(['dikunci_hingga' => null, 'cubaan_gagal' => 0, 'cubaan_gagal_pertama' => null])->save();
    }

    private function lockedMessage(User $user): string
    {
        $minutes = max(1, (int) ceil(Carbon::now()->diffInSeconds($user->dikunci_hingga, false) / 60));

        return __('rbc::rbc.log_masuk.dikunci', ['minit' => $minutes]);
    }

    /** @return never */
    private function fail(Request $request, ?User $user, string $identifier, string $reason, string $message): void
    {
        $this->monitor->failure($request, $user, $identifier, self::METHOD_PASSWORD, $reason);

        throw ValidationException::withMessages(['email' => $message]);
    }
}
