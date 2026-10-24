<?php

namespace App\Modules\Adu\Contracts;

/**
 * SRS-ADU-24: CAPTCHA check for the public complaint portal. The real
 * provider (decided with MAIS / the API Gateway) binds its own
 * implementation; MOD-ADU ships a local stand-in (Integrasi\MockCaptcha).
 */
interface CaptchaVerifier
{
    public function verify(?string $token, ?string $ip = null): bool;
}
