<?php

namespace App\Support;

class ThemeColor
{
    /**
     * Target lightness for each MYDS primary shade, applied to the
     * picked color's own hue/saturation to generate a full 50-950 ramp.
     */
    protected const LIGHTNESS = [
        50 => 0.97,
        100 => 0.93,
        200 => 0.85,
        300 => 0.74,
        400 => 0.63,
        500 => 0.53,
        600 => 0.46,
        700 => 0.39,
        800 => 0.33,
        900 => 0.27,
        950 => 0.18,
    ];

    /**
     * Build a 50-950 "R G B" ramp (matching MYDS's --_primary-* format)
     * from a single hex color.
     */
    public static function ramp(string $hex): array
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        [$h, $s] = self::rgbToHsl($r, $g, $b);

        $ramp = [];

        foreach (self::LIGHTNESS as $shade => $lightness) {
            [$rr, $gg, $bb] = self::hslToRgb($h, $s, $lightness);
            $ramp[$shade] = "{$rr} {$gg} {$bb}";
        }

        return $ramp;
    }

    /**
     * Build the `* { --_primary-50: ...; ... }` override CSS for a hex color.
     *
     * MYDS defines --_primary-* via a universal `*` selector (not :root), so
     * every element gets its own direct value rather than inheriting from
     * <html>. Matching that same selector here — and placing this CSS after
     * the compiled stylesheet — is what lets the override actually win.
     */
    public static function css(string $hex): string
    {
        $vars = collect(self::ramp($hex))
            ->map(fn ($rgb, $shade) => "--_primary-{$shade}: {$rgb};")
            ->implode(' ');

        return "* { {$vars} }";
    }

    protected static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    protected static function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h / 6, $s];
    }

    protected static function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hueToRgb($p, $q, $h + 1 / 3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1 / 3);
        }

        return [(int) round($r * 255), (int) round($g * 255), (int) round($b * 255)];
    }

    protected static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }

        if ($t > 1) {
            $t -= 1;
        }

        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }

        if ($t < 1 / 2) {
            return $q;
        }

        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }
}
