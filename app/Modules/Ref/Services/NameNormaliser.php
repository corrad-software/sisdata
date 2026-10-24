<?php

namespace App\Modules\Ref\Services;

use Illuminate\Support\Str;

/**
 * Normalises Malay place names so spelling variants compare equal
 * (Ulu/Hulu, Tanjong/Tanjung, Telok/Teluk, Sungei/Sungai, "Mukim X", "Kg. X").
 * Used to match OSM names to reference codes, and reusable by the migration
 * track for SRS 15.3 Fasa 2 (penormalan nama mukim dan daerah).
 */
class NameNormaliser
{
    private const WORDS = [
        'ulu' => 'hulu',
        'tanjong' => 'tanjung',
        'telok' => 'teluk',
        'sungei' => 'sungai',
        'kampong' => 'kampung',
        'kg' => 'kampung',
        'ujung' => 'ujong',
        'hujong' => 'ujong',
        'buluh' => 'buloh',
        'pechah' => 'pecah',
        'pancang' => 'panchang',
        'klang' => 'kelang',
        'duabelas' => 'dua belas',
        'sg' => 'sungai',
        'bt' => 'batu',
    ];

    private const PREFIXES = ['mukim', 'daerah', 'bandar', 'pekan', 'kampung'];

    public static function normalise(string $name, bool $stripPrefixes = true): string
    {
        $s = Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9 ]+/', ' ')->squish()->toString();

        $words = array_map(fn (string $w) => self::WORDS[$w] ?? $w, explode(' ', $s));
        $s = implode(' ', $words);

        if ($stripPrefixes) {
            foreach (self::PREFIXES as $prefix) {
                if (str_starts_with($s, $prefix.' ')) {
                    $s = substr($s, strlen($prefix) + 1);
                }
            }
        }

        return trim($s);
    }

    /**
     * @param  list<string>  $aliases
     * @return list<string>
     */
    public static function variants(string $name, array $aliases = []): array
    {
        return array_values(array_unique(array_map(fn (string $n) => self::normalise($n), [$name, ...$aliases])));
    }
}
