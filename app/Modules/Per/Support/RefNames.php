<?php

namespace App\Modules\Per\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * District / mukim display names from MOD-REF's reference tables (read-only
 * reference data; codes are what MOD-PER stores).
 */
final class RefNames
{
    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    /** @return array<string, string> kod => nama */
    public static function daerah(): array
    {
        return self::$cache['daerah'] ??= self::load('ref_daerah');
    }

    /** @return array<string, string> kod => nama */
    public static function mukim(): array
    {
        return self::$cache['mukim'] ??= self::load('ref_mukim');
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** @return array<string, string> */
    private static function load(string $table): array
    {
        try {
            return DB::table($table)->whereNull('sah_hingga')->where('aktif', true)->pluck('nama', 'kod')
                ->map(fn ($n) => (string) $n)->all();
        } catch (Throwable) {
            return [];
        }
    }
}
