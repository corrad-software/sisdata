<?php

namespace App\Modules\Kba\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SRS-KBA-19: AI prompt rate limits per user (per minute and per day) with
 * per-role overrides — the highest limit among the user's roles applies —
 * and one shared bucket for anonymous (portal) callers. Guards against abuse
 * and bulk extraction of the corpus.
 */
class HadKadar
{
    public function __construct(private Parameters $parameters, private AccessControl $access) {}

    /** @return int|null seconds to wait when over the limit, null when allowed (and counted) */
    public function attempt(?User $user): ?int
    {
        if ($user === null) {
            return $this->hit('kba:prompt:awam', max(1, (int) $this->parameters->get('kba.had_prompt_awam_seminit', 60)), 60);
        }

        $perMinute = max(1, (int) $this->parameters->get('kba.had_prompt_seminit', 10));
        $overrides = $this->parameters->get('kba.had_peranan', []);
        if (is_string($overrides)) {
            $overrides = json_decode($overrides, true) ?: [];
        }
        foreach ($this->access->roles($user) as $role) {
            if (is_array($overrides) && isset($overrides[$role]) && (int) $overrides[$role] > 0) {
                $perMinute = max($perMinute, (int) $overrides[$role]);
            }
        }
        $perDay = max($perMinute, (int) $this->parameters->get('kba.had_prompt_sehari', 300));

        if (RateLimiter::tooManyAttempts('kba:prompt:h:'.$user->id, $perDay)) {
            return RateLimiter::availableIn('kba:prompt:h:'.$user->id);
        }
        if (($wait = $this->hit('kba:prompt:m:'.$user->id, $perMinute, 60)) !== null) {
            return $wait;
        }
        RateLimiter::hit('kba:prompt:h:'.$user->id, 86400);

        return null;
    }

    private function hit(string $key, int $max, int $decay): ?int
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return RateLimiter::availableIn($key);
        }
        RateLimiter::hit($key, $decay);

        return null;
    }
}
