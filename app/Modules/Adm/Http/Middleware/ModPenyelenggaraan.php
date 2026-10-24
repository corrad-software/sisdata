<?php

namespace App\Modules\Adm\Http\Middleware;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Rbc\Contracts\AccessControl;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS-ADM-29: application-level maintenance mode (Laravel's `artisan down`
 * would lock admins out too). While parameter adm.mod_penyelenggaraan is on:
 *  - reads keep working and carry an X-Mod-Penyelenggaraan header so the UI
 *    shows the notice;
 *  - new transactions (POST/PUT/PATCH/DELETE) get 503 MAINTENANCE with the
 *    admin's notice;
 *  - holders of adm.penyelenggaraan.kemas_kini (administrators) can still do
 *    everything, and login/logout always work so they can get in.
 */
class ModPenyelenggaraan
{
    public const HEADER = 'X-Mod-Penyelenggaraan';

    /** Paths that must work during maintenance (relative, no leading slash). */
    private const ALWAYS = ['api/v1/login', 'api/v1/logout', 'api/v1/adm/penyelenggaraan', 'sanctum/csrf-cookie', 'auth/*'];

    public function __construct(private Parameters $parameters, private AccessControl $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->parameters->get('adm.mod_penyelenggaraan', false)) {
            return $next($request);
        }

        $isWrite = ! in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true);

        if ($isWrite && ! $request->is(...self::ALWAYS) && ! $this->isAdmin($request)) {
            return new JsonResponse([
                'message' => (string) $this->parameters->get('adm.notis_penyelenggaraan', __('adm::adm.penyelenggaraan.default_notice')),
                'code' => 'MAINTENANCE',
                'request_id' => AssignRequestId::current(),
            ], 503, [self::HEADER => '1', 'Retry-After' => '600']);
        }

        $response = $next($request);
        $response->headers->set(self::HEADER, '1');

        return $response;
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user() ?? $request->user('sanctum');

        return $user !== null && $this->access->allows($user, 'adm.penyelenggaraan.kemas_kini');
    }
}
