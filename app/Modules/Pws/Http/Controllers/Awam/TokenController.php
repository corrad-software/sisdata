<?php

namespace App\Modules\Pws\Http\Controllers\Awam;

use App\Modules\Pws\Services\KlienService;
use App\Modules\Pws\Services\LogIntegrasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * POST /api/v1/pws/awam/token — OAuth 2.0 client-credentials grant for the
 * portal server (SRS-PWS-23, SRS-INT-03). Credentials in the body
 * (client_id / client_secret) or HTTP Basic. Failed attempts are limited per
 * IP and per client id; the error never says which part was wrong.
 */
class TokenController
{
    public function __invoke(Request $request, KlienService $clients, LogIntegrasi $log): JsonResponse
    {
        $started = microtime(true);
        $id = (string) ($request->input('client_id') ?? $request->getUser() ?? '');
        $secret = (string) ($request->input('client_secret') ?? $request->getPassword() ?? '');
        $grant = (string) $request->input('grant_type', 'client_credentials');

        $ipKey = 'pws:token:ip:'.sha1((string) $request->ip());
        $idKey = 'pws:token:id:'.sha1($id);
        if (RateLimiter::tooManyAttempts($ipKey, 20) || RateLimiter::tooManyAttempts($idKey, 10)) {
            $log->write('masuk', 'POST', $request->path(), 429, null, null, ['client_id' => $id], 'had_kadar', $request->ip());

            return response()->json(['message' => __('pws::pws.ralat.had_kadar'), 'code' => 429], 429);
        }

        $issued = $grant === 'client_credentials' && $id !== '' && $secret !== '' ? $clients->issueToken($id, $secret, $request->ip()) : null;
        if ($issued === null) {
            RateLimiter::hit($ipKey, 900);
            RateLimiter::hit($idKey, 900);
            $log->write('masuk', 'POST', $request->path(), 401, (int) ((microtime(true) - $started) * 1000), null, ['client_id' => $id], 'kelayakan_ditolak', $request->ip());

            return response()->json(['message' => __('pws::pws.ralat.kelayakan'), 'code' => 401, 'error' => 'invalid_client'], 401);
        }
        [$klien, $token, $ttl] = $issued;
        RateLimiter::clear($idKey);
        LogIntegrasi::forClient($klien->id);
        $log->write('masuk', 'POST', $request->path(), 200, (int) ((microtime(true) - $started) * 1000), null, ['client_id' => $id], null, $request->ip());
        LogIntegrasi::forClient(null);

        return response()->json([
            'access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $ttl, 'scope' => implode(' ', $klien->skop),
        ])->header('Cache-Control', 'no-store');
    }
}
