<?php

namespace App\Modules\Adu\Integrasi;

use App\Modules\Adu\Contracts\ReporterMessenger;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Default ReporterMessenger: plain-text e-mail via the app mailer; SMS logged only. */
class MailReporterMessenger implements ReporterMessenger
{
    public function email(string $to, string $subject, string $body): void
    {
        Mail::raw($body, fn (Message $m) => $m->to($to)->subject($subject));
    }

    public function sms(string $to, string $text): void
    {
        Log::info('adu.sms_pengadu (tiada gerbang SMS)', ['ke' => substr($to, 0, 3).'•••'.substr($to, -2), 'panjang' => mb_strlen($text)]);
    }
}
