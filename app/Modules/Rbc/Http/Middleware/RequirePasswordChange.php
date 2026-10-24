<?php

namespace App\Modules\Rbc\Http\Middleware;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Rbc\Services\LoginService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS-RBC-12: after a login with an expired / admin-set password, the
 * session may only fetch the profile, change the password or log out.
 */
class RequirePasswordChange
{
    private const ALLOWED = ['api.user', 'api.logout', 'rbc.kata_laluan.tukar', 'rbc.sesi.status', 'rbc.sesi.lanjut'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()
            && $request->session()->get(LoginService::MUST_CHANGE_KEY)
            && ! $request->routeIs(...self::ALLOWED)) {
            return new JsonResponse([
                'message' => __('rbc::rbc.kata_laluan.wajib_tukar'),
                'code' => 'PASSWORD_CHANGE_REQUIRED',
                'request_id' => AssignRequestId::current(),
            ], 403);
        }

        return $next($request);
    }
}
