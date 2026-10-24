<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Models\Peranan;
use Illuminate\Support\Facades\DB;

/**
 * The central RBAC evaluator (SRS-RBC-04/07/08, SRS-GEN-01). Laravel's Gate
 * delegates every "<modul>.<fungsi>.<tindakan>" ability here.
 *
 * Permissions = union over the user's ACTIVE roles. Nothing is granted
 * implicitly (SRS-RBC-09); a non-Aktif account is allowed nothing.
 *
 * Scope for a type (daerah, mukim, bahagian, klasifikasi…):
 *   - user values U (pengguna_skop), or "unrestricted" if the user has none;
 *   - role values R = union over active roles, or "unrestricted" if any
 *     active role has no restriction on that type;
 *   - result = U ∩ R when both restrict, else whichever restricts, else null.
 *
 * Results are memoised per request; any RBAC change calls flushAll().
 */
class RbcAccessControl implements AccessControl
{
    /**
     * Legacy permission names the interim UI (Sidebar) still reads. Sent to
     * the frontend only — never accepted by allows().
     */
    public const LEGACY_ALIASES = [
        'users.manage' => 'rbc.pengguna.lihat',
        'settings.manage' => 'rbc.pentadbiran.akses',
        'platform.view' => 'rbc.pentadbiran.akses',
    ];

    /** @var array<int, array{roles: list<string>, role_ids: list<int>, permissions: array<string, true>}> */
    private static array $memo = [];

    /** @var array<int, array<string, list<string>|null>> */
    private static array $scopeMemo = [];

    public static function flushAll(): void
    {
        self::$memo = [];
        self::$scopeMemo = [];
    }

    public function allows(User $user, string $permission): bool
    {
        if (! $this->isActive($user)) {
            return false;
        }

        return isset($this->context($user)['permissions'][$permission]);
    }

    public function scope(User $user, string $type): ?array
    {
        if (array_key_exists($type, self::$scopeMemo[$user->id] ?? [])) {
            return self::$scopeMemo[$user->id][$type];
        }

        if (! $this->isActive($user)) {
            return self::$scopeMemo[$user->id][$type] = [];
        }

        $userValues = DB::table('pengguna_skop')
            ->where('user_id', $user->id)
            ->where('jenis_skop', $type)
            ->pluck('nilai_skop')
            ->map(fn ($v) => (string) $v)
            ->unique()->values()->all();
        $userValues = $userValues === [] ? null : $userValues;

        $roleIds = $this->context($user)['role_ids'];
        $roleValues = null;

        if ($roleIds !== []) {
            $rows = DB::table('peranan_skop')
                ->whereIn('peranan_id', $roleIds)
                ->where('jenis_skop', $type)
                ->get(['peranan_id', 'nilai_skop']);

            $restricted = $rows->pluck('peranan_id')->unique();

            // Any active role without a restriction on this type → unrestricted.
            if ($restricted->count() === count($roleIds)) {
                $roleValues = $rows->pluck('nilai_skop')->map(fn ($v) => (string) $v)->unique()->values()->all();
            }
        }

        $result = match (true) {
            $userValues === null && $roleValues === null => null,
            $userValues === null => $roleValues,
            $roleValues === null => $userValues,
            default => array_values(array_intersect($userValues, $roleValues)),
        };

        return self::$scopeMemo[$user->id][$type] = $result;
    }

    public function roles(User $user): array
    {
        return $this->context($user)['roles'];
    }

    public function permissions(User $user): array
    {
        if (! $this->isActive($user)) {
            return [];
        }

        $permissions = array_keys($this->context($user)['permissions']);

        foreach (self::LEGACY_ALIASES as $alias => $real) {
            if (isset($this->context($user)['permissions'][$real])) {
                $permissions[] = $alias;
            }
        }

        sort($permissions);

        return array_values(array_unique($permissions));
    }

    private function isActive(User $user): bool
    {
        return ($user->status_akaun ?? AccountStatus::AKTIF) === AccountStatus::AKTIF;
    }

    /**
     * @return array{roles: list<string>, role_ids: list<int>, permissions: array<string, true>}
     */
    private function context(User $user): array
    {
        if (isset(self::$memo[$user->id])) {
            return self::$memo[$user->id];
        }

        $roles = DB::table('pengguna_peranan')
            ->join('peranan', 'peranan.id', '=', 'pengguna_peranan.peranan_id')
            ->where('pengguna_peranan.user_id', $user->id)
            ->where('peranan.status', Peranan::AKTIF)
            ->orderBy('peranan.kod')
            ->get(['peranan.id', 'peranan.kod']);

        $roleIds = $roles->pluck('id')->map(fn ($id) => (int) $id)->all();

        $permissions = $roleIds === [] ? [] : DB::table('peranan_kebenaran')
            ->whereIn('peranan_id', $roleIds)
            ->distinct()
            ->pluck('kebenaran')
            ->mapWithKeys(fn ($key) => [(string) $key => true])
            ->all();

        return self::$memo[$user->id] = [
            'roles' => array_values($roles->pluck('kod')->map(fn ($k) => (string) $k)->all()),
            'role_ids' => array_values($roleIds),
            'permissions' => array_map(fn () => true, $permissions),
        ];
    }
}
