<?php

namespace App\Modules\Gis\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Other modules' tables are read (never written) for thematic layers; they
 * are being built in parallel and may not exist yet or change shape. Every
 * optional join checks the table/columns first so a layer degrades to "no
 * data" instead of failing. Memoised per process.
 */
final class Jadual
{
    /** @var array<string, bool> */
    private static array $memo = [];

    /** @param list<string> $columns */
    public static function has(string $table, array $columns = []): bool
    {
        $key = $table.':'.implode(',', $columns);
        if (! array_key_exists($key, self::$memo)) {
            self::$memo[$key] = Schema::hasTable($table) && ($columns === [] || Schema::hasColumns($table, $columns));
        }

        return self::$memo[$key];
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
