<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\DeepLinkProvider;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Database\Eloquent\Builder;

/** SRS-GEN-05: tickets in global search — by number or title, only what the user may see (SRS-TKT-20). */
class TiketSearchProvider implements DeepLinkProvider, SearchProvider
{
    public function entity(): string
    {
        return 'tiket_sokongan';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        $like = '%'.addcslashes($q, '%_\\').'%';

        return Tiket::query()->bolehDilihat($user)
            ->where(fn (Builder $w) => $w->where('nombor_rujukan', 'ilike', $like)->orWhere('tajuk', 'ilike', $like))
            ->orderByDesc('tarikh_buka')->limit($limit)->get()
            ->map(fn (Tiket $t) => [
                'id' => $t->id,
                'title' => $t->nombor_rujukan.' — '.$t->tajuk,
                'subtitle' => (Lalai::STATUS[$t->status] ?? $t->status).' · '.(Lalai::KETERUKAN_RINGKAS[$t->keterukan] ?? '').' · '.(Lalai::MODUL[$t->modul ?? 'umum'] ?? $t->modul),
                'link' => '/admin/tkt/tiket/'.$t->id,
            ])->values()->all();
    }

    public function link(string $id, User $user): ?string
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $id)) {
            return null;
        }

        return Tiket::query()->bolehDilihat($user)->whereKey($id)->exists() ? '/admin/tkt/tiket/'.$id : null;
    }
}
