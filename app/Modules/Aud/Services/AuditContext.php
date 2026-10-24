<?php

namespace App\Modules\Aud\Services;

use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Aud\Services\Geo\GeoIpResolver;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * SRS-AUD-03/04/19/24: who, from where, through which channel, when — taken
 * from the current request (or "sistem" for console / queue work) so modules
 * never have to pass it.
 */
class AuditContext
{
    public const SALURAN_WEB = 'web';

    public const SALURAN_MUDAH_ALIH = 'mudah_alih';

    public const SALURAN_API = 'api';

    public const SALURAN_SISTEM = 'sistem';

    public function __construct(private GeoIpResolver $geo) {}

    /**
     * @return array<string, mixed> audit_rekod columns except the chain fields
     */
    public function capture(AuditEntry $entry): array
    {
        $request = $this->httpRequest();
        $user = $entry->actor ?? $this->currentUser();
        $now = Carbon::now('UTC');

        $ip = $request?->ip();
        $userAgent = $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null;
        $channel = $this->channel($request);

        return [
            'tarikh_masa_utc' => $now->format('Y-m-d H:i:s.uP'),
            'tarikh_masa_tempatan' => $now->copy()->setTimezone((string) config('aud.zon_waktu_tempatan'))->format('Y-m-d H:i:s.u'),
            'id_pengguna' => $user?->getKey(),
            'pengecam_pengguna' => $user ? $user->email : $entry->actorIdentifier,
            'peranan_semasa' => json_encode($user ? $this->roles($user) : []),
            'id_sesi' => $this->sessionId($request, $user),
            'alamat_ip' => $ip,
            'user_agent' => $userAgent ?: null,
            'jenis_peranti' => $request ? DeviceDetector::detect($userAgent) : DeviceDetector::PELAYAN,
            'lokasi_anggaran' => $this->geo->locate($ip),
            'saluran_capaian' => $channel,
            'modul' => mb_substr($entry->module, 0, 20),
            'nama_entiti' => $entry->entityType,
            'id_rekod' => $entry->entityId,
            'jenis_tindakan' => mb_substr($entry->action, 0, 40),
            'status_transaksi' => mb_substr($entry->status, 0, 20),
            'sebab_perubahan' => $entry->reason ?? $this->reason($request),
            'id_korelasi' => AssignRequestId::current(),
            'klasifikasi' => $entry->classification ? strtolower($entry->classification) : null,
            'meta' => $entry->meta === [] ? null : json_encode($entry->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
        ];
    }

    private function httpRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request->route() !== null ? $request : null;
    }

    private function currentUser(): ?User
    {
        try {
            $user = auth()->user();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    /**
     * @return list<string>
     */
    private function roles(User $user): array
    {
        try {
            return array_values(app()->call(fn (AccessControl $access) => $access->roles($user)));
        } catch (Throwable) {
            return [];
        }
    }

    private function channel(?Request $request): string
    {
        if (! $request) {
            return self::SALURAN_SISTEM;
        }

        $declared = strtolower((string) $request->headers->get((string) config('aud.header_saluran'), ''));
        $declared = match ($declared) {
            'mobile', 'mudah_alih', 'mudah-alih', 'app' => self::SALURAN_MUDAH_ALIH,
            'api' => self::SALURAN_API,
            'web' => self::SALURAN_WEB,
            default => null,
        };

        if ($declared) {
            return $declared;
        }

        // Bearer token without a session = machine/API client.
        return $request->bearerToken() && ! $request->hasSession() ? self::SALURAN_API : self::SALURAN_WEB;
    }

    private function sessionId(?Request $request, ?User $user): ?string
    {
        // Never store the raw session ID — it is a bearer credential (SRS-AUD-25).
        if ($request && $request->hasSession() && $request->session()->getId()) {
            return 'sesi:'.substr(hash('sha256', $request->session()->getId()), 0, 40);
        }

        $token = $user?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            return 'token:'.$token->id;
        }

        return null;
    }

    private function reason(?Request $request): ?string
    {
        $reason = AuditScope::currentReason();

        if ($reason === null && $request) {
            $header = $request->headers->get((string) config('aud.header_sebab'));
            $reason = $header ? rawurldecode($header) : null;
        }

        return $reason === null ? null : mb_substr(trim($reason), 0, 1000);
    }
}
