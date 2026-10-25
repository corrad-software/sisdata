<?php

namespace App\Modules\Pws\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for the public gateway /api/v1/pws/awam/* (SRS-PWS-23). Registered
 * as an OUTERMOST global middleware by PwsServiceProvider so it wraps the
 * app-wide HandleCors (which serves the SISDATA admin frontend origin with
 * credentials) and has the final word on these paths only:
 *  - the portal calls server-to-server (no Origin header), so the API key
 *    never reaches a browser;
 *  - a browser call or preflight is accepted only from the configured
 *    portal origin(s) — never the SISDATA admin frontend or any other site —
 *    and never with credentials.
 * Also selects the response language from Accept-Language (bilingual portal).
 */
class PortalCors
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/pws/awam', 'api/v1/pws/awam/*')) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');
        $allowed = (array) config('pws.asal_portal', []);
        if ($origin !== null && ! in_array($origin, $allowed, true)) {
            return response()->json(['message' => __('pws::pws.ralat.asal'), 'code' => 403], 403);
        }
        if ($origin !== null && $request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method')) {
            $preflight = response()->noContent();
            $preflight->headers->set('Access-Control-Allow-Methods', 'GET, POST');
            $preflight->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, Accept-Language, Idempotency-Key');
            $preflight->headers->set('Access-Control-Max-Age', '600');

            return $this->headers($preflight, $origin);
        }

        if (str_starts_with(strtolower((string) $request->header('Accept-Language', '')), 'en')) {
            app()->setLocale('en');
        }

        return $this->headers($next($request), $origin);
    }

    private function headers(Response $response, ?string $origin): Response
    {
        foreach (['Access-Control-Allow-Credentials', 'Access-Control-Allow-Origin', 'Access-Control-Expose-Headers'] as $h) {
            $response->headers->remove($h);
        }
        if ($origin !== null) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
