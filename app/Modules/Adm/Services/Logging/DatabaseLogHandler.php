<?php

namespace App\Modules\Adm\Services\Logging;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use JsonSerializable;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;

/**
 * SRS-ADM-11: structured application log in adm_log_ralat, searchable by
 * period, module, severity, error code, user id and correlation id. Every
 * entry carries the request's correlation ID from Laravel Context
 * (SRS-ADM-12), so the reference a user sees on an error maps to its row.
 *
 * Never throws and never recurses: logging must not take the app down, and a
 * failed insert inside a transaction runs in a savepoint so it can't abort
 * the caller's transaction.
 */
class DatabaseLogHandler extends AbstractProcessingHandler
{
    private static bool $writing = false;

    private static ?bool $ready = null;

    protected function write(LogRecord $record): void
    {
        if (self::$writing) {
            return;
        }

        self::$writing = true;

        try {
            if (! $this->ready()) {
                return;
            }

            $row = self::row($record);
            $connection = DB::connection();

            if ($connection->transactionLevel() > 0) {
                $connection->transaction(fn () => $connection->table('adm_log_ralat')->insert($row));
            } else {
                $connection->table('adm_log_ralat')->insert($row);
            }
        } catch (Throwable) {
            // Swallow: the file/JSON channels in the same stack still have the entry.
        } finally {
            self::$writing = false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(LogRecord $record): array
    {
        $context = $record->context;
        $exception = $context['exception'] ?? null;
        unset($context['exception']);

        $trace = null;
        $code = $context['kod_ralat'] ?? null;
        if ($exception instanceof Throwable) {
            $trace = $exception::class.': '.$exception->getMessage()."\n".$exception->getFile().':'.$exception->getLine()."\n".$exception->getTraceAsString();
            $code ??= class_basename($exception).($exception->getCode() ? ':'.$exception->getCode() : '');
        }

        $userId = $context['id_pengguna'] ?? $context['userId'] ?? self::currentUserId();

        return [
            'id_korelasi' => self::stringOrNull(Context::get('request_id')),
            'tarikh_masa' => $record->datetime->format('Y-m-d H:i:s.uP'),
            'aras_keterukan' => strtolower($record->level->getName()),
            'saluran' => Str::limit((string) ($context['saluran'] ?? 'aplikasi'), 30, ''),
            'modul' => self::stringOrNull($context['modul'] ?? Context::get('modul') ?? self::moduleFromRequest()),
            'kod_ralat' => $code !== null ? Str::limit((string) $code, 120, '') : null,
            'mesej' => (string) Redactor::string(Str::limit($record->message, 4000)),
            'jejak_tindanan' => $trace !== null ? Redactor::string(Str::limit($trace, 20000)) : null,
            'id_pengguna' => is_numeric($userId) ? (int) $userId : null,
            'alamat_ip' => self::ip(),
            'konteks' => json_encode(Redactor::array(self::normalise($context)), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
        ];
    }

    private function ready(): bool
    {
        // Only a positive answer is remembered: the table may appear later (migrate, tests).
        if (self::$ready === true) {
            return true;
        }

        $ready = Schema::hasTable('adm_log_ralat');
        if ($ready) {
            self::$ready = true;
        }

        return $ready;
    }

    /** Tests and fresh installs: the table may appear after the first check. */
    public static function resetState(): void
    {
        self::$ready = null;
        self::$writing = false;
    }

    private static function currentUserId(): ?int
    {
        try {
            $guard = app('auth')->guard();

            return $guard->hasUser() ? (int) $guard->id() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function ip(): ?string
    {
        try {
            return app()->bound('request') ? request()->ip() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function moduleFromRequest(): ?string
    {
        try {
            if (app()->bound('request') && preg_match('#^api/v1/([a-z]{2,10})(/|$)#', request()->path(), $m)) {
                return $m[1];
            }
        } catch (Throwable) {
        }

        return null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && $value !== '' ? Str::limit((string) $value, 64, '') : null;
    }

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    private static function normalise(array $context, int $depth = 0): array
    {
        foreach ($context as $key => $value) {
            $context[$key] = match (true) {
                $value instanceof Throwable => $value::class.': '.$value->getMessage(),
                $value instanceof JsonSerializable => $value->jsonSerialize(),
                is_object($value) => $value::class,
                is_array($value) => $depth > 5 ? '[…]' : self::normalise($value, $depth + 1),
                default => $value,
            };
        }

        return $context;
    }
}
