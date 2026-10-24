<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;

/** SRS-GEN-05: global search over urusan reference numbers / title, within the user's scope. */
class UrusanSearchProvider implements SearchProvider
{
    public function entity(): string
    {
        return 'urusan_tanah';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        if (! $user->can('tsu.urusan.lihat') || mb_strlen(trim($query)) < 2) {
            return [];
        }
        $q = '%'.trim($query).'%';

        return UrusanTanah::query()->untuk($user)
            ->where(fn ($w) => $w->where('no_rujukan_mais', 'ilike', $q)->orWhere('no_rujukan_ptd', 'ilike', $q)->orWhere('no_rujukan_etanah', 'ilike', $q)->orWhere('tajuk', 'ilike', $q))
            ->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn (UrusanTanah $u) => ['id' => $u->id, 'title' => $u->no_rujukan_mais.' — '.$u->jenisLabel(), 'subtitle' => $u->tajuk.' · '.(Lalai::STATUS_URUSAN[$u->status] ?? $u->status), 'link' => '/admin/tsu/urusan/'.$u->id])
            ->values()->all();
    }
}
