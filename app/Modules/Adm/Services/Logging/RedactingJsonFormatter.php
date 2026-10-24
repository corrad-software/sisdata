<?php

namespace App\Modules\Adm\Services\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * One JSON object per line for the structured file channel `adm_json`
 * (ship-able to any log platform). Laravel Context (request_id…) is already in
 * `extra`; output is redacted (SRS-ADM-15).
 */
class RedactingJsonFormatter extends JsonFormatter
{
    public function __construct()
    {
        parent::__construct(self::BATCH_MODE_NEWLINES, true, false, true);
    }

    public function format(LogRecord $record): string
    {
        return (string) Redactor::string(parent::format($record));
    }
}
