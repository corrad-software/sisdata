<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Models\LogLogMasuk;
use App\Modules\Rbc\Models\Sesi;
use App\Modules\Rbc\Models\StatusAkaunLog;
use App\Modules\Rbc\Services\AccountStatusService;
use App\Modules\Rbc\Services\LoginService;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Rbc\Services\SessionManager;
use App\Modules\Rbc\Support\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** One user's access: status, roles, scopes, sessions (SRS-RBC-04/07/15/17). */
class UserAccessController extends Controller
{
    public function __construct(
        private AccessControl $access,
        private AccountStatusService $statuses,
        private RoleService $roles,
        private SessionManager $sessions,
    ) {}

    public function show(Request $request, User $user): JsonResponse
    {
        $roles = DB::table('pengguna_peranan')
            ->join('peranan', 'peranan.id', '=', 'pengguna_peranan.peranan_id')
            ->where('pengguna_peranan.user_id', $user->id)
            ->orderBy('peranan.nama')
            ->get(['peranan.id', 'peranan.kod', 'peranan.nama', 'peranan.status', 'pengguna_peranan.sumber', 'pengguna_peranan.created_at']);

        $scopes = DB::table('pengguna_skop')->where('user_id', $user->id)->orderBy('jenis_skop')->orderBy('nilai_skop')
            ->get(['jenis_skop', 'nilai_skop', 'sumber']);

        $currentSesi = $request->attributes->get('rbc.sesi');

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status_akaun' => $user->status_akaun,
            'jenis_pengesahan' => $user->jenis_pengesahan,
            'sso' => $user->oidc_sub !== null,
            'no_pekerja' => $user->no_pekerja,
            'bahagian' => $user->bahagian,
            'unit' => $user->unit,
            'jawatan' => $user->jawatan,
            'tarikh_mula_capaian' => $user->tarikh_mula_capaian?->toDateString(),
            'tarikh_tamat_capaian' => $user->tarikh_tamat_capaian?->toDateString(),
            'tarikh_log_masuk_akhir' => $user->tarikh_log_masuk_akhir?->toIso8601String(),
            'dikunci_hingga' => $user->dikunci_hingga?->isFuture() ? $user->dikunci_hingga->toIso8601String() : null,
            'wajib_tukar_kata_laluan' => (bool) $user->wajib_tukar_kata_laluan,
            'peranan' => $roles,
            'skop' => $scopes,
            'kebenaran' => $this->access->permissions($user),
            'skop_berkesan' => collect(array_keys(Presenters::scopeTypes()))
                ->mapWithKeys(fn ($type) => [$type => $this->access->scope($user, $type)]),
            'log_status' => StatusAkaunLog::query()->with('pelaku:id,name')->where('user_id', $user->id)->latest('id')->limit(50)->get()
                ->map(fn (StatusAkaunLog $log) => [
                    'status_lama' => $log->status_lama,
                    'status_baru' => $log->status_baru,
                    'sebab' => $log->sebab,
                    'pelaku' => $log->pelaku?->name,
                    'created_at' => $log->created_at->toIso8601String(),
                ]),
            'sesi_aktif' => Sesi::query()->aktif()->where('user_id', $user->id)->latest('tarikh_aktiviti_akhir')->get()
                ->map(fn (Sesi $s) => Presenters::session($s, $currentSesi instanceof Sesi ? $currentSesi->id : null)),
            'log_masuk_terkini' => LogLogMasuk::query()->where('user_id', $user->id)->latest('id')->limit(10)->get()
                ->map(fn (LogLogMasuk $log) => Presenters::loginLog($log)),
        ]]);
    }

    public function status(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(AccountStatus::ALL)],
            'sebab' => ['required', 'string', 'max:255'],
        ]);

        if ($user->is($request->user()) && $data['status'] !== AccountStatus::AKTIF) {
            throw ValidationException::withMessages(['status' => [__('rbc::rbc.status.diri_sendiri')]]);
        }

        $this->statuses->change($user, $data['status'], $data['sebab'], $request->user());

        return $this->show($request, $user->refresh());
    }

    public function roles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'peranan' => ['present', 'array'],
            'peranan.*' => ['integer', Rule::exists('peranan', 'id')],
        ]);

        $current = DB::table('pengguna_peranan')->where('user_id', $user->id)->pluck('peranan_id')->map(fn ($id) => (int) $id)->all();
        $this->roles->assertCanGrant($request->user(), roleIds: array_values(array_diff(array_map('intval', $data['peranan']), $current)));

        $this->roles->syncUserRoles($user, $data['peranan'], 'manual', $request->user());

        return $this->show($request, $user);
    }

    public function scopes(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'skop' => ['present', 'array'],
            'skop.*' => ['array'],
            'skop.*.*' => ['string', 'max:100'],
        ]);

        Presenters::assertScopeTypes($data['skop']);
        $this->roles->syncUserScopes($user, $data['skop']);

        return $this->show($request, $user);
    }

    /** SRS-RBC-16: access start/end dates (end date → automatic Tamat Tempoh). */
    public function accessPeriod(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'tarikh_mula_capaian' => ['nullable', 'date'],
            'tarikh_tamat_capaian' => ['nullable', 'date', 'after_or_equal:tarikh_mula_capaian'],
            'no_pekerja' => ['nullable', 'string', 'max:50'],
            'bahagian' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'jawatan' => ['nullable', 'string', 'max:150'],
        ]);

        $user->forceFill($data)->save();
        $this->statuses->applyAutomaticRules($user);

        return $this->show($request, $user->refresh());
    }

    public function unlock(Request $request, User $user, LoginService $login): JsonResponse
    {
        $login->unlock($user);

        return $this->show($request, $user->refresh());
    }

    public function terminateSessions(Request $request, User $user): JsonResponse
    {
        $count = $this->sessions->terminateAllFor($user, SessionManager::PAKSA, $request->user());

        return response()->json(['data' => ['ditamatkan' => $count], 'message' => __('rbc::rbc.sesi.ditamatkan', ['n' => $count])]);
    }
}
