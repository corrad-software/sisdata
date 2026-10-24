<?php

namespace App\Modules\Kes\Http\Middleware;

use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use App\Modules\Kes\Contracts\MfaAssurance;
use App\Modules\Kes\Services\LogCapaianKes;
use App\Modules\Kes\Support\Akses;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for every case record / document endpoint (SRS-KES-13/14,
 * acceptance #4): legal permission + explicit Rahsia grant, then MFA. A
 * refusal is itself logged (kes_log_capaian "ditolak" + audit).
 */
class PastikanAksesKes
{
    public function __construct(private Akses $akses, private MfaAssurance $mfa, private LogCapaianKes $log) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }
        $kesId = is_string($request->route('kes')) ? $request->route('kes') : null;
        if (! $this->akses->bolehLihat($user)) {
            $this->log->rekod('ditolak', $kesId, null, 'Tiada kebenaran Bahagian Perundangan / Rahsia: '.$request->path());

            return $this->tolak((string) __('kes::kes.ralat.tiada_akses'), 'FORBIDDEN');
        }
        if (! $this->mfa->satisfied($user, $request)) {
            $this->log->rekod('ditolak', $kesId, null, 'MFA diperlukan: '.$request->path());

            return $this->tolak((string) __('kes::kes.ralat.mfa'), 'MFA_DIPERLUKAN', ['pautan_sso' => '/auth/oidc/redirect']);
        }

        return $next($request);
    }

    /** @param array<string, mixed> $extra */
    private function tolak(string $message, string $code, array $extra = []): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'code' => $code, 'request_id' => AssignRequestId::current(), ...$extra], 403);
    }
}
