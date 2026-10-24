<?php

namespace App\Modules\Adu\Services;

use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\PeraturanPenugasan;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Contracts\ApproverDirectory;

/**
 * SRS-ADU-11: automatic assignment by category + district + role. The most
 * specific active rule wins; a role rule picks the in-scope holder with the
 * fewest open complaints. No match → stays "baharu" for Unit Aduan to assign
 * manually.
 */
class Penugasan
{
    public function __construct(private ApproverDirectory $directory, private AccessControl $access) {}

    public function cadang(Aduan $aduan): ?User
    {
        $rules = PeraturanPenugasan::query()->where('aktif', true)
            ->where(fn ($q) => $q->whereNull('kategori')->orWhere('kategori', $aduan->kategori))
            ->where(fn ($q) => $q->whereNull('kod_daerah')->orWhere('kod_daerah', $aduan->kod_daerah))
            ->get()
            ->sortBy(fn (PeraturanPenugasan $r) => [($r->kategori === null ? 1 : 0) + ($r->kod_daerah === null ? 2 : 0), $r->keutamaan, $r->id]);

        foreach ($rules as $rule) {
            $candidates = [];
            if ($rule->id_pegawai !== null && ($u = User::query()->whereKey($rule->id_pegawai)->where('is_active', true)->first())) {
                $candidates[] = $u;
            }
            if ($rule->kod_peranan !== null) {
                $candidates = [...$candidates, ...$this->directory->usersWithRole($rule->kod_peranan)];
            }
            $eligible = array_values(array_filter($candidates, fn (User $u) => $this->layak($u, $aduan)));
            if ($eligible !== []) {
                return $this->palingRingan($eligible);
            }
        }

        return null;
    }

    /** May act on complaints and the complaint is inside the user's district/mukim scope. */
    public function layak(User $user, Aduan $aduan): bool
    {
        if (! $user->is_active || ! $this->access->allows($user, 'adu.aduan.kemas_kini')) {
            return false;
        }
        foreach (['daerah' => $aduan->kod_daerah, 'mukim' => $aduan->kod_mukim] as $type => $value) {
            $scope = $this->access->scope($user, $type);
            if ($scope !== null && ($value === null || ! in_array($value, $scope, true))) {
                return false;
            }
        }

        return true;
    }

    /** @param non-empty-list<User> $users */
    private function palingRingan(array $users): User
    {
        $load = Aduan::query()->withoutGlobalScope('access')->terbuka()
            ->whereIn('id_pegawai', array_map(fn (User $u) => $u->id, $users))
            ->selectRaw('id_pegawai, count(*) AS n')->groupBy('id_pegawai')->pluck('n', 'id_pegawai');

        usort($users, fn (User $a, User $b) => [(int) ($load[$a->id] ?? 0), $a->id] <=> [(int) ($load[$b->id] ?? 0), $b->id]);

        return $users[0];
    }

    public function penyeliaUntuk(User $pegawai): ?User
    {
        return $this->directory->supervisorsOf($pegawai)[0] ?? null;
    }
}
