<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Events\UserRolesChanged;
use App\Modules\Rbc\Models\PenggunaSkop;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Models\PerananKebenaran;
use App\Modules\Rbc\Models\PerananSkop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Role management without code changes (SRS-RBC-06): create, edit,
 * deactivate, clone, set permissions and scopes; assign roles and scopes to
 * users. Changes take effect on the next permission check (acceptance #7).
 */
class RoleService
{
    public function __construct(private PermissionCatalogue $catalogue) {}

    /**
     * SRS-RBC-09: a new role starts with no permissions at all.
     *
     * @param  array{kod?: string|null, nama: string, keterangan?: string|null}  $data
     */
    public function create(array $data): Peranan
    {
        return Peranan::query()->create([
            'kod' => $data['kod'] ?? $this->uniqueCode($data['nama']),
            'nama' => $data['nama'],
            'keterangan' => $data['keterangan'] ?? null,
            'kategori_rfp' => null,
            'status' => Peranan::AKTIF,
            'boleh_padam' => true,
        ]);
    }

    public function cloneRole(Peranan $source, string $nama, ?string $kod = null): Peranan
    {
        return DB::transaction(function () use ($source, $nama, $kod) {
            $copy = $this->create(['nama' => $nama, 'kod' => $kod, 'keterangan' => $source->keterangan]);
            $this->setPermissions($copy, $source->kebenaranKeys());
            $this->setScopes($copy, $source->skop()->get()->groupBy('jenis_skop')
                ->map(fn ($rows) => $rows->pluck('nilai_skop')->all())->all());

            return $copy;
        });
    }

    /**
     * @param  list<string>  $keys
     *
     * @throws ValidationException
     */
    public function setPermissions(Peranan $role, array $keys): void
    {
        $keys = array_values(array_unique($keys));
        $unknown = array_values(array_filter($keys, fn ($k) => ! $this->catalogue->has($k)));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'kebenaran' => [__('rbc::rbc.peranan.kebenaran_tidak_wujud', ['senarai' => implode(', ', $unknown)])],
            ]);
        }

        DB::transaction(function () use ($role, $keys) {
            $role->kebenaran()->whereNotIn('kebenaran', $keys)->delete();
            $existing = $role->kebenaran()->pluck('kebenaran')->all();

            foreach (array_diff($keys, $existing) as $key) {
                PerananKebenaran::query()->create(['peranan_id' => $role->id, 'kebenaran' => $key, ...PerananKebenaran::split($key)]);
            }
        });

        RbcAccessControl::flushAll();
    }

    /**
     * @param  array<string, list<string>>  $scopes  type => values (empty list = unrestricted)
     */
    public function setScopes(Peranan $role, array $scopes): void
    {
        DB::transaction(function () use ($role, $scopes) {
            $role->skop()->delete();
            foreach ($scopes as $type => $values) {
                foreach (array_unique(array_map('strval', $values)) as $value) {
                    PerananSkop::query()->create(['peranan_id' => $role->id, 'jenis_skop' => $type, 'nilai_skop' => $value]);
                }
            }
        });

        RbcAccessControl::flushAll();
    }

    /**
     * Replace a user's roles from one source (manual by default). Roles from
     * the other source are left alone.
     *
     * @param  list<int>  $roleIds
     */
    public function syncUserRoles(User $user, array $roleIds, string $source = 'manual', ?User $actor = null): void
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $current = DB::table('pengguna_peranan')->where('user_id', $user->id)->get(['peranan_id', 'sumber']);

        $currentFromSource = $current->where('sumber', $source)->pluck('peranan_id')->map(fn ($id) => (int) $id)->all();
        $currentAll = $current->pluck('peranan_id')->map(fn ($id) => (int) $id)->all();

        $toRemove = array_values(array_diff($currentFromSource, $roleIds));
        $toAdd = array_values(array_diff($roleIds, $currentAll));

        if ($toRemove === [] && $toAdd === []) {
            return;
        }

        DB::transaction(function () use ($user, $toRemove, $toAdd, $source, $actor) {
            if ($toRemove !== []) {
                DB::table('pengguna_peranan')->where('user_id', $user->id)->whereIn('peranan_id', $toRemove)->delete();
            }
            foreach ($toAdd as $roleId) {
                DB::table('pengguna_peranan')->insert([
                    'user_id' => $user->id,
                    'peranan_id' => $roleId,
                    'sumber' => $source,
                    'diberi_oleh' => $actor?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        RbcAccessControl::flushAll();

        $codes = Peranan::query()->whereIn('id', [...$toAdd, ...$toRemove])->pluck('kod', 'id');
        UserRolesChanged::dispatch(
            $user,
            array_map(fn ($id) => (string) $codes[$id], $toAdd),
            array_map(fn ($id) => (string) $codes[$id], $toRemove),
            $source,
            $actor,
        );
    }

    public function assignRoleByCode(User $user, string $code, string $source = 'manual', ?User $actor = null): void
    {
        $role = Peranan::query()->where('kod', $code)->first();
        if ($role && ! DB::table('pengguna_peranan')->where('user_id', $user->id)->where('peranan_id', $role->id)->exists()) {
            $ids = DB::table('pengguna_peranan')->where('user_id', $user->id)->where('sumber', $source)->pluck('peranan_id')->all();
            $this->syncUserRoles($user, [...array_map('intval', $ids), $role->id], $source, $actor);
        }
    }

    public function removeRoleByCode(User $user, string $code, ?User $actor = null): void
    {
        $role = Peranan::query()->where('kod', $code)->first();
        if (! $role) {
            return;
        }

        $deleted = DB::table('pengguna_peranan')->where('user_id', $user->id)->where('peranan_id', $role->id)->delete();
        if ($deleted) {
            RbcAccessControl::flushAll();
            UserRolesChanged::dispatch($user, [], [$code], 'manual', $actor);
        }
    }

    /**
     * @param  array<string, list<string>>  $scopes  type => values
     */
    public function syncUserScopes(User $user, array $scopes, string $source = 'manual'): void
    {
        DB::transaction(function () use ($user, $scopes, $source) {
            PenggunaSkop::query()->where('user_id', $user->id)->where('sumber', $source)->delete();
            foreach ($scopes as $type => $values) {
                foreach (array_unique(array_map('strval', $values)) as $value) {
                    PenggunaSkop::query()->firstOrCreate(
                        ['user_id' => $user->id, 'jenis_skop' => $type, 'nilai_skop' => $value],
                        ['sumber' => $source],
                    );
                }
            }
        });

        RbcAccessControl::flushAll();
    }

    /**
     * No privilege escalation (OWASP ASVS V4): an administrator may only
     * grant permissions — directly or through a role — that they hold.
     *
     * @param  list<string>  $permissionKeys
     * @param  list<int>  $roleIds
     *
     * @throws AuthorizationException
     */
    public function assertCanGrant(User $actor, array $permissionKeys = [], array $roleIds = []): void
    {
        if ($roleIds !== []) {
            $permissionKeys = [
                ...$permissionKeys,
                ...DB::table('peranan_kebenaran')->whereIn('peranan_id', $roleIds)->pluck('kebenaran')->all(),
            ];
        }

        $held = app(AccessControl::class)->permissions($actor);
        // Unregistered keys are rejected separately (422) by setPermissions().
        $missing = array_values(array_filter(
            array_diff(array_unique($permissionKeys), $held),
            fn (string $key) => $this->catalogue->has($key),
        ));

        if ($missing !== []) {
            throw new AuthorizationException(__('rbc::rbc.peranan.eskalasi', ['senarai' => implode(', ', array_slice($missing, 0, 5))]));
        }
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(50, '')->toString() ?: 'peranan';
        $code = $base;
        $i = 2;
        while (Peranan::query()->where('kod', $code)->exists()) {
            $code = $base.'_'.$i++;
        }

        return $code;
    }
}
