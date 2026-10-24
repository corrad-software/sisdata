<?php

namespace App\Modules\Ref\Http\Middleware;

use App\Modules\Rbc\Contracts\AccessControl;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asks the central AccessControl contract (SRS-GEN-01) directly, so the
 * check works whether or not MOD-RBC has wired Laravel's Gate yet.
 * Equivalent to `can:<permission>` once it has.
 */
class RequirePermission
{
    public function __construct(private readonly AccessControl $access) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $this->access->allows($user, $permission)) {
            throw new AuthorizationException(__('This action is unauthorized.'));
        }

        return $next($request);
    }
}
