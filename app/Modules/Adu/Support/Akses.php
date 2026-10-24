<?php

namespace App\Modules\Adu\Support;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Wfe\Contracts\ApproverDirectory;

/**
 * Record-level rules on top of the RBC permission + scope checks:
 *  - SRS-ADU-20: who may see the reporter's identity — the assigned officer,
 *    that officer's supervisor, and Unit Aduan (adu.sulit.lihat);
 *  - who may work a complaint — the assigned officer, its supervisor, or the
 *    Unit Aduan coordinators (adu.penugasan.kemas_kini).
 * Supervisors come from Wfe\Contracts\ApproverDirectory (MOD-RBC binds the
 * real hierarchy); `aduan.id_penyelia` is the supervisor fixed at assignment.
 */
class Akses
{
    public function __construct(private ApproverDirectory $directory, private Parameters $parameters) {}

    /** pegawai_ditugaskan | penyelia | unit_aduan | null */
    public function asasIdentiti(User $user, Aduan $aduan): ?string
    {
        if ($aduan->id_pegawai !== null && $aduan->id_pegawai === $user->id) {
            return 'pegawai_ditugaskan';
        }
        if ($this->penyeliaKepada($user, $aduan)) {
            return 'penyelia';
        }
        if ($user->can('adu.sulit.lihat')) {
            return 'unit_aduan';
        }

        return null;
    }

    public function bolehUrus(User $user, Aduan $aduan): bool
    {
        if (! $user->can('adu.aduan.kemas_kini')) {
            return false;
        }

        return $aduan->id_pegawai === $user->id
            || $user->can('adu.penugasan.kemas_kini')
            || $this->penyeliaKepada($user, $aduan);
    }

    public function penyeliaKepada(User $user, Aduan $aduan): bool
    {
        if ($aduan->id_penyelia !== null && $aduan->id_penyelia === $user->id) {
            return true;
        }
        if ($aduan->id_pegawai === null || $aduan->id_pegawai === $user->id) {
            return false;
        }
        $pegawai = $aduan->pegawai;

        return $pegawai !== null && collect($this->directory->supervisorsOf($pegawai))->contains(fn (User $s) => $s->id === $user->id);
    }

    /** @return list<User> */
    public function penyelia(Aduan $aduan): array
    {
        $out = [];
        if ($aduan->id_penyelia !== null && ($u = User::query()->whereKey($aduan->id_penyelia)->where('is_active', true)->first())) {
            $out[$u->id] = $u;
        }
        if ($out === [] && $aduan->pegawai !== null) {
            foreach (array_slice($this->directory->supervisorsOf($aduan->pegawai), 0, 1) as $u) {
                $out[$u->id] = $u;
            }
        }

        return array_values($out);
    }

    /**
     * Escalation recipients for one path entry: pegawai | penyelia |
     * ketua_bahagian | peranan:<kod>.
     *
     * @param  list<string>  $targets
     * @return list<User>
     */
    public function penerima(Aduan $aduan, array $targets): array
    {
        $out = [];
        foreach ($targets as $target) {
            $users = match (true) {
                $target === 'pegawai' => $aduan->pegawai ? [$aduan->pegawai] : [],
                $target === 'penyelia' => $this->penyelia($aduan),
                $target === 'ketua_bahagian' => $this->directory->usersWithRole((string) $this->parameters->get('adu.peranan_ketua_bahagian', 'pelulus')),
                str_starts_with($target, 'peranan:') => $this->directory->usersWithRole(substr($target, 8)),
                default => [],
            };
            foreach ($users as $u) {
                $out[$u->id] = $u;
            }
        }

        return array_values($out);
    }
}
