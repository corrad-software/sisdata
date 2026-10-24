<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `admin` route middleware alias — now permission-based (MOD-RBC):
 * `admin` requires "rbc.pentadbiran.akses" (site settings, platform status);
 * `admin:<permission>` requires that permission. Both go through the Gate →
 * AccessControl, never the legacy `role` string. Prefer `can:<permission>`
 * on new routes.
 */
class EnsureUserIsAdmin
{
    public const DEFAULT_PERMISSION = 'rbc.pentadbiran.akses';

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        if (! $request->user()?->can($permission ?? self::DEFAULT_PERMISSION)) {
            abort(403, __('This action is unauthorized.'));
        }

        return $next($request);
    }
}
