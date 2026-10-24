<?php

namespace App\Modules\Ntf\Services\Channels;

use App\Models\User;
use App\Modules\Ntf\Contracts\WhatsAppGateway;
use App\Modules\Ntf\Models\Mesej;

class WhatsAppChannel implements Channel
{
    public function __construct(private WhatsAppGateway $gateway) {}

    public function deliver(Mesej $mesej, User $to): bool
    {
        $this->gateway->send($to, $mesej->subjek."\n\n".$mesej->kandungan."\n\n".$mesej->pautanPenuh());

        return true;
    }
}
