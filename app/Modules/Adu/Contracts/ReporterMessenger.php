<?php

namespace App\Modules\Adu\Contracts;

/**
 * SRS-ADU-05: messages to a member of the public who is not a SISDATA user
 * (MOD-NTF's Notifier only addresses Users). The default sends e-mail via
 * the application mailer (Mailpit locally) and logs SMS; an SMS gateway
 * adapter replaces it when MAIS subscribes.
 */
interface ReporterMessenger
{
    /** @throws \RuntimeException on delivery failure */
    public function email(string $to, string $subject, string $body): void;

    /** @throws \RuntimeException on delivery failure */
    public function sms(string $to, string $text): void;
}
