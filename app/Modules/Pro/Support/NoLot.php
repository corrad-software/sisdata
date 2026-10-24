<?php

namespace App\Modules\Pro\Support;

use Illuminate\Support\Str;

/**
 * Lot-number normalisation (SRS 15.3 Fasa 2, SRS-PRO-20). Source systems write
 * the same lot many ways — "Lot No. 123", "No. Lot 123", "L123", "lot123",
 * "P.T. 5678", "PT No. 5678" — all mean "Lot 123" / "PT 5678".
 *
 *  - canonical(): the stored display form ("Lot 123", "PT 5678"), or null
 *    when the input cannot be read as a lot number at all.
 *  - key(): upper-case comparison key ("LOT 123") used for duplicate matching.
 */
final class NoLot
{
    /** @return array{0: string, 1: string}|null [prefix LOT|PT, number incl. optional suffix letter] */
    public static function parse(string $raw): ?array
    {
        $s = Str::upper(Str::ascii($raw));
        $s = (string) preg_replace('/[^A-Z0-9]+/', ' ', $s);
        $s = (string) preg_replace('/([A-Z])([0-9])/', '$1 $2', $s);
        $tokens = array_values(array_filter(explode(' ', trim($s)), fn ($t) => $t !== '' && $t !== 'NO'));

        $letters = '';
        $number = null;
        foreach ($tokens as $i => $token) {
            if (preg_match('/^[0-9]+$/', $token)) {
                $number = $token;
                $rest = array_slice($tokens, $i + 1);
                if (count($rest) === 1 && preg_match('/^[A-Z]$/', $rest[0])) {
                    $number .= $rest[0];
                } elseif ($rest !== []) {
                    return null;
                }
                break;
            }
            $letters .= $token;
        }

        if ($number === null) {
            return null;
        }

        $prefix = match ($letters) {
            'LOT', 'L', '' => 'LOT',
            'PT' => 'PT',
            default => null,
        };

        return $prefix === null ? null : [$prefix, ltrim($number, '0') ?: '0'];
    }

    public static function canonical(string $raw): ?string
    {
        $parts = self::parse($raw);
        if ($parts === null) {
            return null;
        }

        return ($parts[0] === 'PT' ? 'PT' : 'Lot').' '.$parts[1];
    }

    public static function key(string $raw): string
    {
        $parts = self::parse($raw);

        return $parts === null
            ? trim((string) preg_replace('/[^A-Z0-9]+/', ' ', Str::upper(Str::ascii($raw))))
            : $parts[0].' '.$parts[1];
    }

    /** Digits only, for typo-distance comparison. */
    public static function digits(string $raw): string
    {
        return (string) preg_replace('/[^0-9]/', '', self::key($raw));
    }
}
