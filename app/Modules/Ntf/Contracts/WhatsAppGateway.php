<?php

namespace App\Modules\Ntf\Contracts;

use App\Models\User;

/**
 * Ready-made integration point for a WhatsApp Business provider
 * (SRS-NTF-04). Bound to a log-only implementation until MAIS subscribes.
 */
interface WhatsAppGateway
{
    /**
     * @return string provider message id
     *
     * @throws \RuntimeException when the gateway rejects or is unreachable (MOD-NTF retries)
     */
    public function send(User $to, string $text): string;
}
