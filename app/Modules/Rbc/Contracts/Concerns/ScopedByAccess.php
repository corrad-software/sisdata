<?php

namespace App\Modules\Rbc\Contracts\Concerns;

use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;

/**
 * Record-level scoping (SRS-RBC-07, ADR-06). A model using this declares which
 * columns carry which scope type:
 *
 *     protected array $accessScopes = ['daerah' => 'kod_daerah', 'klasifikasi' => 'klasifikasi'];
 *
 * and every query made on behalf of the logged-in user is filtered to that
 * user's scope — API, export, GIS and BI all go through the same query, so
 * none can bypass it (SRS-RBC-08). Console/queue code with no user is not
 * filtered; use `withoutGlobalScope('access')` explicitly when a user-facing
 * path really needs everything.
 */
trait ScopedByAccess
{
    public static function bootScopedByAccess(): void
    {
        static::addGlobalScope('access', function (Builder $query) {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            $access = app(AccessControl::class);
            $model = $query->getModel();

            foreach ($model->accessScopeColumns() as $type => $column) {
                $allowed = $access->scope($user, $type);
                if ($allowed !== null) {
                    $query->whereIn($model->qualifyColumn($column), $allowed);
                }
            }
        });
    }

    /**
     * @return array<string, string> scope type => column
     */
    public function accessScopeColumns(): array
    {
        return get_object_vars($this)['accessScopes'] ?? [];
    }
}
