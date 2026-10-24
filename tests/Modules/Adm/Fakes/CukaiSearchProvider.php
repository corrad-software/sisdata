<?php

namespace Tests\Modules\Adm\Fakes;

use App\Models\User;
use App\Modules\Adm\Contracts\SearchProvider;

/** A second module's provider (no deep-link interface → resolver falls back to search). */
class CukaiSearchProvider implements SearchProvider
{
    public function entity(): string
    {
        return 'cukai';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        if ($query !== 'C9' && stripos('PT 1234', $query) === false && stripos('BIL-2026-01', $query) === false) {
            return [];
        }

        return [['id' => 'C9', 'title' => 'BIL-2026-01 (PT 1234)', 'subtitle' => 'Cukai tanah 2026', 'link' => '/admin/cki/bil/C9']];
    }
}
