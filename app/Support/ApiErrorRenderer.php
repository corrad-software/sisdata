<?php

namespace App\Support;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * One error shape for every /api response (SRS-ADM-13):
 *
 *   { "message": "...", "code": "VALIDATION", "request_id": "...", "errors": {...}? }
 *
 * `message` + `errors` keep the shape the frontend ApiError already reads.
 * Stack traces and exception internals never leave the server unless
 * APP_DEBUG is on (local dev only); the request_id ties the response to the
 * log line instead.
 */
class ApiErrorRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        [$status, $code, $message, $errors] = $this->classify($e);

        $body = [
            'message' => $message,
            'code' => $code,
            'request_id' => AssignRequestId::current(),
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        if ($status >= 500 && config('app.debug')) {
            $body['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ];
        }

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return new JsonResponse($body, $status, $headers);
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, array<int, string>>|null}
     */
    private function classify(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [
                $e->status, 'VALIDATION', $e->getMessage(), $e->errors(),
            ],
            $e instanceof AuthenticationException => [
                401, 'UNAUTHENTICATED', __('Unauthenticated.'), null,
            ],
            $e instanceof AuthorizationException => [
                403, 'FORBIDDEN', $e->getMessage() !== '' ? __($e->getMessage()) : __('This action is unauthorized.'), null,
            ],
            $e instanceof ModelNotFoundException => [
                404, 'NOT_FOUND', __('Record not found.'), null,
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                $this->codeForStatus($e->getStatusCode()),
                $this->httpMessage($e),
                null,
            ],
            default => [
                500, 'SERVER_ERROR', __('A system error occurred. Please contact the administrator and quote the reference ID.'), null,
            ],
        };
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            419 => 'CSRF_MISMATCH',
            429 => 'TOO_MANY_REQUESTS',
            503 => 'UNAVAILABLE',
            default => $status >= 500 ? 'SERVER_ERROR' : 'HTTP_'.$status,
        };
    }

    /**
     * SRS-GEN-09: framework-generated HTTP messages ("CSRF token mismatch.",
     * "The route … could not be found.", "Too Many Attempts.") are English and
     * not meant for users — replace them with the Malay default for the status.
     * Messages an app passes to abort() are kept (and translated if a key exists).
     */
    private function httpMessage(HttpExceptionInterface $e): string
    {
        $message = $e->getMessage();
        $status = $e->getStatusCode();

        if ($message === '' || in_array($status, [405, 419, 429], true)
            || str_starts_with($message, 'The route ') || str_starts_with($message, 'No query results')) {
            return $this->messageForStatus($status);
        }

        return __($message);
    }

    private function messageForStatus(int $status): string
    {
        return match ($status) {
            404 => __('Record not found.'),
            419 => __('Your session has expired. Please refresh the page.'),
            405 => __('This action is not supported.'),
            429 => __('Too many requests. Please try again shortly.'),
            503 => __('The system is temporarily unavailable. Please try again later.'),
            default => __('Request failed.'),
        };
    }
}
