<?php

namespace App\Modules\Dms\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Dms\Models\Dokumen;

/** SRS-GEN-05 / SRS-ADM-20: documents in global search, access-filtered. */
class DokumenSearchProvider implements SearchProvider
{
    public function __construct(private DocumentSearch $search) {}

    public function entity(): string
    {
        return 'dokumen';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        return $this->search->query($user, ['q' => $query])
            ->limit($limit)
            ->get()
            ->map(fn (Dokumen $d) => [
                'id' => $d->id,
                'title' => $d->tajuk,
                'subtitle' => $d->no_rujukan.' · '.$d->nama_fail,
                'link' => '/admin/dms/'.$d->id,
            ])
            ->values()
            ->all();
    }
}
