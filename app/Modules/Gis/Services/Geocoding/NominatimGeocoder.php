<?php

namespace App\Modules\Gis\Services\Geocoding;

use App\Models\User;
use App\Modules\Gis\Contracts\Geocoder;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Nominatim-compatible address geocoder (OSM Nominatim, a self-hosted
 * instance, or any service speaking the same /search?format=jsonv2 API),
 * bounded to Selangor. Used only when MAIS sets gis.geocoder = "nominatim"
 * and gis.nominatim_url — the query text then leaves the system, so keep it
 * off for personal data searches.
 */
class NominatimGeocoder implements Geocoder
{
    /** Selangor + WP viewbox (lng/lat). */
    private const VIEWBOX = '100.7,3.9,102.0,2.5';

    public function __construct(private string $baseUrl, private string $userAgent = 'SISDATA-MAIS/1.0') {}

    public function search(string $query, User $user, int $limit = 10): array
    {
        if ($this->baseUrl === '' || mb_strlen(trim($query)) < 3) {
            return [];
        }

        try {
            $response = Http::timeout(5)->withHeaders(['User-Agent' => $this->userAgent])
                ->get(rtrim($this->baseUrl, '/').'/search', [
                    'q' => $query, 'format' => 'jsonv2', 'countrycodes' => 'my', 'viewbox' => self::VIEWBOX, 'bounded' => 1, 'limit' => $limit,
                ]);
            if (! $response->ok()) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ((array) $response->json() as $r) {
            if (! is_array($r) || ! isset($r['lat'], $r['lon'])) {
                continue;
            }
            $bb = isset($r['boundingbox']) && is_array($r['boundingbox']) && count($r['boundingbox']) === 4
                ? [(float) $r['boundingbox'][2], (float) $r['boundingbox'][0], (float) $r['boundingbox'][3], (float) $r['boundingbox'][1]] : null;
            $out[] = ['label' => (string) ($r['display_name'] ?? $query), 'jenis' => 'alamat', 'lng' => (float) $r['lon'], 'lat' => (float) $r['lat'], 'bbox' => $bb, 'id' => null, 'pautan' => null];
        }

        return $out;
    }
}
