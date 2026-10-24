<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Support\BaseRoles;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Query-based answers to "which users hold X" from MOD-RBC's own data
 * (pengguna_peranan, peranan_kebenaran, *_skop, users.jawatan), for the
 * contracts MOD-NTF (RecipientDirectory) and MOD-WFE (ApproverDirectory)
 * defined. Only active accounts (status_akaun = aktif) and active roles count.
 *
 * RBC holds no supervisor hierarchy: supervisorsOf() returns the holders of
 * WFE's fallback supervisor role (parameter wfe.peranan_penyelia_lalai), as
 * WFE's own default does. MOD-NTF's SupervisorResolver is left to NTF's
 * default, which resolves through this RecipientDirectory.
 */
class UserDirectory implements ApproverDirectory, RecipientDirectory
{
    /**
     * Legacy users.role values still used as parameter defaults
     * (wfe.peranan_penyelia_lalai, ntf.peranan_penyelia = "admin").
     */
    public const LEGACY_ROLE_CODES = ['admin' => BaseRoles::PENTADBIR_SISTEM];

    private const SCOPE_TARGETS = [
        self::BAHAGIAN => AccessControl::SCOPE_BAHAGIAN,
        self::DAERAH => AccessControl::SCOPE_DAERAH,
    ];

    public function __construct(private AccessControl $access) {}

    public function resolve(string $target, string $value): array
    {
        return match ($target) {
            self::PENGGUNA => ctype_digit($value) ? $this->active()->whereKey((int) $value)->get()->all() : [],
            self::PERANAN => $this->usersWithRole($value),
            self::KEBENARAN => $this->active()->whereIn('id', DB::table('pengguna_peranan')
                ->join('peranan', 'peranan.id', '=', 'pengguna_peranan.peranan_id')
                ->join('peranan_kebenaran', 'peranan_kebenaran.peranan_id', '=', 'peranan.id')
                ->where('peranan.status', Peranan::AKTIF)
                ->where('peranan_kebenaran.kebenaran', $value)
                ->select('pengguna_peranan.user_id'))->get()->all(),
            self::BAHAGIAN, self::DAERAH => $this->usersInScope(self::SCOPE_TARGETS[$target], $value),
            default => [],
        };
    }

    public function options(string $target): array
    {
        if ($target === self::PERANAN) {
            return Peranan::query()->where('status', Peranan::AKTIF)->orderBy('nama')->get(['kod', 'nama'])
                ->map(fn (Peranan $role) => ['value' => $role->kod, 'label' => $role->nama])->values()->all();
        }

        $type = self::SCOPE_TARGETS[$target] ?? null;
        if ($type === null) {
            return [];
        }

        return DB::table('pengguna_skop')->where('jenis_skop', $type)->select('nilai_skop')
            ->union(DB::table('peranan_skop')->where('jenis_skop', $type)->select('nilai_skop'))
            ->orderBy('nilai_skop')->pluck('nilai_skop')
            ->map(fn ($v) => ['value' => (string) $v, 'label' => (string) $v])->values()->all();
    }

    public function usersWithRole(string $roleCode): array
    {
        $roleCode = self::LEGACY_ROLE_CODES[$roleCode] ?? $roleCode;

        return $this->active()->whereIn('id', DB::table('pengguna_peranan')
            ->join('peranan', 'peranan.id', '=', 'pengguna_peranan.peranan_id')
            ->where('peranan.status', Peranan::AKTIF)
            ->where('peranan.kod', $roleCode)
            ->select('pengguna_peranan.user_id'))->get()->all();
    }

    public function usersWithPosition(string $positionCode): array
    {
        return $this->active()->where('jawatan', $positionCode)->get()->all();
    }

    public function positionOf(User $user): ?string
    {
        return $user->jawatan ?: null;
    }

    public function supervisorsOf(User $user): array
    {
        $role = (string) app(Parameters::class)->get('wfe.peranan_penyelia_lalai', 'admin');

        return array_values(array_filter($this->usersWithRole($role), fn (User $u) => $u->id !== $user->id));
    }

    /**
     * Users whose effective scope (AccessControl::scope — user ∩ role
     * restrictions) explicitly includes $value. Unrestricted users are not
     * members of every bahagian/daerah. The query narrows to users that carry
     * the value on themselves or on one of their roles; the contract decides.
     *
     * @return list<User>
     */
    private function usersInScope(string $type, string $value): array
    {
        $candidates = $this->active()->where(fn (Builder $q) => $q
            ->whereIn('id', DB::table('pengguna_skop')->where('jenis_skop', $type)->where('nilai_skop', $value)->select('user_id'))
            ->orWhereIn('id', DB::table('pengguna_peranan')
                ->join('peranan_skop', 'peranan_skop.peranan_id', '=', 'pengguna_peranan.peranan_id')
                ->where('peranan_skop.jenis_skop', $type)
                ->where('peranan_skop.nilai_skop', $value)
                ->select('pengguna_peranan.user_id')))
            ->get();

        return array_values($candidates->filter(
            fn (User $user) => in_array($value, $this->access->scope($user, $type) ?? [], true),
        )->all());
    }

    /** @return Builder<User> */
    private function active(): Builder
    {
        return User::query()->where('status_akaun', AccountStatus::AKTIF)->orderBy('id');
    }
}
