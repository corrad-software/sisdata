<?php

namespace App\Modules\Ntf\Services\Channels;

use App\Models\User;
use App\Modules\Ntf\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Log-only SMS adapter until MAIS subscribes to a gateway (SRS-NTF-04). */
class LogSmsGateway implements SmsGateway
{
    public function send(User $to, string $text): string
    {
        $id = 'log-sms-'.Str::uuid();
        Log::info('NTF SMS (log sahaja)', ['id' => $id, 'user_id' => $to->id, 'length' => mb_strlen($text)]);

        return $id;
    }
}
