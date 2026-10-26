<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Support\NoLot;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one query every lot listing goes through (list, export, API, GeoJSON,
 * search): RBC record scope (via ScopedByAccess) + classification level +
 * the user's filters. No path can bypass it (SRS-RBC-08).
 */
class LotQuery
{
    public const FILTERS = ['q', 'kod_daerah', 'kod_mukim', 'bahagian_pemilik', 'status_lot', 'kategori_guna_tanah', 'status_pengesahan', 'klasifikasi', 'id_data_steward', 'tag', 'arkib', 'sintetik'];

    public function __construct(private Klasifikasi $klasifikasi, private AccessControl $access) {}

    /**
     * Base query scoped to $user (not necessarily the logged-in user — e.g. an
     * API token's owner or another module asking on someone's behalf): the
     * same RBC scope ScopedByAccess applies, computed for $user explicitly.
     *
     * @return Builder<LotTanah>
     */
    public function base(User $user): Builder
    {
        $query = LotTanah::query()->withoutGlobalScope('access');
        foreach ((new LotTanah)->accessScopeColumns() as $type => $column) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed !== null) {
                $query->whereIn('lot_tanah.'.$column, $allowed);
            }
        }

        return $this->klasifikasi->restrict($query, $user);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<LotTanah>
     */
    public function for(User $user, array $filters = []): Builder
    {
        $query = $this->base($user);

        $archived = $filters['arkib'] ?? false;
        if ($archived === 'sahaja') {
            $query->where('lot_tanah.status_lot', 'arkib');
        } elseif (! filter_var($archived, FILTER_VALIDATE_BOOL)) {
            $query->where('lot_tanah.status_lot', '!=', 'arkib');
        }

        if (! empty($filters['q'])) {
            $q = (string) $filters['q'];
            $key = NoLot::key($q);
            $query->where(fn (Builder $w) => $w
                ->where('lot_tanah.no_lot', 'ilike', '%'.$q.'%')
                ->orWhere('lot_tanah.nama_hartanah', 'ilike', '%'.$q.'%')
                ->orWhere('lot_tanah.no_lot_normal', 'like', '%'.$key.'%')
                ->orWhere('lot_tanah.kunci_asal', $q));
        }

        foreach (['kod_daerah', 'kod_mukim', 'bahagian_pemilik', 'status_lot', 'kategori_guna_tanah', 'klasifikasi', 'id_data_steward'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '' && $filters[$field] !== []) {
                $values = (array) $filters[$field];
                $query->whereIn('lot_tanah.'.$field, array_map('strval', $values));
            }
        }

        if (! empty($filters['tag'])) {
            $query->whereJsonContains('lot_tanah.tag', (string) $filters['tag']);
        }
        if (isset($filters['sintetik']) && $filters['sintetik'] !== '') {
            $query->where('lot_tanah.sintetik', filter_var($filters['sintetik'], FILTER_VALIDATE_BOOL));
        }

        if (! empty($filters['status_pengesahan'])) {
            $status = (string) $filters['status_pengesahan'];
            if ($status === 'tiada_geometri') {
                $query->whereDoesntHave('spatialSemasa');
            } elseif ($status === 'belum_disahkan_semua') {
                $query->where(fn (Builder $w) => $w->whereDoesntHave('spatialSemasa')
                    ->orWhereHas('spatialSemasa', fn (Builder $s) => $s->whereIn('status_pengesahan', ['belum_disahkan', 'bertindih'])));
            } else {
                $query->whereHas('spatialSemasa', fn (Builder $s) => $s->where('status_pengesahan', $status));
            }
        }

        return $query;
    }

    /** One lot by id, within the user's scope and classification, or null. */
    public function find(User $user, string $id, bool $withArchived = true): ?LotTanah
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $id)) {
            return null;
        }

        return $this->for($user, ['arkib' => $withArchived])->whereKey($id)->first();
    }
}
