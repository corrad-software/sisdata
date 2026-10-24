<?php

namespace App\Modules\Aud\Services\Geo;

/** Approximate location of an IP address (SRS-AUD-19). */
interface GeoIpResolver
{
    public function locate(?string $ip): ?string;
}
