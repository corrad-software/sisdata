<?php

namespace App\Modules\Ast\Models\Concerns;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Builder;

/**
 * RBC record scope via the parent lot (SRS-RBC-07/08): every MOD-AST table
 * carries the lot's kod_daerah / kod_mukim / klasifikasi (copied when the
 * asset is registered), so a district officer only sees assets on lots of
 * their district. `untuk($user)` applies an EXPLICIT user's scope for
 * contract callbacks (KPIs, profile sections, map layers, search).
 */
trait SkopLot
{
    use ScopedByAccess;

    public function accessScopeColumns(): array
    {
        return ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];
    }

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
