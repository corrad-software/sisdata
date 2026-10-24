<?php

namespace App\Modules\Wfe\Services;

use App\Modules\Adm\Contracts\Parameters;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * SRS-WFE-19: MFA step-up for critical stages.
 *
 * The user re-authenticates at SSO MAIS (Keycloak realm "mais") with
 * prompt=login + max_age=0 (and optional acr_values); the callback verifies
 * the fresh ID token (same subject, auth_time after the step-up began, and —
 * when wfe.mfa_wajib_otp is on — an OTP amr / accepted acr) and records the
 * time here in the session. The engine accepts a decision on a perlu_mfa
 * stage only if that time is within wfe.mfa_sah_minit minutes.
 */
class StepUp
{
    public const SESSION_AT = 'wfe.mfa.pada';

    public const SESSION_INFO = 'wfe.mfa.maklumat';

    public const SESSION_PENDING = 'wfe.mfa.mula';

    public const SESSION_RETURN = 'wfe.mfa.kembali';

    public function __construct(private Parameters $parameters) {}

    public function verifiedAt(Request $request): ?Carbon
    {
        if (! $request->hasSession()) {
            return null;
        }
        $at = $request->session()->get(self::SESSION_AT);

        return is_numeric($at) ? Carbon::createFromTimestamp((int) $at) : null;
    }

    public function isFresh(?Carbon $at): bool
    {
        return $at !== null && $at->greaterThanOrEqualTo(now()->subMinutes($this->windowMinutes()));
    }

    /** acr_values sent with the step-up request (empty = none), e.g. "mfa" once the realm maps it. */
    public function acrValues(): string
    {
        return trim((string) $this->parameters->get('wfe.mfa_acr_values', ''));
    }

    public function windowMinutes(): int
    {
        return max(1, (int) $this->parameters->get('wfe.mfa_sah_minit', 10));
    }

    /**
     * Checks the claims of the re-authentication ID token. Returns null when
     * acceptable, else a reason code.
     *
     * @param  array<string, mixed>  $claims
     */
    public function rejectReason(array $claims, string $expectedSub, int $startedAt): ?string
    {
        if (($claims['sub'] ?? null) !== $expectedSub) {
            return 'subjek';
        }
        $authTime = (int) ($claims['auth_time'] ?? 0);
        // Allow small clock skew between IdP and app.
        if ($authTime === 0 || $authTime < $startedAt - 30) {
            return 'tidak_segar';
        }
        if ((bool) $this->parameters->get('wfe.mfa_wajib_otp', false)) {
            $amr = array_map('strtolower', array_map('strval', (array) ($claims['amr'] ?? [])));
            $acr = (string) ($claims['acr'] ?? '');
            $accepted = (array) $this->parameters->get('wfe.mfa_acr_diterima', ['mfa', 'otp', '2']);
            $hasOtp = array_intersect($amr, ['otp', 'totp', 'hwk', 'mfa']) !== [];
            if (! $hasOtp && ! in_array($acr, array_map('strval', $accepted), true)) {
                return 'tiada_otp';
            }
        }

        return null;
    }

    /**
     * Payload of a JWT without signature verification. Safe here only because
     * the token came straight from the IdP's token endpoint over the
     * back-channel (OIDC Core §3.1.3.7(6)).
     *
     * @return array<string, mixed>
     */
    public static function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}
