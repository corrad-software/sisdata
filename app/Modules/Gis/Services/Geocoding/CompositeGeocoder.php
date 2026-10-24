<?php

namespace App\Modules\Gis\Services\Geocoding;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Gis\Contracts\Geocoder;

/** Local results first; external address geocoder appended when enabled (gis.geocoder = nominatim). */
class CompositeGeocoder implements Geocoder
{
    public function __construct(private LocalGeocoder $local, private Parameters $parameters) {}

    public function search(string $query, User $user, int $limit = 10): array
    {
        $results = $this->local->search($query, $user, $limit);
        if ($this->parameters->get('gis.geocoder', 'tempatan') === 'nominatim') {
            $external = new NominatimGeocoder((string) $this->parameters->get('gis.nominatim_url', ''));
            $results = [...$results, ...$external->search($query, $user, $limit)];
        }

        return $results;
    }
}
