<?php

namespace App\Modules\Ntf\Contracts;

use InvalidArgumentException;

/**
 * Thrown by Notifier::send() when $data carries a field flagged sensitive
 * (Sulit/Rahsia) on the type or in the global list `ntf.medan_sensitif_global`
 * (SRS-NTF-19). Pass a summary and a link instead.
 */
class SensitiveDataRefused extends InvalidArgumentException
{
    /** @param list<string> $fields */
    public function __construct(public readonly string $type, public readonly array $fields)
    {
        parent::__construct(sprintf('Notification "%s" refused: sensitive field(s) %s must not be sent.', $type, implode(', ', $fields)));
    }
}
