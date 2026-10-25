<?php

namespace App\Modules\Pws\Services;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The SISDATA service accounts the gateway acts as when it forwards a portal
 * call to MOD-PJK / MOD-PER integration endpoints. Their roles hold only the
 * integration permissions (pjk.portal.cipta, per.portal_wakaf.cipta) and are
 * created by database/seeders/PwsSeeder through MOD-RBC — never here.
 */
class AkaunIntegrasi
{
    public function portal(): User
    {
        return $this->find((string) config('pws.akaun_integrasi'));
    }

    /** Non-production only: may run the mock gateway simulation (pjk.bayaran.cipta). */
    public function gatewayUjian(): User
    {
        return $this->find((string) config('pws.akaun_gateway_ujian'));
    }

    private function find(string $email): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            throw new HttpException(503, __('pws::pws.ralat.akaun_integrasi'));
        }

        return $user;
    }
}
