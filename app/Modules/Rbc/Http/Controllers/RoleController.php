<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\PermissionCatalogue;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Rbc\Support\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** SRS-RBC-05/06/09: dynamic roles, managed without code changes. */
class RoleController extends Controller
{
    public function __construct(private RoleService $roles, private PermissionCatalogue $catalogue) {}

    public function catalogue(): JsonResponse
    {
        $modules = [];
        foreach ($this->catalogue->all() as $code => $module) {
            $modules[] = [
                'modul' => $code,
                'label' => $module['label'],
                'kebenaran' => collect($module['permissions'])->map(fn ($label, $key) => ['kunci' => $key, 'label' => $label])->values(),
            ];
        }

        return response()->json(['data' => [
            'modul' => $modules,
            'tindakan' => AccessControl::ACTIONS,
            'jenis_skop' => Presenters::scopeTypes(),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $roles = Peranan::query()
            ->withCount(['pengguna', 'kebenaran'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByRaw('kategori_rfp is null')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $roles->map(fn (Peranan $r) => Presenters::role($r))]);
    }

    public function show(Peranan $peranan): JsonResponse
    {
        return response()->json(['data' => Presenters::role($peranan->loadCount(['pengguna', 'kebenaran']), detail: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kod' => ['nullable', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('peranan', 'kod')],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);

        $role = $this->roles->create($data);

        return response()->json(['data' => Presenters::role($role->loadCount(['pengguna', 'kebenaran']), detail: true)], 201);
    }

    public function update(Request $request, Peranan $peranan): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);

        $peranan->fill($data)->save();

        return $this->show($peranan);
    }

    public function permissions(Request $request, Peranan $peranan): JsonResponse
    {
        $data = $request->validate([
            'kebenaran' => ['present', 'array'],
            'kebenaran.*' => ['string', 'max:150'],
        ]);

        $this->roles->assertCanGrant($request->user(), array_values(array_diff($data['kebenaran'], $peranan->kebenaranKeys())));
        $this->roles->setPermissions($peranan, $data['kebenaran']);

        return $this->show($peranan);
    }

    public function scopes(Request $request, Peranan $peranan): JsonResponse
    {
        $data = $request->validate([
            'skop' => ['present', 'array'],
            'skop.*' => ['array'],
            'skop.*.*' => ['string', 'max:100'],
        ]);

        Presenters::assertScopeTypes($data['skop']);
        $this->roles->setScopes($peranan, $data['skop']);

        return $this->show($peranan);
    }

    public function status(Request $request, Peranan $peranan): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in([Peranan::AKTIF, Peranan::TIDAK_AKTIF])]]);

        $peranan->forceFill(['status' => $data['status']])->save();

        return $this->show($peranan);
    }

    public function clone(Request $request, Peranan $peranan): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kod' => ['nullable', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('peranan', 'kod')],
        ]);

        $copy = $this->roles->cloneRole($peranan, $data['nama'], $data['kod'] ?? null);

        return response()->json(['data' => Presenters::role($copy->loadCount(['pengguna', 'kebenaran']), detail: true)], 201);
    }

    public function destroy(Peranan $peranan): Response
    {
        if (! $peranan->boleh_padam) {
            throw ValidationException::withMessages(['peranan' => [__('rbc::rbc.peranan.tidak_boleh_padam')]]);
        }

        if ($peranan->pengguna()->exists()) {
            throw ValidationException::withMessages(['peranan' => [__('rbc::rbc.peranan.masih_digunakan')]]);
        }

        $peranan->delete();

        return response()->noContent();
    }
}
