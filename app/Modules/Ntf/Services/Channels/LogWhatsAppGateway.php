<?php

namespace App\Modules\Ntf\Services\Channels;

use App\Models\User;
use App\Modules\Ntf\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Log-only WhatsApp adapter until MAIS subscribes to a provider (SRS-NTF-04). */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(User $to, string $text): string
    {
        $id = 'log-wa-'.Str::uuid();
        Log::info('NTF WhatsApp (log sahaja)', ['id' => $id, 'user_id' => $to->id, 'length' => mb_strlen($text)]);

        return $id;
    }
}
