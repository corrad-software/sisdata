<?php

namespace App\Modules\Adm\Http\Middleware;

use App\Modules\Rbc\Contracts\AccessControl;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `adm.boleh:<permission>[,<permission>…]` — passes if the user holds ANY of
 * the listed permissions, asked of MOD-RBC's AccessControl (SRS-GEN-01).
 */
class AdmBoleh
{
    public function __construct(private AccessControl $access) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if ($user && $this->access->allows($user, $permission)) {
                return $next($request);
            }
        }

        throw new AuthorizationException(__('This action is unauthorized.'));
    }
}
