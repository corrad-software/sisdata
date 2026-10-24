<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rbc\Models\PemetaanTuntutan;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\ClaimMapper;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Rbc\Support\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** SRS-RBC-02: admin-configured SSO claim → role / scope rules. */
class ClaimMappingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PemetaanTuntutan::query()->with('peranan:id,kod,nama')
            ->orderBy('keutamaan')->orderBy('id')->get()->map(fn (PemetaanTuntutan $r) => $this->present($r))]);
    }

    public function store(Request $request, RoleService $roles): JsonResponse
    {
        $data = $this->validated($request);
        if ($data['peranan_id'] ?? null) {
            $roles->assertCanGrant($request->user(), roleIds: [(int) $data['peranan_id']]);
        }

        $rule = PemetaanTuntutan::query()->create($data);

        return response()->json(['data' => $this->present($rule->load('peranan:id,kod,nama'))], 201);
    }

    public function update(Request $request, PemetaanTuntutan $pemetaan, RoleService $roles): JsonResponse
    {
        $data = $this->validated($request);
        if (($data['peranan_id'] ?? null) && (int) $data['peranan_id'] !== $pemetaan->peranan_id) {
            $roles->assertCanGrant($request->user(), roleIds: [(int) $data['peranan_id']]);
        }

        $pemetaan->fill($data)->save();

        return response()->json(['data' => $this->present($pemetaan->load('peranan:id,kod,nama'))]);
    }

    public function destroy(PemetaanTuntutan $pemetaan): Response
    {
        $pemetaan->delete();

        return response()->noContent();
    }

    /** Dry run: which roles/scopes would these claims get? */
    public function preview(Request $request, ClaimMapper $mapper): JsonResponse
    {
        $claims = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'max:150'],
            'bahagian' => ['nullable', 'string', 'max:150'],
            'unit' => ['nullable', 'string', 'max:150'],
            'jawatan' => ['nullable', 'string', 'max:150'],
        ]);

        $result = $mapper->evaluate($claims);

        return response()->json(['data' => [
            'peranan' => Peranan::query()->whereIn('id', $result['roles'])->get(['id', 'kod', 'nama']),
            'skop' => $result['scopes'] ?: (object) [],
            'peraturan' => $result['rules'],
        ]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'jenis_tuntutan' => ['required', Rule::in(PemetaanTuntutan::JENIS)],
            'nilai' => ['required', 'string', 'max:150'],
            'peranan_id' => ['nullable', 'integer', Rule::exists('peranan', 'id'), 'required_without:skop_jenis'],
            'skop_jenis' => ['nullable', Rule::in(array_keys(Presenters::scopeTypes())), 'required_with:skop_nilai'],
            'skop_nilai' => ['nullable', 'string', 'max:100', 'required_with:skop_jenis'],
            'aktif' => ['boolean'],
            'keutamaan' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
        $data['keutamaan'] ??= 100;

        return $data;
    }

    /** @return array<string, mixed> */
    private function present(PemetaanTuntutan $rule): array
    {
        return [
            'id' => $rule->id,
            'jenis_tuntutan' => $rule->jenis_tuntutan,
            'nilai' => $rule->nilai,
            'peranan' => $rule->peranan ? ['id' => $rule->peranan->id, 'kod' => $rule->peranan->kod, 'nama' => $rule->peranan->nama] : null,
            'skop_jenis' => $rule->skop_jenis,
            'skop_nilai' => $rule->skop_nilai,
            'aktif' => $rule->aktif,
            'keutamaan' => $rule->keutamaan,
            'keterangan' => $rule->keterangan,
        ];
    }
}
