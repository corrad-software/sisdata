<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\GeoIpLocator;
use App\Modules\Rbc\Events\LoginFailed;
use App\Modules\Rbc\Events\SuspiciousActivityDetected;
use App\Modules\Rbc\Events\UserLoggedIn;
use App\Modules\Rbc\Models\AmaranKeselamatan;
use App\Modules\Rbc\Models\LogLogMasuk;
use App\Modules\Rbc\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Login attempt log (SRS-RBC-21) and suspicious-activity detection
 * (SRS-RBC-22). Dispatches UserLoggedIn / LoginFailed for MOD-AUD and
 * SuspiciousActivityDetected for MOD-NTF; never writes audit records itself.
 */
class LoginMonitor
{
    public function __construct(private GeoIpLocator $geo, private SecurityPolicy $policy) {}

    public function success(Request $request, User $user, string $method): LogLogMasuk
    {
        $log = $this->record($request, $user, $user->email, $method, true, null);

        $flags = [];
        $previous = LogLogMasuk::query()
            ->where('user_id', $user->id)
            ->where('berjaya', true)
            ->where('id', '<', $log->id);

        if ((clone $previous)->exists()) {
            if (! (clone $previous)->where('sidik_peranti', $log->sidik_peranti)->exists()) {
                $flags[] = AmaranKeselamatan::PERANTI_BAHARU;
            }
            if ($log->lokasi && ! (clone $previous)->where('lokasi', $log->lokasi)->exists()) {
                $flags[] = AmaranKeselamatan::LOKASI_BAHARU;
            }
        }

        if ($flags !== []) {
            $log->forceFill(['mencurigakan' => true])->save();
            foreach ($flags as $flag) {
                $this->alert($flag, $user, $log->alamat_ip, [
                    'log_id' => $log->id,
                    'lokasi' => $log->lokasi,
                    'pelayar' => $log->pelayar,
                    'platform' => $log->platform,
                    'peranti' => $log->peranti,
                ]);
            }
        }

        UserLoggedIn::dispatch($user, $method);

        return $log;
    }

    public function failure(Request $request, ?User $user, ?string $identifier, string $method, string $reason): LogLogMasuk
    {
        $log = $this->record($request, $user, $identifier, $method, false, $reason);

        LoginFailed::dispatch($user, (string) $identifier, $reason);

        // Repeated failures from one IP or against one identifier within the window.
        $window = Carbon::now()->subMinutes($this->policy->int('kunci_tetingkap_minit'));
        $threshold = $this->policy->int('amaran_gagal_ambang');

        $byIp = LogLogMasuk::query()->where('berjaya', false)->where('alamat_ip', $log->alamat_ip)->where('created_at', '>=', $window)->count();
        $byIdentifier = $identifier
            ? LogLogMasuk::query()->where('berjaya', false)->where('pengecam', $identifier)->where('created_at', '>=', $window)->count()
            : 0;

        if ($byIp === $threshold || $byIdentifier === $threshold) {
            $log->forceFill(['mencurigakan' => true])->save();
            $this->alert(AmaranKeselamatan::GAGAL_BERULANG, $user, $log->alamat_ip, [
                'pengecam' => $identifier,
                'cubaan_ip' => $byIp,
                'cubaan_pengecam' => $byIdentifier,
                'tetingkap_minit' => $this->policy->int('kunci_tetingkap_minit'),
            ]);
        }

        return $log;
    }

    /** @param array<string, mixed> $details */
    public function alert(string $type, ?User $user, ?string $ip, array $details = []): AmaranKeselamatan
    {
        $alert = AmaranKeselamatan::query()->create([
            'jenis' => $type,
            'user_id' => $user?->id,
            'alamat_ip' => $ip,
            'butiran' => $details,
        ]);

        SuspiciousActivityDetected::dispatch($type, $user, $ip, $details);

        return $alert;
    }

    private function record(Request $request, ?User $user, ?string $identifier, string $method, bool $success, ?string $reason): LogLogMasuk
    {
        $ua = UserAgent::parse($request->userAgent());
        $place = $this->geo->locate($request->ip());

        return LogLogMasuk::query()->create([
            'user_id' => $user?->id,
            'pengecam' => $identifier ? mb_substr(mb_strtolower($identifier), 0, 255) : null,
            'kaedah' => $method,
            'berjaya' => $success,
            'sebab' => $reason,
            'alamat_ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'peranti' => $ua->peranti,
            'pelayar' => $ua->pelayar,
            'platform' => $ua->platform,
            'sidik_peranti' => $ua->fingerprint(),
            'lokasi' => $place['lokasi'],
            'negara' => $place['negara'],
        ]);
    }
}
