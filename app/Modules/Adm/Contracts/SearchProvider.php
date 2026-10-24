<?php

namespace App\Modules\Adm\Contracts;

use App\Models\User;

/**
 * A module's contribution to global search (SRS-ADM-20/21, SRS-GEN-05).
 * Register implementations with SearchProviders::register(). Results must be
 * filtered by the user's access — return only what $user may open.
 */
interface SearchProvider
{
    /** Entity key shown as a result group, e.g. "lot_tanah". */
    public function entity(): string;

    /**
     * @return list<array{id: string, title: string, subtitle?: string, link: string}>
     */
    public function search(string $query, User $user, int $limit = 10): array;
}
