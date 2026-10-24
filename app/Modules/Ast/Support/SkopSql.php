<?php

namespace App\Modules\Ast\Support;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * The RBC record scope (daerah / mukim / klasifikasi copied from the lot) as
 * SQL for raw queries — map layer and vector tiles — so GIS can never show
 * an asset the user may not open (SRS-RBC-08).
 */
class SkopSql
{
    public function __construct(private AccessControl $access) {}

    /**
     * @param  array<string, mixed>  $filters  kod_daerah, kod_mukim, status_kondisi, kategori (kod), jenis_aset
     * @return array{0: string, 1: list<mixed>}
     */
    public function where(User $user, string $alias = 'a', array $filters = []): array
    {
        $where = ["{$alias}.status_arkib = false"];
        $bindings = [];
        foreach (['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'] as $type => $col) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed === null) {
                continue;
            }
            if ($allowed === []) {
                $where[] = 'false';

                continue;
            }
            $where[] = "{$alias}.{$col} IN (".implode(',', array_fill(0, count($allowed), '?')).')';
            array_push($bindings, ...$allowed);
        }
        foreach (['kod_daerah', 'kod_mukim', 'status_kondisi', 'jenis_aset'] as $col) {
            if (! empty($filters[$col]) && is_string($filters[$col])) {
                $where[] = "{$alias}.{$col} = ?";
                $bindings[] = $filters[$col];
            }
        }
        if (! empty($filters['kategori']) && is_string($filters['kategori'])) {
            $where[] = "{$alias}.id_kategori IN (SELECT id FROM aset_kategori WHERE kod = ?)";
            $bindings[] = $filters['kategori'];
        }

        return [implode(' AND ', $where), $bindings];
    }

    public function signature(User $user, string $salt): string
    {
        $parts = [$salt];
        foreach (['daerah', 'mukim', 'klasifikasi'] as $type) {
            $v = $this->access->scope($user, $type);
            $parts[] = $type.'='.($v === null ? '*' : implode(',', $v));
        }

        return substr(sha1(implode('|', $parts)), 0, 16);
    }
}
