<?php

namespace App\Modules\Aud\Listeners;

use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use App\Modules\Aud\Services\TrailAuditLogger;
use App\Modules\Rbc\Events\AccessDenied;
use App\Modules\Rbc\Events\AccountStatusChanged;
use App\Modules\Rbc\Events\LoginFailed;
use App\Modules\Rbc\Events\UserLoggedIn;
use App\Modules\Rbc\Events\UserLoggedOut;
use App\Modules\Rbc\Events\UserRolesChanged;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * SRS-AUD-02/19: log in (success + failure), log out, account status changes,
 * role assignment changes (pivot rows — invisible to the model listener) and
 * access denials. Listens to MOD-RBC's events and, as a safety net, to
 * Laravel's own auth events — so a login is audited even where RBC hasn't
 * added its dispatch yet. The same login reaching both is recorded once per
 * request.
 */
class RecordAuthActivity
{
    /** @var array<string, true> */
    private static array $seen = [];

    public function __construct(private TrailAuditLogger $logger) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(UserLoggedIn::class, fn (UserLoggedIn $e) => $this->loggedIn($e->user, $e->method));
        $events->listen(Login::class, fn (Login $e) => $e->user instanceof User ? $this->loggedIn($e->user, $this->methodFromRequest()) : null);

        $events->listen(LoginFailed::class, fn (LoginFailed $e) => $this->failed($e->user, $e->identifier, $e->reason));
        $events->listen(Failed::class, fn (Failed $e) => $this->failed(
            $e->user instanceof User ? $e->user : null,
            (string) ($e->credentials['email'] ?? $e->credentials['username'] ?? ''),
            'kelayakan_tidak_sah',
        ));

        $events->listen(UserLoggedOut::class, fn (UserLoggedOut $e) => $this->loggedOut($e->user));
        $events->listen(Logout::class, fn (Logout $e) => $e->user instanceof User ? $this->loggedOut($e->user) : null);

        $events->listen(AccountStatusChanged::class, fn (AccountStatusChanged $e) => $this->logger->record(
            'tukar_status', 'rbc', 'users', $e->user->getKey(), 'berjaya', $e->reason,
            ['status' => [$e->from, $e->to]],
        ));

        $events->listen(UserRolesChanged::class, fn (UserRolesChanged $e) => $this->logger->recordAs(
            $e->actor, 'tukar_peranan', 'rbc', 'users', $e->user->getKey(), 'berjaya', null,
            array_filter([
                'peranan_ditambah' => $e->added === [] ? null : [null, implode(', ', $e->added)],
                'peranan_dikeluarkan' => $e->removed === [] ? null : [implode(', ', $e->removed), null],
            ]),
            ['ditambah' => $e->added, 'dikeluarkan' => $e->removed, 'sumber' => $e->source],
        ));

        $events->listen(AccessDenied::class, fn (AccessDenied $e) => $this->logger->recordAs(
            $e->user, 'capaian_ditolak', 'rbc', null, null, 'ditolak', null, [],
            ['kebenaran' => $e->permission, 'sumber' => $e->resource],
        ));
    }

    /** Forget dedupe keys between requests (long-running workers / Octane / tests). */
    public static function reset(): void
    {
        self::$seen = [];
    }

    private function once(string $key): bool
    {
        $requestId = AssignRequestId::current();
        if ($requestId === null) {
            return true; // outside an HTTP request: nothing to dedupe against
        }

        $key .= '|'.$requestId;
        if (isset(self::$seen[$key])) {
            return false;
        }

        return self::$seen[$key] = true;
    }

    private function loggedIn(User $user, string $method): void
    {
        if ($this->once('masuk:'.$user->getKey())) {
            $this->logger->recordAs($user, 'log_masuk', 'rbc', 'users', $user->getKey(), 'berjaya', null, [], ['kaedah' => $method]);
        }
    }

    private function failed(?User $user, string $identifier, string $reason): void
    {
        if ($this->once('gagal:'.$identifier)) {
            $this->logger->recordAs(
                $user, 'log_masuk', 'rbc', 'users', $user?->getKey(), 'gagal', null, [],
                ['pengecam' => $identifier, 'sebab_gagal' => $reason, 'kaedah' => $this->methodFromRequest()],
                actorIdentifier: $identifier,
            );
        }
    }

    private function loggedOut(User $user): void
    {
        if ($this->once('keluar:'.$user->getKey())) {
            $this->logger->recordAs($user, 'log_keluar', 'rbc', 'users', $user->getKey());
        }
    }

    private function methodFromRequest(): string
    {
        $path = app()->bound('request') ? (string) app('request')->path() : '';

        return str_contains($path, 'oidc') ? 'sso' : 'kata_laluan';
    }
}
