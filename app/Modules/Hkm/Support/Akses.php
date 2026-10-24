<?php

namespace App\Modules\Hkm\Support;

use App\Models\User;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;

/**
 * Title queries on behalf of a GIVEN user (contract callers: profile sections,
 * search providers, TitleRegistry::forLot) — the ScopedByAccess global scope
 * only knows the logged-in user, so here it is replaced by that user's scope.
 */
class Akses
{
    public function __construct(private AccessControl $access) {}

    /** @return Builder<HakMilik> */
    public function titles(User $user): Builder
    {
        $query = HakMilik::query()->withoutGlobalScope('access');
        if (! $this->access->allows($user, 'hkm.hak_milik.lihat')) {
            return $query->whereRaw('false');
        }

        $model = new HakMilik;
        foreach ($model->accessScopeColumns() as $type => $column) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed !== null) {
                $query->whereIn($model->qualifyColumn($column), $allowed);
            }
        }

        return $query;
    }
}
