<?php

namespace App\Modules\Ntf\Contracts;

use App\Models\User;

/**
 * Ready-made integration point for an SMS provider (SRS-NTF-04). MAIS has no
 * subscription yet, so MOD-NTF binds a log-only implementation. A real
 * adapter resolves the user's phone number itself.
 */
interface SmsGateway
{
    /**
     * @return string provider message id
     *
     * @throws \RuntimeException when the gateway rejects or is unreachable (MOD-NTF retries)
     */
    public function send(User $to, string $text): string;
}
