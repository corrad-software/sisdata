<?php

namespace App\Modules\Cki\Support;

use App\Models\User;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Models\Transaksi;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Record scoping for an explicit user (profile sections, map layers, KPIs,
 * reminders and search get a $user that may not be the request's auth user).
 * Same rules as ScopedByAccess — always through MOD-RBC's AccessControl
 * ("capaian rekod cukai ditapis mengikut daerah bagi pegawai daerah").
 */
class Akses
{
    public function __construct(private AccessControl $access) {}

    /** @return Builder<Cukai> */
    public function cukai(User $user): Builder
    {
        return $this->apply(Cukai::query()->withoutGlobalScope('access'), $user, ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi']);
    }

    /** @return Builder<Obligasi> */
    public function obligasi(User $user): Builder
    {
        return $this->apply(Obligasi::query()->withoutGlobalScope('access'), $user, ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi']);
    }

    /** @return Builder<Transaksi> */
    public function transaksi(User $user): Builder
    {
        return $this->apply(Transaksi::query()->withoutGlobalScope('access'), $user, ['daerah' => 'kod_daerah']);
    }

    /** Whether $user's district/mukim scope covers a record located in ($daerah, $mukim). */
    public function meliputi(User $user, ?string $daerah, ?string $mukim = null): bool
    {
        $d = $this->access->scope($user, AccessControl::SCOPE_DAERAH);
        $m = $this->access->scope($user, AccessControl::SCOPE_MUKIM);

        return ($d === null || in_array((string) $daerah, $d, true)) && ($m === null || $mukim === null || in_array($mukim, $m, true));
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $q
     * @param  array<string, string>  $columns
     * @return Builder<T>
     */
    private function apply(Builder $q, User $user, array $columns): Builder
    {
        $table = $q->getModel()->getTable();
        foreach ($columns as $type => $column) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed !== null) {
                $q->whereIn($table.'.'.$column, $allowed);
            }
        }

        return $q;
    }
}
