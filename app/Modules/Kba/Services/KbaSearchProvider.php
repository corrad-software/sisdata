<?php

namespace App\Modules\Kba\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Reindexable;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Kba\Jobs\IndeksSumber;
use App\Modules\Kba\Models\SumberDokumen;

/**
 * Joins MOD-ADM's global search (SRS-ADM-20, SRS-GEN-05) with keyword hits
 * over knowledge-base passages the user may open. "Reindex" from the search
 * settings screen re-queues every active source.
 */
class KbaSearchProvider implements Reindexable, SearchProvider
{
    public function __construct(private Pencari $pencari) {}

    public function entity(): string
    {
        return 'kba_pengetahuan';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        if (! $user->can('kba.soalan.lihat')) {
            return [];
        }

        return array_map(fn (array $p) => [
            'id' => (string) $p['id_segmen'],
            'title' => $p['dokumen'].(($s = Penjawab::seksyen($p)) !== null ? ' · '.$s : ''),
            'subtitle' => Penjawab::petikan($p['kandungan'], Teks::contentTokens($query), 140),
            'link' => $p['pautan'],
        ], $this->pencari->cari($query, $user, $limit, semantik: false));
    }

    public function reindex(): int
    {
        $n = 0;
        foreach (SumberDokumen::query()->where('aktif', true)->pluck('id') as $id) {
            IndeksSumber::dispatch((int) $id, false);
            $n++;
        }

        return $n;
    }
}
