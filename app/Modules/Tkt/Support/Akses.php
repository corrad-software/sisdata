<?php

namespace App\Modules\Tkt\Support;

use App\Models\User;
use App\Modules\Tkt\Models\Tiket;

/**
 * SRS-TKT-20 record-level decisions on one ticket (the list/route scope is
 * Tiket::bolehDilihat). "As support" = may see internal notes and act on
 * it: assigned to me, my team / my team's modules, or support management
 * (tkt.semua.pantau); classified / security tickets also need
 * tkt.sulit.lihat.
 */
class Akses
{
    /** @var array<int, array{0: list<int>, 1: list<string>, 2: bool}> */
    private array $cache = [];

    public function pelapor(User $user, Tiket $tiket): bool
    {
        return $tiket->id_pelapor !== null && $tiket->id_pelapor === $user->id;
    }

    public function sokongan(User $user, Tiket $tiket): bool
    {
        $all = $user->can('tkt.semua.pantau');
        if (! $all && ! $user->can('tkt.sokongan.lihat')) {
            return false;
        }
        if ($tiket->terhad() && ! $user->can('tkt.sulit.lihat')) {
            return false;
        }
        if ($all || $tiket->id_juruteknik === $user->id) {
            return true;
        }
        [$teams, $modules, $every] = $this->cache[$user->id] ??= Tiket::liputanPasukan($user);

        return $every || in_array((int) $tiket->id_pasukan, $teams, true) || ($tiket->modul !== null && in_array($tiket->modul, $modules, true));
    }

    public function bolehUrus(User $user, Tiket $tiket): bool
    {
        return $user->can('tkt.sokongan.kemas_kini') && $this->sokongan($user, $tiket);
    }

    public function bolehBalasPelapor(User $user, Tiket $tiket): bool
    {
        return $this->pelapor($user, $tiket) && $user->can('tkt.tiket.kemas_kini');
    }
}
