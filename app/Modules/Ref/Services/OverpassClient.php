<?php

namespace App\Modules\Ref\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal, polite Overpass API client: one query at a time, an identifying
 * User-Agent, a pause between live requests, and every response cached under
 * storage/app/ref-raw/osm/ (gitignored) so re-runs don't hit the server.
 *
 * Usage policy: https://wiki.openstreetmap.org/wiki/Overpass_API#Public_Overpass_API_instances
 */
class OverpassClient
{
    public const USER_AGENT = 'SISDATA-MAIS-refdata/1.0 (reference data track; low-volume, cached)';

    private bool $hitServer = false;

    public function __construct(
        private readonly string $endpoint = 'https://overpass-api.de/api/interpreter',
        private readonly int $pauseSeconds = 5,
    ) {}

    public static function cacheDir(): string
    {
        return storage_path('app/ref-raw/osm');
    }

    /**
     * @return array{elements: list<array<string, mixed>>, osm3s?: array<string, mixed>}
     */
    public function query(string $name, string $ql, bool $refresh = false): array
    {
        $file = self::cacheDir()."/{$name}.json";

        if (! $refresh && is_file($file)) {
            return $this->decode((string) file_get_contents($file), $file);
        }

        if ($this->hitServer && $this->pauseSeconds > 0) {
            sleep($this->pauseSeconds);
        }
        $this->hitServer = true;

        $response = Http::withUserAgent(self::USER_AGENT)
            ->timeout(600)
            ->retry(3, 30_000, throw: false)
            ->asForm()
            ->post($this->endpoint, ['data' => $ql]);

        if (! $response->successful()) {
            throw new RuntimeException("Overpass query '{$name}' failed: HTTP {$response->status()}");
        }

        File::ensureDirectoryExists(self::cacheDir());
        file_put_contents($file, $response->body());

        return $this->decode($response->body(), $file);
    }

    /**
     * Data timestamp reported by Overpass for a cached query (OSM data date).
     */
    public function timestamp(string $name): ?string
    {
        $file = self::cacheDir()."/{$name}.json";
        if (! is_file($file)) {
            return null;
        }
        $data = $this->decode((string) file_get_contents($file), $file);

        return $data['osm3s']['timestamp_osm_base'] ?? null;
    }

    /**
     * @return array{elements: list<array<string, mixed>>, osm3s?: array<string, mixed>}
     */
    private function decode(string $body, string $file): array
    {
        $data = json_decode($body, true);
        if (! is_array($data) || ! isset($data['elements'])) {
            throw new RuntimeException("Unexpected Overpass response in {$file}");
        }

        /** @var array{elements: list<array<string, mixed>>, osm3s?: array<string, mixed>} $data */
        return $data;
    }
}
