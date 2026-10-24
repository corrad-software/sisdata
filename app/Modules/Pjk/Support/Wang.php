<?php

namespace App\Modules\Pjk\Support;

use InvalidArgumentException;

/**
 * Money helper: amounts travel as DECIMAL strings ("1234.50") and are
 * computed in integer sen — never floats. Display in Malaysian ringgit.
 */
final class Wang
{
    public static function sen(string|int|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        if (is_int($amount)) {
            return $amount * 100;
        }
        $s = trim($amount);
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,4}))?$/', $s, $m)) {
            throw new InvalidArgumentException("Amaun tidak sah: {$s}");
        }
        $frac = str_pad($m[3] ?? '', 4, '0');
        $cents = (int) $m[2] * 100 + (int) substr($frac, 0, 2);
        if ((int) substr($frac, 2, 2) >= 50) {
            $cents++; // half-up on the 3rd/4th decimals
        }

        return $m[1] === '-' ? -$cents : $cents;
    }

    /** Sen → DECIMAL string, e.g. 123450 → "1234.50". */
    public static function dec(int $sen): string
    {
        $sign = $sen < 0 ? '-' : '';
        $sen = abs($sen);

        return $sign.intdiv($sen, 100).'.'.str_pad((string) ($sen % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Normalise any amount input to a DECIMAL string. */
    public static function norm(string|int|null $amount): string
    {
        return self::dec(self::sen($amount));
    }

    /** "RM 1,234.50" (negative: "-RM 1,234.50"). */
    public static function rm(string|int|null $amount): string
    {
        $sen = is_int($amount) ? $amount : self::sen($amount);
        $abs = abs($sen);

        return ($sen < 0 ? '-' : '').'RM '.number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** $sen × $percent / 100 where $percent is a DECIMAL string ("12.5000"); half-up. */
    public static function percent(int $sen, string $percent): int
    {
        $num = $sen * self::scaled($percent);
        $den = 1_000_000;

        return intdiv($num + ($num >= 0 ? $den / 2 : -$den / 2), $den);
    }

    /** DECIMAL with up to 4 places → integer × 10^4. */
    public static function scaled(string $value): int
    {
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,4}))?$/', trim($value), $m)) {
            throw new InvalidArgumentException("Nilai tidak sah: {$value}");
        }
        $v = (int) $m[2] * 10000 + (int) str_pad($m[3] ?? '', 4, '0');

        return $m[1] === '-' ? -$v : $v;
    }
}
