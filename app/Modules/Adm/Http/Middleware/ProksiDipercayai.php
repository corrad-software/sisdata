<?php

namespace App\Modules\Adm\Http\Middleware;

use App\Modules\Adm\Contracts\Parameters;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * SRS-ADM-26: the WAF / load balancer / reverse proxy addresses whose
 * X-Forwarded-For (and -Proto/-Host/-Port) are trusted come from parameter
 * adm.proksi_dipercayai (comma-separated IPs/CIDRs, or "*"), changeable with
 * second-admin approval. Runs before Laravel's TrustProxies, which reads
 * config('trustedproxy.proxies'), so $request->ip() — used by audit, logs,
 * rate limits — is the real client IP.
 *
 * This is the ONLY place trusted proxies are configured. Loopback
 * (127.0.0.1, ::1) is always trusted on top of the parameter: the Next.js
 * frontend proxies /api, /sanctum and /auth from the same host, and MOD-RBC's
 * login log and session binding need the browser's IP, not 127.0.0.1. Only a
 * process on the server itself can connect from loopback. Empty parameter =
 * trust loopback only.
 */
class ProksiDipercayai
{
    /** @var list<string> */
    public const LOOPBACK = ['127.0.0.1', '::1'];

    public function __construct(private Parameters $parameters) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $value = trim((string) $this->parameters->get('adm.proksi_dipercayai', ''));
        } catch (Throwable) {
            $value = '';
        }

        config(['trustedproxy.proxies' => $value === '*' || $value === '**'
            ? $value
            : array_values(array_unique([...self::LOOPBACK, ...array_filter(array_map(trim(...), explode(',', $value)))]))]);

        return $next($request);
    }
}
