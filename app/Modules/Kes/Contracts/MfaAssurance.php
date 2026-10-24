<?php

namespace App\Modules\Kes\Contracts;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * SRS-KES-14: case records and documents need MFA on every access. The
 * default implementation (Support\SsoMfaAssurance) accepts a session that
 * came through SSO MAIS (the IdP enforces TOTP) and can be relaxed only by
 * the critical parameter `kes.mfa_wajib`. MOD-RBC may bind a stricter one
 * (e.g. reading acr/amr from the ID token).
 */
interface MfaAssurance
{
    public function satisfied(User $user, ?Request $request = null): bool;
}
