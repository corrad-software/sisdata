<?php

namespace App\Modules\Tsu\Models\Concerns;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scope a query to an EXPLICIT user's access (instead of the logged-in one
 * that ScopedByAccess uses) — for contract callbacks that receive a $user:
 * KPIs, profile sections, map layers, search, reports (SRS-RBC-08).
 */
trait UntukPengguna
{
    /** @param Builder<static> $query */
    public function scopeUntuk(Builder $query, User $user): void
    {
        $query->withoutGlobalScope('access');
        $access = app(AccessControl::class);
        foreach ($this->accessScopeColumns() as $type => $column) {
            $allowed = $access->scope($user, $type);
            if ($allowed !== null) {
                $query->whereIn($this->qualifyColumn($column), $allowed);
            }
        }
    }
}
