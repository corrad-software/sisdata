<?php

namespace App\Modules\Pjk\Support;

/** Partial masking of identity data in lists (kawalan keselamatan MOD-PJK). */
final class Masker
{
    public static function ic(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($digits) === 12) {
            return '******-**-'.substr($digits, -4);
        }

        return self::tail($value);
    }

    public static function tail(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat('*', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }

    public static function email(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, '@')) {
            return $value;
        }
        [$name, $domain] = explode('@', $value, 2);

        return mb_substr($name, 0, 1).'***@'.$domain;
    }
}
