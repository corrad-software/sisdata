<?php

namespace App\Modules\Adm\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS-ADM-05: the admin API is usable with a Sanctum token for automation,
 * but only a token minted with the `adm:pentadbir` ability may reach it — an
 * integration/other-purpose token can't. SPA (cookie) sessions pass through;
 * the per-endpoint permission check applies either way.
 */
class TokenPentadbir
{
    public const ABILITY = 'adm:pentadbir';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can(self::ABILITY)) {
            throw new AuthorizationException(__('adm::adm.token.scope'));
        }

        return $next($request);
    }
}
