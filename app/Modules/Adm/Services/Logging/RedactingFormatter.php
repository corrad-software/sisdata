<?php

namespace App\Modules\Adm\Services\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Wraps any channel's formatter so the final text (including normalised
 * exception messages and traces the processor can't reach) is redacted.
 */
class RedactingFormatter implements FormatterInterface
{
    public function __construct(private FormatterInterface $inner) {}

    public function format(LogRecord $record): mixed
    {
        $formatted = $this->inner->format($record);

        return is_string($formatted) ? Redactor::string($formatted) : $formatted;
    }

    public function formatBatch(array $records): mixed
    {
        $formatted = $this->inner->formatBatch($records);

        return is_string($formatted) ? Redactor::string($formatted) : $formatted;
    }

    public function inner(): FormatterInterface
    {
        return $this->inner;
    }
}
