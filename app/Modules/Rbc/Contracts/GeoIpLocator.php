<?php

namespace App\Modules\Rbc\Contracts;

/**
 * Approximate location of an IP address (SRS-RBC-21). MAIS has not supplied
 * a GeoIP service yet, so MOD-RBC binds a local mock (MockGeoIpLocator);
 * swap the binding for a MaxMind/IP2Location adapter in production. Other
 * modules (e.g. MOD-AUD) may resolve this contract too.
 */
interface GeoIpLocator
{
    /**
     * @return array{lokasi: string, negara: string|null}
     */
    public function locate(?string $ip): array;
}
