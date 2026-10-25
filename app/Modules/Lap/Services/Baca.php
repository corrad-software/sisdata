<?php

namespace App\Modules\Lap\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * The reporting connection (SRS-LAP-07/08). With LAP_DB_READ_HOST set, MOD-LAP
 * registers `lap_baca` pointing at the read replica (optionally with its own
 * read-only role); otherwise the default connection is used. Either way every
 * query goes through the `laporan` views inside a read-only transaction.
 */
final class Baca
{
    public const CONNECTION = 'lap_baca';

    public static function db(): ConnectionInterface
    {
        return DB::connection(self::name());
    }

    public static function name(): ?string
    {
        return config('database.connections.'.self::CONNECTION) ? self::CONNECTION : null;
    }

    public static function replica(): bool
    {
        return self::name() !== null;
    }

    public static function configure(): void
    {
        $host = config('lap.baca.host');
        if (! is_string($host) || $host === '') {
            return;
        }
        $default = (string) config('database.default');
        $base = (array) config("database.connections.{$default}");
        config(['database.connections.'.self::CONNECTION => array_merge($base, array_filter([
            'host' => $host,
            'port' => config('lap.baca.port'),
            'database' => config('lap.baca.database'),
            'username' => config('lap.baca.username'),
            'password' => config('lap.baca.password'),
        ], fn ($v) => $v !== null && $v !== ''))]);
    }
}
