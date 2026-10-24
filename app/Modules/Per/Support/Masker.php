<?php

namespace App\Modules\Per\Support;

/**
 * Masking of personal data in MOD-PER (SRS Modul 4 kawalan keselamatan):
 * ID numbers are always masked in lists; names are masked for users without
 * per.sulit.lihat.
 */
final class Masker
{
    public static function idNumber(?string $last4): ?string
    {
        return $last4 === null || $last4 === '' ? null : '******-**-'.$last4;
    }

    public static function name(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return $name;
        }

        return implode(' ', array_map(
            fn (string $w) => mb_substr($w, 0, 1).str_repeat('*', max(2, min(5, mb_strlen($w) - 1))),
            preg_split('/\s+/u', trim($name)) ?: [],
        ));
    }
}
