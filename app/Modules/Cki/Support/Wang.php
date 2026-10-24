<?php

namespace App\Modules\Cki\Support;

use InvalidArgumentException;

/**
 * Money for MOD-CKI: DECIMAL strings computed with bcmath (scale 2, half-up
 * where a rate is applied) — never floats.
 */
final class Wang
{
    /** Normalise any amount input to a 2-decimal DECIMAL string. */
    public static function norm(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }
        $s = is_string($amount) ? trim($amount) : (is_int($amount) ? (string) $amount : number_format($amount, 2, '.', ''));
        if (! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            throw new InvalidArgumentException("Amaun tidak sah: {$s}");
        }

        return self::round($s);
    }

    public static function add(string ...$values): string
    {
        $t = '0';
        foreach ($values as $v) {
            $t = bcadd($t, self::norm($v), 2);
        }

        return $t;
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::norm($a), self::norm($b), 2);
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp(self::norm($a), self::norm($b), 2);
    }

    public static function isZero(string $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function max(string $a, string $b): string
    {
        return self::cmp($a, $b) >= 0 ? self::norm($a) : self::norm($b);
    }

    /** $amount × $percent / 100, half-up to sen. */
    public static function percent(string $amount, string $percent): string
    {
        return self::round(bcdiv(bcmul(self::norm($amount), trim($percent), 6), '100', 6));
    }

    /** "RM 1,234.50". */
    public static function rm(string|int|float|null $amount): string
    {
        $n = self::norm($amount);
        $neg = str_starts_with($n, '-');
        [$int, $dec] = explode('.', ltrim($n, '-'));

        return ($neg ? '-' : '').'RM '.number_format((int) $int).'.'.$dec;
    }

    /** For contracts that require float (FinancialSeries, KPIs) — conversion at the boundary only. */
    public static function float(string $amount): float
    {
        return round((float) self::norm($amount), 2);
    }

    private static function round(string $value): string
    {
        $neg = str_starts_with($value, '-');
        $abs = ltrim($value, '-');
        $r = bcadd($abs, '0.005', 3);

        return ($neg && bccomp($abs, '0', 3) !== 0 ? '-' : '').bcadd($r, '0', 2);
    }
}
