<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * RBAC scope as SQL for MOD-DSH's read-only analytics queries over other
 * modules' tables (SRS-DSH-13, "row-level" per the security controls): the
 * user's daerah / mukim / bahagian / klasifikasi scope from
 * AccessControl::scope(), plus the Sulit/Rahsia levels the user may see.
 * Applied inside the query, never on the rendered result.
 */
class Skop
{
    /** Lot classification levels beyond Dalaman need an explicit grant (same rule as MOD-PRO / MOD-GIS). */
    public const LEVEL_PERMISSION = ['sulit' => 'pro.sulit.lihat', 'rahsia' => 'pro.rahsia.lihat'];

    public const SCOPE_TYPES = ['daerah', 'mukim', 'bahagian', 'klasifikasi'];

    public function __construct(private AccessControl $access) {}

    /**
     * WHERE fragment (no leading AND) restricting $alias by the user's scope
     * on the given columns (scope type => column; null/absent = not applied).
     *
     * @param  array<string, string|null>  $columns
     * @return array{0: string, 1: list<mixed>}
     */
    public function where(User $user, string $alias, array $columns): array
    {
        $sql = ['TRUE'];
        $bindings = [];
        foreach ($columns as $type => $column) {
            if ($column === null) {
                continue;
            }
            $allowed = $this->access->scope($user, $type);
            if ($allowed === null) {
                continue;
            }
            if ($allowed === []) {
                $sql[] = 'FALSE';

                continue;
            }
            $sql[] = "{$alias}.{$column} IN (".$this->marks($allowed).')';
            array_push($bindings, ...$allowed);
        }

        return [implode(' AND ', $sql), $bindings];
    }

    /**
     * MOD-PRO's lot visibility rule: scope + classification levels, never
     * archived / merged / deleted lots.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public function lot(User $user, string $alias = 'l'): array
    {
        [$sql, $bindings] = $this->where($user, $alias, ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'bahagian' => 'bahagian_pemilik', 'klasifikasi' => 'klasifikasi']);
        $levels = $this->levels($user);
        $sql .= " AND {$alias}.deleted_at IS NULL AND {$alias}.status_lot NOT IN ('arkib', 'digabung') AND {$alias}.klasifikasi IN (".$this->marks($levels).')';

        return [$sql, [...$bindings, ...$levels]];
    }

    /** @return list<string> */
    public function levels(User $user): array
    {
        $levels = ['awam', 'dalaman'];
        foreach (self::LEVEL_PERMISSION as $level => $permission) {
            if ($this->access->allows($user, $permission)) {
                $levels[] = $level;
            }
        }

        return $levels;
    }

    /**
     * The user's scope per dimension (null = unrestricted).
     *
     * @return array<string, list<string>|null>
     */
    public function scopes(User $user): array
    {
        $out = [];
        foreach (self::SCOPE_TYPES as $type) {
            $scope = $this->access->scope($user, $type);
            if ($scope !== null) {
                sort($scope);
            }
            $out[$type] = $scope;
        }

        return $out;
    }

    /** True when $kodDaerah lies outside the user's district scope. */
    public function outsideDistrict(User $user, ?string $kodDaerah): bool
    {
        if ($kodDaerah === null || $kodDaerah === '') {
            return false;
        }
        $allowed = $this->access->scope($user, 'daerah');

        return $allowed !== null && ! in_array($kodDaerah, $allowed, true);
    }

    /**
     * Hash of everything that decides what $user sees (scope, levels,
     * permissions): the cache key for KPI results (SRS-DSH-13 performance).
     */
    public function signature(User $user): string
    {
        $permissions = $this->access->permissions($user);
        sort($permissions);

        return substr(sha1((string) json_encode([$this->scopes($user), $this->levels($user), $permissions])), 0, 20);
    }

    /** @param list<mixed> $values */
    public function marks(array $values): string
    {
        return implode(',', array_fill(0, max(1, count($values)), '?'));
    }
}
