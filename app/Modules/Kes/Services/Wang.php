<?php

namespace App\Modules\Kes\Services;

use InvalidArgumentException;

/** Money as DECIMAL strings (bcmath, 2 dp) — never floats for stored values. */
final class Wang
{
    public static function normal(string $amaun): string
    {
        $v = str_replace([',', ' ', 'RM'], '', trim($amaun));
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $v)) {
            throw new InvalidArgumentException("Amaun tidak sah: {$amaun}");
        }

        return bcadd($v, '0', 2);
    }

    /** @param iterable<string|int|float|null> $values */
    public static function jumlah(iterable $values): string
    {
        $t = '0.00';
        foreach ($values as $v) {
            if ($v !== null) {
                $t = bcadd($t, (string) $v, 2);
            }
        }

        return $t;
    }

    public static function papar(?string $amaun): string
    {
        return $amaun === null ? '—' : 'RM '.number_format((float) $amaun, 2);
    }
}
