<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Rbc\Events\SessionTerminated;
use App\Modules\Rbc\Models\Sesi;
use App\Modules\Rbc\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Server-side session control (SRS-RBC-17..20) on top of Laravel's database
 * session driver. Each authenticated session has a `sesi` row; that row is
 * the authority: EnforceSession logs out any request whose row has ended,
 * gone idle, or no longer matches the bound device/IP. Ending a session also
 * deletes its Laravel `sessions` row so the cookie is useless at once.
 */
class SessionManager
{
    public const SESSION_KEY = 'rbc.sesi_id';

    public const LOG_KELUAR = 'log_keluar';

    public const TAMAT_MASA = 'tamat_masa';

    public const PAKSA = 'paksa';

    public const STATUS_AKAUN = 'status_akaun';

    public const HAD_SERENTAK = 'had_serentak';

    public const IKATAN_GAGAL = 'ikatan_gagal';

    public function __construct(private SecurityPolicy $policy) {}

    /**
     * Called right after a successful login + session id regeneration.
     * Enforces the concurrent-session limit by ending the oldest sessions.
     */
    public function start(Request $request, User $user, string $method): Sesi
    {
        $this->expireIdleFor($user);

        $limit = max(1, $this->policy->int('sesi_had_serentak'));
        $active = Sesi::query()->aktif()->where('user_id', $user->id)->orderBy('tarikh_aktiviti_akhir')->get();

        foreach ($active->take(max(0, $active->count() - $limit + 1)) as $old) {
            $this->terminate($old, self::HAD_SERENTAK);
        }

        $now = Carbon::now();
        $sesi = Sesi::query()->create([
            'session_id' => $request->session()->getId(),
            'user_id' => $user->id,
            'kaedah_log_masuk' => $method,
            'alamat_ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'sidik_peranti' => UserAgent::parse($request->userAgent())->fingerprint(),
            'tarikh_mula' => $now,
            'tarikh_aktiviti_akhir' => $now,
        ]);

        $request->session()->put(self::SESSION_KEY, $sesi->id);

        return $sesi;
    }

    public function current(Request $request): ?Sesi
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);

        return $id ? Sesi::query()->find($id) : null;
    }

    public function touch(Sesi $sesi, Request $request): void
    {
        $sesi->forceFill([
            'tarikh_aktiviti_akhir' => Carbon::now(),
            'session_id' => $request->session()->getId(),
        ])->save();
    }

    public function idleTimeoutSeconds(): int
    {
        return max(1, $this->policy->int('sesi_tamat_minit')) * 60;
    }

    public function isIdle(Sesi $sesi): bool
    {
        return $sesi->tarikh_aktiviti_akhir->lt(Carbon::now()->subSeconds($this->idleTimeoutSeconds()));
    }

    public function remainingSeconds(Sesi $sesi): int
    {
        $expires = $sesi->tarikh_aktiviti_akhir->copy()->addSeconds($this->idleTimeoutSeconds());

        return max(0, (int) ceil(Carbon::now()->diffInSeconds($expires, false)));
    }

    /**
     * SRS-RBC-20: the session is bound to the browser fingerprint and to the
     * IP address (exact, same /24 (IPv4) or /64 (IPv6), or off — policy).
     */
    public function bindingMismatch(Sesi $sesi, Request $request): bool
    {
        if ($this->policy->bool('sesi_ikat_peranti') && $sesi->sidik_peranti
            && $sesi->sidik_peranti !== UserAgent::parse($request->userAgent())->fingerprint()) {
            return true;
        }

        $mode = (string) $this->policy->get('sesi_ikat_ip');
        $ip = (string) $request->ip();
        $bound = (string) $sesi->alamat_ip;

        if ($mode === 'tiada' || $bound === '' || $ip === '' || $ip === $bound) {
            return false;
        }

        // 127.0.0.1 vs ::1 — the same machine (local dev, or the Next.js
        // server rendering on the user's behalf).
        $loopback = ['127.0.0.0/8', '::1/128'];
        if (IpUtils::checkIp($ip, $loopback) && IpUtils::checkIp($bound, $loopback)) {
            return false;
        }

        if ($mode === 'tepat') {
            return true;
        }

        $prefix = str_contains($bound, ':') ? '/64' : '/24';

        return ! IpUtils::checkIp($ip, $bound.$prefix);
    }

    public function terminate(Sesi $sesi, string $method, ?User $actor = null): void
    {
        if ($sesi->tarikh_tamat !== null) {
            return;
        }

        $sesi->forceFill([
            'tarikh_tamat' => Carbon::now(),
            'kaedah_tamat' => $method,
            'ditamatkan_oleh' => $actor?->id,
        ])->save();

        $this->destroyLaravelSession($sesi->session_id);

        SessionTerminated::dispatch($sesi->user, $sesi->id, $method, $actor);
    }

    /**
     * SRS-RBC-17 / acceptance #2: end every session and revoke every API
     * token of a user immediately (status change, admin action).
     */
    public function terminateAllFor(User $user, string $method, ?User $actor = null): int
    {
        $count = 0;

        foreach (Sesi::query()->aktif()->where('user_id', $user->id)->get() as $sesi) {
            $this->terminate($sesi, $method, $actor);
            $count++;
        }

        // Sessions that predate MOD-RBC have no `sesi` row — remove them too.
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->id)
                ->delete();
        }

        return $count;
    }

    /** Mark idle sessions as ended (scheduled sweep, so the admin list is accurate). */
    public function expireIdle(): int
    {
        $cutoff = Carbon::now()->subSeconds($this->idleTimeoutSeconds());
        $count = 0;

        Sesi::query()->aktif()->where('tarikh_aktiviti_akhir', '<', $cutoff)->with('user')->chunkById(200, function ($rows) use (&$count) {
            foreach ($rows as $sesi) {
                $this->terminate($sesi, self::TAMAT_MASA);
                $count++;
            }
        });

        return $count;
    }

    private function expireIdleFor(User $user): void
    {
        $cutoff = Carbon::now()->subSeconds($this->idleTimeoutSeconds());

        foreach (Sesi::query()->aktif()->where('user_id', $user->id)->where('tarikh_aktiviti_akhir', '<', $cutoff)->get() as $sesi) {
            $this->terminate($sesi, self::TAMAT_MASA);
        }
    }

    private function destroyLaravelSession(string $sessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->delete();
    }
}
