<?php

namespace App\Modules\Adu\Integrasi;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adu\Contracts\CaptchaVerifier;

/**
 * Local CAPTCHA stand-in: accepts the token in parameter adu.captcha_token_ujian
 * (default "lulus-captcha"). Rejects anything else, including a missing token.
 */
class MockCaptcha implements CaptchaVerifier
{
    public function __construct(private Parameters $parameters) {}

    public function verify(?string $token, ?string $ip = null): bool
    {
        return $token !== null && hash_equals((string) $this->parameters->get('adu.captcha_token_ujian', 'lulus-captcha'), $token);
    }
}
