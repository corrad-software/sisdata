<?php

namespace App\Modules\Ptn\Support;

use App\Models\User;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\TindakanPembetulan;
use App\Modules\Wfe\Contracts\ApproverDirectory;

/**
 * Record-level rules on top of RBC permission + daerah/mukim scope:
 *  - security control "pegawai lapangan hanya melihat tugasan yang diumpukkan
 *    kepadanya": a user holding only ptn.lapangan.* sees and records only
 *    visits of schedules assigned to them;
 *  - supervisors (ptn.lawatan.kemas_kini or the officer's supervisor in the
 *    hierarchy) review photos and resolve sync conflicts.
 */
class Akses
{
    public function __construct(private ApproverDirectory $directory) {}

    public function lihatSemua(User $user): bool
    {
        return $user->can('ptn.lawatan.lihat');
    }

    public function bolehLihatLawatan(User $user, RekodLawatan $lawatan): bool
    {
        return $this->lihatSemua($user) || ($user->can('ptn.lapangan.lihat') && $lawatan->id_pegawai === $user->id);
    }

    public function bolehRekod(User $user, JadualPemantauan $jadual): bool
    {
        if ($user->can('ptn.lawatan.cipta')) {
            return true;
        }

        return $user->can('ptn.lapangan.kemas_kini') && $jadual->id_pegawai_bertugas === $user->id;
    }

    public function bolehUbahLawatan(User $user, RekodLawatan $lawatan): bool
    {
        return $lawatan->id_pegawai === $user->id && ($user->can('ptn.lapangan.kemas_kini') || $user->can('ptn.lawatan.cipta'));
    }

    public function penyelia(User $user, ?User $pegawai): bool
    {
        if ($user->can('ptn.lawatan.kemas_kini')) {
            return true;
        }

        return $pegawai !== null && collect($this->directory->supervisorsOf($pegawai))->contains(fn (User $s) => $s->id === $user->id);
    }

    public function bolehUrusTindakan(User $user, TindakanPembetulan $t): bool
    {
        return $user->can('ptn.isu.kemas_kini') || ($t->id_pegawai_bertanggungjawab === $user->id && $user->can('ptn.tindakan.kemas_kini'));
    }

    /** @return list<User> */
    public function penyeliaKepada(?User $pegawai): array
    {
        return $pegawai === null ? [] : array_slice($this->directory->supervisorsOf($pegawai), 0, 2);
    }
}
