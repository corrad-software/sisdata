<?php

namespace App\Modules\Adm\Contracts;

use App\Models\User;

/**
 * Optional on a SearchProvider: detailed search (SRS-ADM-21) by structured
 * filters. Filter keys used across SISDATA: no_lot, mukim, daerah, status_tanah,
 * jenis_hak_milik, no_hak_milik, pemilik, pajakan, cukai, lokasi. A provider
 * ignores keys it doesn't know; providers without this interface are skipped
 * when filters are given.
 */
interface FilterableSearchProvider extends SearchProvider
{
    /**
     * @param  array<string, string>  $filters
     * @return list<array{id: string, title: string, subtitle?: string, link: string}>
     */
    public function searchWithFilters(string $query, array $filters, User $user, int $limit = 10): array;
}
