<?php

namespace App\Modules\Rbc\Services;

use App\Modules\Rbc\Contracts\GeoIpLocator;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Offline stand-in for a GeoIP database: private/loopback ranges are the
 * MAIS internal network, a few documentation/test ranges map to fixed
 * places (so tests can exercise "new location"), everything else is
 * "Tidak diketahui".
 */
class MockGeoIpLocator implements GeoIpLocator
{
    /** @var array<string, array{lokasi: string, negara: string|null}> */
    private const RANGES = [
        '127.0.0.0/8' => ['lokasi' => 'Rangkaian dalaman MAIS', 'negara' => 'MY'],
        '::1/128' => ['lokasi' => 'Rangkaian dalaman MAIS', 'negara' => 'MY'],
        '10.0.0.0/8' => ['lokasi' => 'Rangkaian dalaman MAIS', 'negara' => 'MY'],
        '172.16.0.0/12' => ['lokasi' => 'Rangkaian dalaman MAIS', 'negara' => 'MY'],
        '192.168.0.0/16' => ['lokasi' => 'Rangkaian dalaman MAIS', 'negara' => 'MY'],
        // RFC 5737 documentation ranges, used by tests and demos.
        '192.0.2.0/24' => ['lokasi' => 'Shah Alam, Selangor', 'negara' => 'MY'],
        '198.51.100.0/24' => ['lokasi' => 'Kuala Lumpur', 'negara' => 'MY'],
        '203.0.113.0/24' => ['lokasi' => 'Singapura', 'negara' => 'SG'],
    ];

    public function locate(?string $ip): array
    {
        if ($ip) {
            foreach (self::RANGES as $range => $place) {
                if (IpUtils::checkIp($ip, $range)) {
                    return $place;
                }
            }
        }

        return ['lokasi' => 'Tidak diketahui', 'negara' => null];
    }
}
