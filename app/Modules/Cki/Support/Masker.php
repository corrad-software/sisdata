<?php

namespace App\Modules\Cki\Support;

/** Payload summaries for the integration log never carry full account / financial detail (kawalan keselamatan). */
final class Masker
{
    public static function akaun(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $len = mb_strlen($value);

        return $len <= 4 ? str_repeat('*', $len) : str_repeat('*', $len - 4).mb_substr($value, -4);
    }
}
