<?php

namespace App\Modules\Pws\Services;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Throwable;

/**
 * Calls another module's published HTTP API in-process (MOD-PJK portal
 * endpoints, MOD-ADU public complaint API, MOD-PER portal push) — the same
 * contract an external API Gateway would use, so MOD-PWS never touches
 * another module's tables or classes (ADR-03). The request goes through the
 * full middleware stack: authentication, `can:` (MOD-RBC), throttling,
 * validation and audit all apply exactly as for a network call.
 *
 * $as is the integration service account (Sanctum guard); null = anonymous.
 * $clientIp is the end user's IP forwarded by the portal, so per-IP limits
 * in the target module apply to the member of the public, not the portal.
 */
class InternalApi
{
    public function __construct(private LogIntegrasi $log) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @param  array<string, UploadedFile|list<UploadedFile>>  $files
     * @return array{status: int, body: array<string, mixed>}
     */
    public function call(string $method, string $path, array $data = [], ?User $as = null, array $headers = [], ?string $clientIp = null, array $files = [], ?string $reference = null): array
    {
        $original = app('request');
        $server = ['REMOTE_ADDR' => $clientIp ?? $original->ip() ?? '127.0.0.1', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $requestId = $original->headers->get('X-Request-Id');
        if (is_string($requestId) && $requestId !== '') {
            $server['HTTP_X_REQUEST_ID'] = $requestId;
        }

        $sub = Request::create('/api/v1/'.ltrim($path, '/'), strtoupper($method), $data, [], $files, $server);
        $started = microtime(true);
        $status = 500;
        $body = [];

        $guard = Auth::guard('sanctum');
        $previous = $guard->hasUser() ? $guard->user() : null;

        try {
            if ($as !== null) {
                $guard->setUser($as);
            }
            $response = app(HttpKernel::class)->handle($sub);
            $status = $response->getStatusCode();
            $decoded = json_decode((string) $response->getContent(), true);
            $body = is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            report($e);
            $body = ['message' => 'internal'];
        } finally {
            // Restore the caller's request and drop the service account from the guards.
            app()->instance('request', $original);
            Facade::clearResolvedInstance('request');
            if ($previous !== null) {
                Auth::guard('sanctum')->setUser($previous);
            } elseif ($as !== null) {
                Auth::forgetGuards();
            }
        }

        $this->log->write('keluar', strtoupper($method), $path, $status, (int) ((microtime(true) - $started) * 1000), $reference, $data, $status >= 400 ? (string) ($body['message'] ?? '') : null);

        return ['status' => $status, 'body' => $body];
    }
}
