<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS-ADM-12: every request carries a correlation ID. An incoming
 * X-Request-Id (from the API gateway / frontend) is kept if it looks sane,
 * otherwise a new UUID is minted. It is pushed into Laravel's Context so it
 * lands in every log line and follows queued jobs, and echoed back on the
 * response so a user can quote it to support.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $id = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();

        $request->headers->set(self::HEADER, $id);
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    public static function current(): ?string
    {
        return Context::get('request_id');
    }
}
