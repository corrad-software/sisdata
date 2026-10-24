<?php

namespace App\Modules\Tsu\Support;

/** Money in MOD-TSU is a DECIMAL string handled with bcmath — never float. */
final class Wang
{
    public const POLA = '/^\d{1,12}(\.\d{1,2})?$/';

    public static function norm(string|int|float|null $v): string
    {
        if ($v === null || $v === '') {
            return '0.00';
        }

        return bcadd(is_float($v) ? number_format($v, 2, '.', '') : (string) $v, '0', 2);
    }

    /** @param iterable<string|int|float|null> $values */
    public static function sum(iterable $values): string
    {
        $t = '0.00';
        foreach ($values as $v) {
            $t = bcadd($t, self::norm($v), 2);
        }

        return $t;
    }
}
