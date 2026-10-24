<?php

namespace App\Modules\Ast\Support;

use App\Models\User;
use App\Modules\Ast\Models\AsetKontraktor;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use Illuminate\Database\Eloquent\Builder;

/**
 * Record-level rules on top of RBC permission + lot scope:
 *  - "Kontraktor penyelenggaraan — capaian terhad untuk mengemas kini status
 *    kerja": a user holding only ast.kerja.kemas_kini sees and updates only
 *    work orders of the contractor linked to their account;
 *  - only Bahagian Kewangan / authorised Unit Aset change values
 *    (ast.sulit.kemas_kini, never granted by wildcard base roles).
 */
class Akses
{
    public function pegawaiPenyelenggaraan(User $user): bool
    {
        return $user->can('ast.penyelenggaraan.lihat');
    }

    public function kontraktorSahaja(User $user): bool
    {
        return ! $this->pegawaiPenyelenggaraan($user) && $user->can('ast.kerja.kemas_kini');
    }

    /** @return list<int> contractor ids linked to the user's account */
    public function kontraktorPengguna(User $user): array
    {
        return AsetKontraktor::query()->where('id_pengguna', $user->id)->where('status_aktif', true)->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Work orders the user may see: officers by lot scope, contractors only their own.
     *
     * @param  Builder<RekodPenyelenggaraan>  $q
     * @return Builder<RekodPenyelenggaraan>
     */
    public function hadKerja(Builder $q, User $user): Builder
    {
        if ($this->pegawaiPenyelenggaraan($user)) {
            return $q;
        }
        if ($user->can('ast.kerja.kemas_kini')) {
            return $q->withoutGlobalScope('access')->whereIn('id_kontraktor', $this->kontraktorPengguna($user) ?: [0]);
        }

        return $q->whereRaw('false');
    }

    public function bolehUrusKerja(User $user, RekodPenyelenggaraan $k): bool
    {
        if ($user->can('ast.penyelenggaraan.kemas_kini')) {
            return true;
        }

        return $user->can('ast.kerja.kemas_kini') && $k->id_kontraktor !== null && in_array($k->id_kontraktor, $this->kontraktorPengguna($user), true);
    }

    public function bolehUbahNilai(User $user): bool
    {
        return $user->can('ast.sulit.kemas_kini');
    }
}
