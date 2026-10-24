<?php

namespace App\Modules\Adu\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\DeepLinkProvider;
use App\Modules\Adm\Contracts\FilterableSearchProvider;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use Illuminate\Database\Eloquent\Builder;

/**
 * SRS-GEN-05 / SRS-ADM-20..22: complaints in global search — by reference
 * number, description or location text; detailed filters daerah / mukim /
 * status / lokasi. Results are scoped (ScopedByAccess) and require
 * adu.aduan.lihat. Reporter identity is never searchable.
 */
class AduanSearchProvider implements DeepLinkProvider, FilterableSearchProvider
{
    public function __construct(private Rujukan $ref) {}

    public function entity(): string
    {
        return 'aduan';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        return $this->searchWithFilters($query, [], $user, $limit);
    }

    public function searchWithFilters(string $query, array $filters, User $user, int $limit = 10): array
    {
        if (! $user->can('adu.aduan.lihat')) {
            return [];
        }
        $q = trim($query);

        return Aduan::query()->bolehDilihat($user)
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('no_rujukan_aduan', 'ilike', '%'.$q.'%')
                ->orWhere('keterangan', 'ilike', '%'.$q.'%')
                ->orWhere('lokasi_teks', 'ilike', '%'.$q.'%')))
            ->when($filters['daerah'] ?? null, fn (Builder $b, $v) => $b->where('kod_daerah', $v))
            ->when($filters['mukim'] ?? null, fn (Builder $b, $v) => $b->where('kod_mukim', $v))
            ->when($filters['lokasi'] ?? null, fn (Builder $b, $v) => $b->where('lokasi_teks', 'ilike', '%'.$v.'%'))
            ->when($filters['status_aduan'] ?? null, fn (Builder $b, $v) => $b->where('status', $v))
            ->orderByDesc('tarikh_terima')->limit($limit)->get()
            ->map(fn (Aduan $a) => [
                'id' => $a->id,
                'title' => $a->no_rujukan_aduan.' — '.$this->ref->namaKategori($a->kategori),
                'subtitle' => (Lalai::STATUS[$a->status] ?? $a->status).' · '.($this->ref->namaDaerah($a->kod_daerah) ?? '—').' · '.mb_strimwidth((string) $a->keterangan, 0, 80, '…'),
                'link' => '/admin/adu/aduan/'.$a->id,
            ])->values()->all();
    }

    public function link(string $id, User $user): ?string
    {
        if (! $user->can('adu.aduan.lihat') || ! preg_match('/^[0-9a-f-]{36}$/i', $id)) {
            return null;
        }

        return Aduan::query()->bolehDilihat($user)->whereKey($id)->exists() ? '/admin/adu/aduan/'.$id : null;
    }
}
