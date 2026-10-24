<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Services\AccountStatusService;
use App\Modules\Rbc\Services\PasswordPolicy;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Rbc\Support\BaseRoles;
use App\Notifications\UserCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * User accounts (MOD-RBC). Routes authorise with `can:rbc.pengguna.*`;
 * role/scope/status detail lives under /api/v1/rbc/pengguna/{user}.
 */
class UserController extends Controller
{
    public function __construct(
        private RoleService $roles,
        private AccountStatusService $statuses,
        private PasswordPolicy $passwords,
    ) {}

    public function index(Request $request)
    {
        $search = mb_strtolower($request->string('search')->toString());

        $users = User::query()
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->whereRaw('lower(name) like ?', ["%{$search}%"])
                ->orWhereRaw('lower(email) like ?', ["%{$search}%"])))
            ->when($request->filled('status'), fn ($q) => $q->where('status_akaun', $request->string('status')))
            ->when($request->filled('bahagian'), fn ($q) => $q->where('bahagian', $request->string('bahagian')))
            ->when($request->filled('peranan'), fn ($q) => $q->whereIn('id', DB::table('pengguna_peranan')
                ->join('peranan', 'peranan.id', '=', 'pengguna_peranan.peranan_id')
                ->where('peranan.kod', $request->string('peranan'))
                ->select('pengguna_peranan.user_id')))
            ->latest()
            ->paginate(10);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();
        $roleIds = $this->requestedRoleIds($validated);
        $this->roles->assertCanGrant($request->user(), roleIds: $roleIds);

        $user = DB::transaction(function () use ($validated, $roleIds, $request) {
            $sso = ($validated['jenis_pengesahan'] ?? 'dalaman') === 'sso';

            $user = User::create([
                ...collect($validated)->only(['name', 'email', 'no_pekerja', 'bahagian', 'unit', 'jawatan', 'tarikh_mula_capaian', 'tarikh_tamat_capaian'])->all(),
                'jenis_pengesahan' => $sso ? 'sso' : 'dalaman',
                'role' => $validated['role'] ?? 'user',
                'status_akaun' => $validated['status_akaun'] ?? (($validated['is_active'] ?? true) ? AccountStatus::AKTIF : AccountStatus::NYAHAKTIF),
            ]);

            if (! $sso) {
                // SRS-RBC-12: an admin-set password must be changed at first login.
                $this->passwords->setPassword($user, $validated['password'], forceChange: true);
            }

            $this->roles->syncUserRoles($user, $roleIds, 'manual', $request->user());

            return $user;
        });

        $request->user()->notify(new UserCreated($user));

        return (new UserResource($user->refresh()))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $user->fill(collect($validated)->only(['name', 'email', 'no_pekerja', 'bahagian', 'unit', 'jawatan', 'tarikh_mula_capaian', 'tarikh_tamat_capaian'])->all());
        $user->save();

        if (filled($validated['password'] ?? null)) {
            if ($user->jenis_pengesahan === 'sso') {
                throw ValidationException::withMessages(['password' => [__('rbc::rbc.kata_laluan.akaun_sso')]]);
            }
            $this->passwords->setPassword($user, $validated['password'], forceChange: true);
        }

        if (array_key_exists('peranan', $validated) || array_key_exists('role', $validated)) {
            $roleIds = $this->requestedRoleIds($validated, $user);
            $current = DB::table('pengguna_peranan')->where('user_id', $user->id)->pluck('peranan_id')->map(fn ($id) => (int) $id)->all();
            $this->roles->assertCanGrant($request->user(), roleIds: array_values(array_diff($roleIds, $current)));
            $this->roles->syncUserRoles($user, $roleIds, 'manual', $request->user());
            if (isset($validated['role'])) {
                $user->forceFill(['role' => $validated['role']])->saveQuietly();
            }
        }

        $status = $validated['status_akaun'] ?? (array_key_exists('is_active', $validated)
            ? ($validated['is_active'] ? AccountStatus::AKTIF : AccountStatus::NYAHAKTIF)
            : null);

        if ($status !== null && $status !== $user->status_akaun) {
            if ($user->is($request->user()) && $status !== AccountStatus::AKTIF) {
                throw ValidationException::withMessages(['status_akaun' => [__('rbc::rbc.status.diri_sendiri')]]);
            }
            $this->statuses->change($user, $status, $validated['sebab'] ?? __('rbc::rbc.status.sebab_kemas_kini'), $request->user());
        }

        return new UserResource($user->refresh());
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => [__('rbc::rbc.pengguna.padam_diri')]]);
        }

        $user->delete();

        return response()->noContent();
    }

    /**
     * `peranan` (role ids) wins; the deprecated `role` = admin/user toggles
     * Pentadbir Sistem on top of the user's current manual roles.
     *
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function requestedRoleIds(array $validated, ?User $user = null): array
    {
        if (isset($validated['peranan'])) {
            return array_values(array_map('intval', $validated['peranan']));
        }

        $ids = $user ? DB::table('pengguna_peranan')->where('user_id', $user->id)->where('sumber', 'manual')->pluck('peranan_id')->map(fn ($id) => (int) $id)->all() : [];
        $adminId = (int) DB::table('peranan')->where('kod', BaseRoles::PENTADBIR_SISTEM)->value('id');

        if (($validated['role'] ?? null) === 'admin' && $adminId) {
            $ids[] = $adminId;
        } elseif (($validated['role'] ?? null) === 'user') {
            $ids = array_values(array_diff($ids, [$adminId]));
        }

        return array_values(array_unique($ids));
    }
}
