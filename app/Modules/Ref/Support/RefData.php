<?php

namespace App\Modules\Ref\Support;

use RuntimeException;

/**
 * Committed, processed reference data lives in app/Modules/Ref/Database/data
 * (small, reviewed files). Raw downloads go to storage/app/ref-raw (gitignored).
 */
class RefData
{
    public static function path(string $file = ''): string
    {
        return dirname(__DIR__).'/Database/data'.($file !== '' ? '/'.$file : '');
    }

    public static function exists(string $file): bool
    {
        return is_file(self::path($file));
    }

    /**
     * @return array<mixed>
     */
    public static function json(string $file): array
    {
        $path = self::path($file);
        if (! is_file($path)) {
            throw new RuntimeException("Reference data file missing: {$path} (run php artisan ref:fetch-osm)");
        }

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException("Reference data file is not a JSON array/object: {$path}");
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function write(string $file, array $data, bool $pretty = false): void
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | ($pretty ? JSON_PRETTY_PRINT : 0);
        file_put_contents(self::path($file), json_encode($data, $flags)."\n");
    }

    /**
     * Record source, licence, retrieval date and count of one dataset in manifest.json.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function manifest(string $dataset, array $entry): void
    {
        $manifest = self::exists('manifest.json') ? self::json('manifest.json') : [];
        $manifest[$dataset] = $entry;
        ksort($manifest);
        self::write('manifest.json', $manifest, pretty: true);
    }
}
