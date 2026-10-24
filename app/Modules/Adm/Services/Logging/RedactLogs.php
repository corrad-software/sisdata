<?php

namespace App\Modules\Adm\Services\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger as Monolog;

/**
 * Logging "tap" MOD-ADM adds to every configured channel: pushes the
 * redaction processor and wraps each handler's formatter. Idempotent, so a
 * stack channel re-using tapped sub-channels is fine.
 */
class RedactLogs
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if (! $monolog instanceof Monolog) {
            return;
        }

        $hasProcessor = false;
        foreach ($monolog->getProcessors() as $processor) {
            $hasProcessor = $hasProcessor || $processor instanceof RedactSensitiveData;
        }
        if (! $hasProcessor) {
            $monolog->pushProcessor(new RedactSensitiveData);
        }

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface && ! $handler->getFormatter() instanceof RedactingFormatter) {
                $handler->setFormatter(new RedactingFormatter($handler->getFormatter()));
            }
        }
    }
}
