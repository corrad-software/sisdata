<?php

namespace App\Modules\Pro\Http\Middleware;

use App\Modules\Pro\Services\KunciApiService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * `pro.api:<skop>` — for requests authenticated with an API key (Sanctum
 * personal access token): the key must carry `pro:api` + the scope, must not
 * be revoked, is rate-limited per key, and every call is logged
 * (SRS-PRO-29). Browser-session requests pass straight through.
 */
class KunciApiPro
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = $request->user()?->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $policy = DB::table('kunci_api_pro')->where('id_token', $token->getKey())->first();
        if ($policy === null || $policy->dibatalkan_pada !== null || ! $token->can(KunciApiService::BASE_ABILITY) || ! $token->can($scope)) {
            $this->log($token->getKey(), $policy?->sistem_pemanggil, $request, 403);
            throw new HttpException(403, __('pro::pro.api.skop_ditolak', ['skop' => $scope]));
        }

        $key = 'pro-api:'.$token->getKey();
        $limit = max(1, (int) $policy->had_kadar_seminit);
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $this->log($token->getKey(), $policy->sistem_pemanggil, $request, 429);
            throw new HttpException(429, __('pro::pro.api.had_kadar', ['had' => $limit]), null, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        $request->attributes->set('pro_api_sistem', $policy->sistem_pemanggil);
        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $limit));
        $this->log($token->getKey(), $policy->sistem_pemanggil, $request, $response->getStatusCode());

        return $response;
    }

    private function log(mixed $tokenId, ?string $system, Request $request, int $status): void
    {
        DB::table('log_penggunaan_api')->insert([
            'id_token' => (int) $tokenId,
            'sistem_pemanggil' => $system,
            'kaedah' => $request->method(),
            'laluan' => substr('/'.$request->path(), 0, 255),
            'kod_status' => $status,
            'alamat_ip' => $request->ip(),
            'tarikh_masa' => now(),
        ]);
    }
}
