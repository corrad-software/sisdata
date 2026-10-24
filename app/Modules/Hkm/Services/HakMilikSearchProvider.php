<?php

namespace App\Modules\Hkm\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\DeepLinkProvider;
use App\Modules\Adm\Contracts\FilterableSearchProvider;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Support\Akses;
use App\Modules\Hkm\Support\RefLookup;
use Illuminate\Support\Str;

/**
 * SRS-GEN-05 / SRS-ADM-20/21: titles in ADM global and detailed search,
 * filtered by the searching user's access.
 */
class HakMilikSearchProvider implements DeepLinkProvider, FilterableSearchProvider
{
    public function __construct(private Akses $akses, private TitleSearch $search, private RefLookup $ref) {}

    public function entity(): string
    {
        return 'hak_milik';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        return $this->searchWithFilters($query, [], $user, $limit);
    }

    public function searchWithFilters(string $query, array $filters, User $user, int $limit = 10): array
    {
        $mapped = array_filter([
            'q' => trim($query) !== '' ? $query : null,
            'no_lot' => $filters['no_lot'] ?? null,
            'kod_mukim' => $filters['mukim'] ?? null,
            'kod_daerah' => $filters['daerah'] ?? null,
            'status' => $filters['status_tanah'] ?? null,
            'jenis_hak_milik' => $filters['jenis_hak_milik'] ?? null,
            'no_hak_milik' => $filters['no_hak_milik'] ?? null,
            'pemilik' => $filters['pemilik'] ?? null,
        ]);
        if ($mapped === []) {
            return [];
        }

        return $this->search->query($mapped, $this->akses->titles($user))
            ->orderBy('no_hak_milik')->limit($limit)->get()
            ->map(fn (HakMilik $h) => [
                'id' => $h->id,
                'title' => $h->no_hak_milik,
                'subtitle' => trim(($this->ref->namaJenis($h->jenis_hak_milik) ?? '').' · '.($this->ref->namaMukim($h->kod_mukim) ?? $h->kod_mukim)),
                'link' => '/admin/hkm/hak-milik/'.$h->id,
            ])->values()->all();
    }

    public function link(string $id, User $user): ?string
    {
        return Str::isUuid($id) && $this->akses->titles($user)->whereKey($id)->exists() ? '/admin/hkm/hak-milik/'.$id : null;
    }
}
