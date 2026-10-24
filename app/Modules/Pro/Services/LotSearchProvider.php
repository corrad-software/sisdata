<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\DeepLinkProvider;
use App\Modules\Adm\Contracts\FilterableSearchProvider;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Rbc\Contracts\AccessControl;

/** Lots in global / detailed search (SRS-GEN-05, SRS-ADM-20/21), always within the user's scope. */
class LotSearchProvider implements DeepLinkProvider, FilterableSearchProvider
{
    public function __construct(private LotQuery $lots, private RujukanInduk $ref, private AccessControl $access) {}

    public function entity(): string
    {
        return 'lot_tanah';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        return $this->searchWithFilters($query, [], $user, $limit);
    }

    public function searchWithFilters(string $query, array $filters, User $user, int $limit = 10): array
    {
        if (! $this->access->allows($user, 'pro.lot.lihat')) {
            return [];
        }
        $f = ['q' => $query !== '' ? $query : ($filters['no_lot'] ?? null)];
        if (! empty($filters['mukim'])) {
            $f['kod_mukim'] = $filters['mukim'];
        }
        if (! empty($filters['daerah'])) {
            $f['kod_daerah'] = $filters['daerah'];
        }
        if (! empty($filters['status_tanah'])) {
            $f['status_lot'] = $filters['status_tanah'];
        }
        if ($f['q'] === null && count($f) === 1) {
            return [];
        }

        return $this->lots->for($user, $f)->orderBy('no_lot')->limit($limit)->get()
            ->map(fn (LotTanah $l) => [
                'id' => $l->id,
                'title' => $l->no_lot.' · '.($this->ref->namaMukim($l->kod_mukim) ?? $l->kod_mukim),
                'subtitle' => trim(($this->ref->namaDaerah($l->kod_daerah) ?? $l->kod_daerah).' · '.__('pro::pro.status_lot.'.$l->status_lot)),
                'link' => '/admin/pro/lot/'.$l->id,
            ])->all();
    }

    public function link(string $id, User $user): ?string
    {
        if (! $this->access->allows($user, 'pro.lot.lihat')) {
            return null;
        }

        return $this->lots->find($user, $id) !== null ? '/admin/pro/lot/'.$id : null;
    }
}
