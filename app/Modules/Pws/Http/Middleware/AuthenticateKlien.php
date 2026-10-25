<?php

namespace App\Modules\Pws\Http\Middleware;

use App\Modules\Pws\Models\KlienApi;
use App\Modules\Pws\Services\KlienService;
use App\Modules\Pws\Services\LogIntegrasi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public API gateway guard (SRS-PWS-23/24, SRS-INT-03/04/06):
 *  1. bearer token from the client-credentials grant, still valid, client
 *     active, source IP on the client's allowlist;
 *  2. the route's scope (`pws.klien:permohonan`);
 *  3. per-client rate limit (had_kadar_minit, admin-configurable);
 *  4. every call — accepted or refused — written to pws_log_integrasi.
 * Errors are deliberately generic (no hint whether a client id exists).
 */
class AuthenticateKlien
{
    public const ATTR = 'pws_klien';

    public function __construct(private KlienService $clients, private LogIntegrasi $log) {}

    public function handle(Request $request, Closure $next, string $scope = 'baca'): Response
    {
        $started = microtime(true);
        $klien = null;
        $response = null;

        try {
            $token = (string) $request->bearerToken();
            $klien = $token !== '' ? $this->clients->clientForToken($token, $request->ip()) : null;
            if ($klien === null) {
                return $response = $this->deny(401, __('pws::pws.ralat.tidak_sah'));
            }
            LogIntegrasi::forClient($klien->id);
            if (! $klien->ada($scope)) {
                return $response = $this->deny(403, __('pws::pws.ralat.skop'));
            }
            $key = 'pws:klien:'.$klien->id;
            if (RateLimiter::tooManyAttempts($key, max(1, $klien->had_kadar_minit))) {
                return $response = $this->deny(429, __('pws::pws.ralat.had_kadar'), ['Retry-After' => (string) RateLimiter::availableIn($key)]);
            }
            RateLimiter::hit($key, 60);
            $request->attributes->set(self::ATTR, $klien);

            return $response = $next($request);
        } finally {
            $status = $response?->getStatusCode();
            $this->log->write('masuk', $request->method(), $request->path(), $status, (int) ((microtime(true) - $started) * 1000),
                $request->route('rujukan') !== null ? (string) $request->route('rujukan') : null,
                $request->except(['fail']), $status !== null && $status >= 400 ? $this->message($response) : null, $request->ip(), strlen((string) $request->getContent()));
            LogIntegrasi::forClient(null);
        }
    }

    public static function klien(Request $request): KlienApi
    {
        $klien = $request->attributes->get(self::ATTR);
        abort_unless($klien instanceof KlienApi, 401);

        return $klien;
    }

    /**
     * End user's IP as forwarded by the authenticated portal server
     * (X-Pengguna-IP); falls back to the caller's IP. Only trusted because
     * the caller is an authenticated, allowlisted client.
     */
    public static function endUserIp(Request $request): string
    {
        $forwarded = trim((string) $request->header('X-Pengguna-IP', ''));

        return $forwarded !== '' && filter_var($forwarded, FILTER_VALIDATE_IP) !== false ? $forwarded : (string) $request->ip();
    }

    /** @param array<string, string> $headers */
    private function deny(int $status, string $message, array $headers = []): Response
    {
        return response()->json(['message' => $message, 'code' => $status], $status, $headers);
    }

    private function message(?Response $response): ?string
    {
        if ($response === null) {
            return null;
        }
        $body = json_decode((string) $response->getContent(), true);

        return is_array($body) && isset($body['message']) ? (string) $body['message'] : null;
    }
}
