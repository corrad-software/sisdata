<?php

namespace App\Modules\Pjk\Support;

use App\Modules\Adm\Contracts\Integrations;
use Closure;
use Throwable;

/**
 * Wraps every call to an external system (gateway, GRP MAIS, e-Sewa) so it
 * lands in MOD-ADM's integration log / health screen (SRS-INT-06/12). Codes:
 * pjk_gateway, pjk_grp, pjk_esewa.
 */
final class Luaran
{
    /**
     * @template T
     *
     * @param  Closure(): T  $call
     * @param  array<string, mixed>  $context  never credentials or personal data
     * @return T
     */
    public static function call(string $code, string $operation, Closure $call, array $context = [], string $direction = 'keluar'): mixed
    {
        $started = hrtime(true);
        try {
            $result = $call();
            self::log($code, true, $operation, $started, null, $context, $direction, is_array($result) ? count($result) : null);

            return $result;
        } catch (Throwable $e) {
            self::log($code, false, $operation, $started, $e->getMessage(), $context, $direction, null);
            throw $e;
        }
    }

    /** @param array<string, mixed> $context */
    public static function log(string $code, bool $ok, string $operation, ?int $started = null, ?string $message = null, array $context = [], string $direction = 'keluar', ?int $records = null): void
    {
        if (! app()->bound(Integrations::class)) {
            return;
        }
        $ms = $started === null ? null : (int) ((hrtime(true) - $started) / 1_000_000);
        app(Integrations::class)->log($code, $ok, null, $ms, $message, $operation, $records, $context, $direction);
    }
}
