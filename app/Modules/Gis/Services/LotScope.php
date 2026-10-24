<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * The SQL form of MOD-PRO's lot visibility rule, for MOD-GIS's bulk spatial
 * queries over lot_tanah (read-only): the user's RBC scope (daerah, mukim,
 * klasifikasi — AccessControl::scope) plus the classification levels a user
 * may see (Sulit/Rahsia only with pro.sulit.lihat / pro.rahsia.lihat), and
 * never archived or deleted lots. Same rule as Pro\Services\LotQuery::base(),
 * which GIS may not call directly (module boundary).
 */
class LotScope
{
    public const LEVEL_PERMISSION = ['sulit' => 'pro.sulit.lihat', 'rahsia' => 'pro.rahsia.lihat'];

    public function __construct(private AccessControl $access) {}

    /**
     * WHERE fragment (no leading AND) restricting lot alias $alias.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public function where(User $user, string $alias = 'l'): array
    {
        $sql = ["{$alias}.deleted_at IS NULL", "{$alias}.status_lot NOT IN ('arkib', 'digabung')"];
        $bindings = [];

        foreach (['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'] as $type => $column) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed === null) {
                continue;
            }
            if ($allowed === []) {
                $sql[] = 'FALSE';

                continue;
            }
            $sql[] = "{$alias}.{$column} IN (".implode(',', array_fill(0, count($allowed), '?')).')';
            array_push($bindings, ...$allowed);
        }

        $levels = $this->levels($user);
        $sql[] = "{$alias}.klasifikasi IN (".implode(',', array_fill(0, count($levels), '?')).')';
        array_push($bindings, ...$levels);

        return [implode(' AND ', $sql), $bindings];
    }

    /**
     * Scope restriction for another table that carries kod_daerah/kod_mukim
     * columns (e.g. pemerolehan, gis_aset_titik); null columns are skipped.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public function districtWhere(User $user, string $alias, ?string $daerahColumn = 'kod_daerah', ?string $mukimColumn = 'kod_mukim'): array
    {
        $sql = ['TRUE'];
        $bindings = [];
        foreach (['daerah' => $daerahColumn, 'mukim' => $mukimColumn] as $type => $column) {
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
            $sql[] = "{$alias}.{$column} IN (".implode(',', array_fill(0, count($allowed), '?')).')';
            array_push($bindings, ...$allowed);
        }

        return [implode(' AND ', $sql), $bindings];
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

    /** Hash of what decides lot visibility for $user (cache key for tiles). */
    public function signature(User $user, string $extra = ''): string
    {
        $parts = ['levels' => $this->levels($user)];
        foreach (['daerah', 'mukim', 'klasifikasi'] as $type) {
            $scope = $this->access->scope($user, $type);
            if ($scope !== null) {
                sort($scope);
            }
            $parts[$type] = $scope;
        }

        return substr(sha1((string) json_encode($parts).'|'.$extra), 0, 16);
    }
}
