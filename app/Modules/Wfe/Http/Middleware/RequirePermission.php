<?php

namespace App\Modules\Wfe\Http\Middleware;

use App\Modules\Rbc\Contracts\AccessControl;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `wfe.boleh:perm.a,perm.b` — passes when the user holds ANY of the listed
 * permissions, asked through RBC's AccessControl (SRS-GEN-01). Never a role
 * string check.
 */
class RequirePermission
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

        throw new AuthorizationException(__('wfe::wfe.error.tiada_kebenaran'));
    }
}
