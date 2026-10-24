<?php

namespace App\Modules\Pjk\Support;

use App\Models\User;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;

/**
 * Record scoping for an explicit user (profile sections, map layers, KPIs and
 * search are called with a $user that may not be the request's auth user).
 * Same rules as ScopedByAccess — always through MOD-RBC's AccessControl.
 */
class Akses
{
    public function __construct(private AccessControl $access) {}

    /** @return Builder<Kontrak> */
    public function kontrak(User $user): Builder
    {
        return $this->apply(Kontrak::query()->withoutGlobalScope('access'), $user, ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'], 'kontrak_pajakan');
    }

    /** @return Builder<PremisAset> */
    public function premis(User $user): Builder
    {
        return $this->apply(PremisAset::query()->withoutGlobalScope('access'), $user, ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'], 'premis_aset');
    }

    /**
     * Tenants: unrestricted users see all; a district-scoped officer sees the
     * tenants of contracts in their scope, plus tenants they registered.
     *
     * @return Builder<Penyewa>
     */
    public function penyewa(User $user): Builder
    {
        $q = Penyewa::query();
        $klas = $this->access->scope($user, AccessControl::SCOPE_KLASIFIKASI);
        if ($klas !== null && ! in_array('sulit', $klas, true)) {
            return $q->whereRaw('1 = 0');
        }
        $daerah = $this->access->scope($user, AccessControl::SCOPE_DAERAH);
        $mukim = $this->access->scope($user, AccessControl::SCOPE_MUKIM);
        if ($daerah === null && $mukim === null) {
            return $q;
        }

        return $q->where(function ($w) use ($user) {
            $w->where('id_pencipta', $user->id)
                ->orWhereIn('id', $this->kontrak($user)->select('penyewa_id')->whereNotNull('penyewa_id'));
        });
    }

    public function bolehLihatPenuh(User $user): bool
    {
        return $this->access->allows($user, 'pjk.sulit.lihat');
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<T>  $q
     * @param  array<string, string>  $columns
     * @return Builder<T>
     */
    private function apply(Builder $q, User $user, array $columns, string $table): Builder
    {
        foreach ($columns as $type => $column) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed !== null) {
                $q->whereIn($table.'.'.$column, $allowed);
            }
        }

        return $q;
    }
}
