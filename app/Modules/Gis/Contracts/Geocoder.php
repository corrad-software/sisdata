<?php

namespace App\Modules\Gis\Contracts;

use App\Models\User;

/**
 * Location search adapter (SRS-GIS-08 "search by location"). The default
 * implementation searches locally (lots, mukim/daerah/PBT names, mosques);
 * a Nominatim-compatible HTTP geocoder can be chained for street addresses
 * when MAIS approves an external service (parameter gis.geocoder).
 */
interface Geocoder
{
    /**
     * @return list<array{label: string, jenis: string, lng: float, lat: float, bbox?: array{0: float, 1: float, 2: float, 3: float}|null, id?: string|null, pautan?: string|null}>
     */
    public function search(string $query, User $user, int $limit = 10): array;
}
