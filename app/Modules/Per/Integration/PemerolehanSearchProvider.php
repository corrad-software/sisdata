<?php

namespace App\Modules\Per\Integration;

use App\Models\User;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Rbc\Contracts\AccessControl;

/** SRS-GEN-05: acquisition cases in global search (case no., title, expected lot no.). */
class PemerolehanSearchProvider implements SearchProvider
{
    public function __construct(private AccessControl $access) {}

    public function entity(): string
    {
        return 'pemerolehan';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        if (! $this->access->allows($user, 'per.kes.lihat') || mb_strlen(trim($query)) < 2) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($query)).'%';

        return Pemerolehan::query()->visibleTo($user)->with('saluran')
            ->where(fn ($q) => $q->where('no_kes', 'ilike', $like)->orWhere('tajuk', 'ilike', $like)->orWhere('no_lot_dijangka', 'ilike', $like))
            ->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn (Pemerolehan $c) => [
                'id' => $c->id,
                'title' => $c->no_kes.' — '.$c->tajuk,
                'subtitle' => $c->saluran->nama_saluran,
                'link' => $c->pautan(),
            ])->values()->all();
    }
}
