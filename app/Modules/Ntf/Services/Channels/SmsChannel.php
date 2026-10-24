<?php

namespace App\Modules\Ntf\Services\Channels;

use App\Models\User;
use App\Modules\Ntf\Contracts\SmsGateway;
use App\Modules\Ntf\Models\Mesej;
use Illuminate\Support\Str;

class SmsChannel implements Channel
{
    public function __construct(private SmsGateway $gateway) {}

    public function deliver(Mesej $mesej, User $to): bool
    {
        // Summary + link only (SRS-NTF-19); SMS is short.
        $this->gateway->send($to, Str::limit($mesej->subjek, 100).' '.$mesej->pautanPenuh());

        return true;
    }
}
