<?php

namespace App\Modules\Rbc\Http\Middleware;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Services\SessionManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS-RBC-17..20 on every session-authenticated API request: the SESI row
 * must still be open, the account still Aktif, the session not idle past
 * the configured timeout, and the browser/IP still the bound ones.
 * Otherwise the session is ended server-side and the request gets 401
 * `SESSION_ENDED`. Requests authenticated otherwise (API token, tests'
 * actingAs) are left alone.
 */
class EnforceSession
{
    /** Polling the remaining time must not count as activity. */
    private const PASSIVE_ROUTES = ['rbc.sesi.status'];

    public function __construct(private SessionManager $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $guard = Auth::guard('web');
        if (! $request->session()->has($guard->getName()) || ! $guard->check()) {
            return $next($request);
        }

        $user = $guard->user();
        $sesi = $this->sessions->current($request);

        $reason = match (true) {
            $sesi === null => SessionManager::TAMAT_MASA, // a pre-RBC session: sign in again
            $sesi->tarikh_tamat !== null => $sesi->kaedah_tamat ?? SessionManager::PAKSA,
            $sesi->user_id !== $user->getAuthIdentifier() => SessionManager::IKATAN_GAGAL,
            ($user->status_akaun ?? AccountStatus::AKTIF) !== AccountStatus::AKTIF => SessionManager::STATUS_AKAUN,
            $this->sessions->isIdle($sesi) => SessionManager::TAMAT_MASA,
            $this->sessions->bindingMismatch($sesi, $request) => SessionManager::IKATAN_GAGAL,
            default => null,
        };

        if ($reason !== null) {
            if ($sesi !== null && $sesi->tarikh_tamat === null) {
                $this->sessions->terminate($sesi, $reason);
            }

            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return new JsonResponse([
                'message' => __('rbc::rbc.sesi.tamat.'.$reason),
                'code' => 'SESSION_ENDED',
                'reason' => $reason,
                'request_id' => AssignRequestId::current(),
            ], 401);
        }

        if (! $request->routeIs(...self::PASSIVE_ROUTES)) {
            $this->sessions->touch($sesi, $request);
        }

        $request->attributes->set('rbc.sesi', $sesi);

        return $next($request);
    }
}
