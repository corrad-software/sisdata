<?php

namespace App\Modules\Adm\Services\Siem;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Log channel "keselamatan": `Log::channel('keselamatan')->warning('…', [...])`
 * from any module ends up in the SIEM stream (SRS-ADM-27).
 */
class SiemLogHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        try {
            $context = $record->context;
            app(LogKeselamatan::class)->rekod(
                (string) ($context['tindakan'] ?? 'log'),
                $record->level->isHigherThan(Level::Notice) ? 'failure' : 'success',
                isset($context['id_pengguna']) && is_numeric($context['id_pengguna']) ? (int) $context['id_pengguna'] : null,
                $record->message,
                array_diff_key($context, ['tindakan' => true, 'id_pengguna' => true, 'exception' => true]),
            );
        } catch (Throwable) {
        }
    }
}
