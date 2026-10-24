<?php

namespace App\Modules\Rbc\Http\Middleware;

use App\Models\User;
use App\Modules\Rbc\Events\AccessDenied;
use App\Modules\Rbc\Support\DeniedAbilities;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Dispatches AccessDenied for every API request refused with 403 by the
 * authorisation layer (Gate, `can:` middleware, policies, abort(403)) —
 * SRS-RBC-08 "rejected and recorded", SRS-RBC-22 unauthorised attempts.
 * The ability that failed is the last one the Gate denied in this request.
 */
class RecordAccessDenied
{
    public function __construct(private DeniedAbilities $denied) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->denied->reset();

        $response = $next($request);

        $exception = $response->exception ?? null;
        $isAuthorisationDenial = $exception instanceof AuthorizationException
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403)
            || ($exception === null && $response->getStatusCode() === 403 && $this->denied->last() !== null);

        if ($response->getStatusCode() === 403 && $isAuthorisationDenial) {
            $user = $request->user();

            AccessDenied::dispatch(
                $user instanceof User ? $user : null,
                $this->denied->last() ?? 'tidak_diketahui',
                $request->method().' '.$request->path(),
            );
        }

        return $response;
    }
}
