<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Rbc\Models\KataLaluanSejarah;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Internal-account password policy (SRS-RBC-12, SRS-RBC-14): minimum
 * length (12), 3 of 4 character classes, expiry (90 days), last-5 reuse
 * ban, forced change on first login. Hashing is Laravel's bcrypt with
 * per-hash salt (never reversible). SSO accounts never get a password
 * (SRS-RBC-11).
 */
class PasswordPolicy
{
    public function __construct(private SecurityPolicy $policy) {}

    /** Validation rule for a new password. */
    public function rule(?User $user = null): ValidationRule
    {
        $policy = $this;

        return new class($policy, $user) implements ValidationRule
        {
            public function __construct(private PasswordPolicy $policy, private ?User $user) {}

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                foreach ($this->policy->problems((string) $value, $this->user) as $message) {
                    $fail($message);
                }
            }
        };
    }

    /** @return list<string> */
    public function problems(string $password, ?User $user = null): array
    {
        $problems = [];
        $min = $this->policy->int('kata_laluan_min_panjang');

        if (mb_strlen($password) < $min) {
            $problems[] = __('rbc::rbc.kata_laluan.pendek', ['min' => $min]);
        }

        $classes = (int) (bool) preg_match('/[a-z]/', $password)
            + (int) (bool) preg_match('/[A-Z]/', $password)
            + (int) (bool) preg_match('/\d/', $password)
            + (int) (bool) preg_match('/[^a-zA-Z\d]/', $password);

        if ($classes < 3) {
            $problems[] = __('rbc::rbc.kata_laluan.kompleksiti');
        }

        $localPart = $user?->email ? mb_strtolower((string) strtok($user->email, '@')) : '';
        if (mb_strlen($localPart) >= 4 && str_contains(mb_strtolower($password), $localPart)) {
            $problems[] = __('rbc::rbc.kata_laluan.mengandungi_emel');
        }

        if ($user && $this->wasUsedRecently($user, $password)) {
            $problems[] = __('rbc::rbc.kata_laluan.guna_semula', ['n' => $this->policy->int('kata_laluan_sejarah')]);
        }

        return $problems;
    }

    public function wasUsedRecently(User $user, string $password): bool
    {
        $n = $this->policy->int('kata_laluan_sejarah');
        if ($n <= 0 || ! $user->exists) {
            return false;
        }

        if ($user->password && Hash::check($password, $user->password)) {
            return true;
        }

        $hashes = KataLaluanSejarah::query()->where('user_id', $user->id)->latest('id')->limit($n)->pluck('kata_laluan');

        return $hashes->contains(fn ($hash) => Hash::check($password, $hash));
    }

    /**
     * Set a new password, keep the previous one in history.
     *
     * @param  bool  $forceChange  true when an administrator sets it (user must change at next login)
     */
    public function setPassword(User $user, string $plain, bool $forceChange = false): void
    {
        DB::transaction(function () use ($user, $plain, $forceChange) {
            if ($user->exists && $user->password) {
                KataLaluanSejarah::query()->create(['user_id' => $user->id, 'kata_laluan' => $user->password]);
            }

            $user->forceFill([
                'password' => Hash::make($plain),
                'kata_laluan_ditukar_pada' => Carbon::now(),
                'wajib_tukar_kata_laluan' => $forceChange,
            ])->save();

            $keep = max(1, $this->policy->int('kata_laluan_sejarah'));
            $stale = KataLaluanSejarah::query()->where('user_id', $user->id)->latest('id')->skip($keep)->take(PHP_INT_MAX)->pluck('id');
            if ($stale->isNotEmpty()) {
                KataLaluanSejarah::query()->whereIn('id', $stale)->delete();
            }
        });
    }

    /**
     * Must this internal account change its password before doing anything else?
     */
    public function mustChange(User $user): bool
    {
        if ($user->jenis_pengesahan !== 'dalaman') {
            return false;
        }

        if ($user->wajib_tukar_kata_laluan) {
            return true;
        }

        $days = $this->policy->int('kata_laluan_luput_hari');
        $changed = $user->kata_laluan_ditukar_pada ?? $user->created_at;

        return $days > 0 && $changed !== null && $changed->lt(Carbon::now()->subDays($days));
    }

    /** @throws ValidationException */
    public function change(User $user, string $current, string $new): void
    {
        if (! $user->password || ! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages(['kata_laluan_semasa' => [__('rbc::rbc.kata_laluan.semasa_salah')]]);
        }

        $problems = $this->problems($new, $user);
        if ($problems !== []) {
            throw ValidationException::withMessages(['kata_laluan' => $problems]);
        }

        $this->setPassword($user, $new);
    }
}
