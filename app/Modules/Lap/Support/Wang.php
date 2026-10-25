<?php

namespace App\Modules\Lap\Support;

/**
 * Money and decimal arithmetic for reports — bcmath only, never floats, so
 * totals, year-on-year differences and percentages match to the sen across
 * screen, PDF, Excel and CSV (acceptance criterion 2).
 */
final class Wang
{
    public const SKALA = 2;

    /** Normalise a DB numeric / int / null to a canonical decimal string. */
    public static function nilai(mixed $v, int $scale = self::SKALA): string
    {
        if ($v === null || $v === '' || is_bool($v)) {
            return bcadd('0', '0', $scale);
        }
        $s = is_float($v) ? number_format($v, 10, '.', '') : trim((string) $v);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return bcadd('0', '0', $scale);
        }

        // bcadd truncates; round half away from zero at $scale.
        $half = '0.'.str_repeat('0', $scale).'5';

        return str_starts_with($s, '-') ? bcsub($s, $half, $scale) : bcadd($s, $half, $scale);
    }

    /** @param iterable<mixed> $values */
    public static function jumlah(iterable $values, int $scale = self::SKALA): string
    {
        $total = bcadd('0', '0', $scale + 4);
        foreach ($values as $v) {
            $total = bcadd($total, self::nilai($v, $scale + 4), $scale + 4);
        }

        return self::nilai($total, $scale);
    }

    public static function tolak(mixed $a, mixed $b, int $scale = self::SKALA): string
    {
        return self::nilai(bcsub(self::nilai($a, $scale + 4), self::nilai($b, $scale + 4), $scale + 4), $scale);
    }

    /** (a - b) / b × 100, or null when b is zero. */
    public static function peratusPerubahan(mixed $a, mixed $b): ?string
    {
        $b = self::nilai($b, 6);
        if (bccomp($b, '0', 6) === 0) {
            return null;
        }

        return self::nilai(bcmul(bcdiv(bcsub(self::nilai($a, 6), $b, 6), $b, 8), '100', 6));
    }

    public static function darab(mixed $a, mixed $b, int $scale = self::SKALA): string
    {
        return self::nilai(bcmul(self::nilai($a, 6), self::nilai($b, 6), 8), $scale);
    }

    /** "1234567.5" → "1,234,567.50" (display only). */
    public static function papar(mixed $v): string
    {
        $s = self::nilai($v);
        $neg = str_starts_with($s, '-');
        [$int, $dec] = explode('.', ltrim($s, '-'));
        $int = strrev(implode(',', str_split(strrev($int), 3)));

        return ($neg ? '-' : '').$int.'.'.$dec;
    }
}
