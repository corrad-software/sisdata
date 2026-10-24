<?php

namespace App\Modules\Ptn\Support;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * The lot-level RBC scope as SQL (daerah / mukim / klasifikasi of
 * lot_tanah) for MOD-PTN's map layers and lot lists — the same rule MOD-PRO
 * applies, so GIS cannot show a lot the user may not open (SRS-RBC-08).
 */
class LotSql
{
    public function __construct(private AccessControl $access) {}

    /**
     * @param  array<string, mixed>  $filters  kod_daerah, kod_mukim
     * @return array{0: string, 1: list<mixed>}
     */
    public function where(User $user, string $alias = 'l', array $filters = []): array
    {
        $where = ["{$alias}.deleted_at IS NULL"];
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
        foreach (['kod_daerah', 'kod_mukim'] as $col) {
            if (! empty($filters[$col]) && is_string($filters[$col])) {
                $where[] = "{$alias}.{$col} = ?";
                $bindings[] = $filters[$col];
            }
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
