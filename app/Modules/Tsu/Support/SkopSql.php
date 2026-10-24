<?php

namespace App\Modules\Tsu\Support;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/** RBC scope of urusan_tanah (daerah / mukim / klasifikasi) as raw SQL for map layers (SRS-RBC-08: GIS cannot bypass it). */
class SkopSql
{
    public function __construct(private AccessControl $access) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: list<mixed>}
     */
    public function where(User $user, string $alias = 'u', array $filters = []): array
    {
        $where = ['true'];
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
        foreach (['kod_daerah', 'kod_mukim', 'jenis_urusan'] as $col) {
            if (! empty($filters[$col]) && is_string($filters[$col])) {
                $where[] = "{$alias}.{$col} = ?";
                $bindings[] = $filters[$col];
            }
        }

        return [implode(' AND ', $where), $bindings];
    }
}
