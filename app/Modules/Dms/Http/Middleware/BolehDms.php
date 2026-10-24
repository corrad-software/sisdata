<?php

namespace App\Modules\Dms\Http\Middleware;

use App\Modules\Rbc\Contracts\AccessControl;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `dms.boleh:<permission>` — authorises through MOD-RBC's AccessControl
 * (SRS-GEN-01). Equivalent to `can:` once RBC wires the Gate, but also works
 * in a worktree where RBC is a fallback.
 */
class BolehDms
{
    public function __construct(private AccessControl $access) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if ($user === null || ! $this->access->allows($user, $permission)) {
            throw new AuthorizationException(__('This action is unauthorized.'));
        }

        return $next($request);
    }
}
