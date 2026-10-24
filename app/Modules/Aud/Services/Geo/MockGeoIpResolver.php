<?php

namespace App\Modules\Aud\Services\Geo;

/**
 * Stand-in until MAIS supplies a GeoIP source (e.g. a MaxMind GeoLite2 City
 * database on the server): internal ranges are labelled as such, everything
 * else is reported only at country level.
 */
class MockGeoIpResolver implements GeoIpResolver
{
    public function locate(?string $ip): ?string
    {
        if ($ip === null || $ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return 'Hos tempatan';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Rangkaian dalaman MAIS';
        }

        return 'Malaysia (anggaran)';
    }
}
