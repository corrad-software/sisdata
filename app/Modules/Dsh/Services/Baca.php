<?php

namespace App\Modules\Dsh\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * The read connection for dashboard analytics (SRS-DSH-13). When
 * DSH_DB_READ_HOST is set, MOD-DSH registers a `dsh_baca` connection to the
 * read replica (same credentials, read-only session) and every analytics
 * query of MOD-DSH's own KPIs goes there; otherwise the default connection
 * is used. MOD-DSH never writes to another module's tables either way —
 * its writes are limited to its own dsh_* tables.
 */
final class Baca
{
    public const CONNECTION = 'dsh_baca';

    public static function db(): ConnectionInterface
    {
        return DB::connection(self::name());
    }

    public static function name(): ?string
    {
        return config('database.connections.'.self::CONNECTION) ? self::CONNECTION : null;
    }

    /** Called from the service provider: derive the replica connection from the default one. */
    public static function configure(): void
    {
        $host = config('dsh.baca.host');
        if (! is_string($host) || $host === '') {
            return;
        }
        $default = (string) config('database.default');
        $base = (array) config("database.connections.{$default}");
        config(['database.connections.'.self::CONNECTION => array_merge($base, array_filter([
            'host' => $host,
            'port' => config('dsh.baca.port'),
            'username' => config('dsh.baca.username'),
            'password' => config('dsh.baca.password'),
        ], fn ($v) => $v !== null && $v !== ''), [
            // Read replicas reject writes anyway; make the intent explicit.
            'options' => ($base['options'] ?? []),
        ])]);
    }
}
