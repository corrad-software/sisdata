<?php

namespace App\Modules\Adm\Services\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/** Monolog processor: redacts the message, context and extra of every record (SRS-ADM-15). */
class RedactSensitiveData implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: (string) Redactor::string($record->message),
            context: Redactor::array($record->context),
            extra: Redactor::array($record->extra),
        );
    }
}
