<?php

namespace App\Modules\Ntf\Contracts;

/**
 * Modules declare their notification types once, from their service
 * provider's boot(). Admins can then change template, channels, recipients,
 * priority and frequency without code (SRS-NTF-03); the values given here are
 * only the seeded defaults.
 */
interface NotificationTypes
{
    public const KRITIKAL = 'kritikal';

    public const TINGGI = 'tinggi';

    public const BIASA = 'biasa';

    public const MAKLUMAT = 'maklumat';

    /**
     * @param  string  $code  "<modul>.<nama>", e.g. "wfe.tugasan_baharu"
     * @param  string  $priority  one of the constants above
     * @param  string  $subject  default Malay subject, with {{field}} placeholders
     * @param  string  $body  default Malay body, with {{field}} placeholders
     * @param  list<string>  $fields  template fields the sender will supply
     */
    public function register(string $code, string $name, string $priority, string $subject, string $body, array $fields = [], bool $mandatory = false): void;
}
