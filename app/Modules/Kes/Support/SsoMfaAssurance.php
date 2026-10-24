<?php

namespace App\Modules\Kes\Support;

use App\Http\Controllers\Auth\OidcController;
use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Kes\Contracts\MfaAssurance;
use Illuminate\Http\Request;

/**
 * Default MfaAssurance (SRS-KES-14): the session must come from SSO MAIS,
 * whose login flow enforces TOTP (SRS-RBC-03). A password session — even a
 * valid one — is refused for case records. `kes.mfa_wajib` (critical
 * parameter, two-admin approval) can switch the requirement off, e.g. for a
 * training environment without the IdP.
 */
class SsoMfaAssurance implements MfaAssurance
{
    public function __construct(private Parameters $parameters) {}

    public function satisfied(User $user, ?Request $request = null): bool
    {
        if (! (bool) $this->parameters->get('kes.mfa_wajib', true)) {
            return true;
        }
        if ($request === null || ! $request->hasSession()) {
            return false;
        }

        return $user->jenis_pengesahan === 'sso' && (string) $request->session()->get(OidcController::SESSION_ID_TOKEN, '') !== '';
    }
}
