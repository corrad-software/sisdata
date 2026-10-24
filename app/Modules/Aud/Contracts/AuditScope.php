<?php

namespace App\Modules\Aud\Contracts;

use Closure;

/**
 * Supplies the "sebab perubahan" (SRS-AUD-03) for every audit entry written
 * while the callback runs — including the automatic field-change entries the
 * global model listener makes:
 *
 *     AuditScope::withReason('Pembetulan keluasan mengikut pelan akui', fn () => $lot->update([...]));
 *
 * A client can also send the reason in the X-Sebab-Perubahan header
 * (URL-encoded). The innermost scope wins over the header. There is
 * deliberately no way to switch auditing off (SRS-AUD-09).
 */
final class AuditScope
{
    /** @var list<string> */
    private static array $reasons = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function withReason(string $reason, Closure $callback): mixed
    {
        self::$reasons[] = $reason;

        try {
            return $callback();
        } finally {
            array_pop(self::$reasons);
        }
    }

    public static function currentReason(): ?string
    {
        return self::$reasons === [] ? null : self::$reasons[array_key_last(self::$reasons)];
    }
}
