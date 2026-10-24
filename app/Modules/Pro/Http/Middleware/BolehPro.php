<?php

namespace App\Modules\Pro\Http\Middleware;

use App\Modules\Rbc\Contracts\AccessControl;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `pro.boleh:<permission>[|<permission>…]` — authorises through MOD-RBC's AccessControl (SRS-GEN-01); any one permission suffices. */
class BolehPro
{
    public function __construct(private AccessControl $access) {}

    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        $allowed = $user !== null && collect(explode('|', $permissions))->contains(fn ($p) => $this->access->allows($user, $p));
        if (! $allowed) {
            throw new AuthorizationException(__('This action is unauthorized.'));
        }

        return $next($request);
    }
}
