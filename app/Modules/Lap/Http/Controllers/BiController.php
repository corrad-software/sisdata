<?php

namespace App\Modules\Lap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Lap\Models\PapanBi;
use App\Modules\Lap\Services\Metabase;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Approved Metabase dashboards embedded in SISDATA (SRS-LAP-01). The signed
 * embed URL carries the reader's scope as locked parameters; extra
 * permissions per dashboard keep BI from bypassing module authorisation
 * (SRS-RBC-08).
 */
class BiController extends Controller
{
    public function __construct(private Metabase $metabase, private AccessControl $access) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $admin = $this->access->allows($user, 'lap.bi.kemas_kini');
        $rows = PapanBi::query()->orderBy('nama')->get()
            ->filter(fn (PapanBi $p) => $admin || ($p->aktif && $this->may($user, $p)))
            ->map(fn (PapanBi $p) => $this->present($p, $user))->values();

        return response()->json(['data' => $rows, 'status' => $this->metabase->status()]);
    }

    public function embed(Request $request, string $kod, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $p = PapanBi::query()->where('kod', $kod)->where('aktif', true)->firstOrFail();
        abort_unless($this->may($user, $p), 403, __('lap::lap.ralat.tiada_akses'));
        $e = $this->metabase->embed($user, $p);
        $audit->record('lihat', 'lap', 'lap_papan_bi', $p->id, meta: ['papan' => $p->kod, 'parameter_kunci' => $e['parameter']]);

        return response()->json(['data' => $e + ['nama' => $p->nama]]);
    }

    public function store(Request $request): JsonResponse
    {
        $p = PapanBi::query()->create($this->validated($request));

        return response()->json(['message' => __('lap::lap.disimpan'), 'data' => $this->present($p, $request->user())], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $p = PapanBi::query()->findOrFail($id);
        $p->fill($this->validated($request, $p->id))->save();

        return response()->json(['message' => __('lap::lap.disimpan'), 'data' => $this->present($p, $request->user())]);
    }

    public function destroy(int $id): JsonResponse
    {
        PapanBi::query()->findOrFail($id)->delete();

        return response()->json(['message' => __('lap::lap.dipadam')]);
    }

    private function may(User $user, PapanBi $p): bool
    {
        return ! $p->kebenaran || $this->access->allows($user, $p->kebenaran);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'kod' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('lap_papan_bi', 'kod')->ignore($id)],
            'nama' => ['required', 'string', 'max:200'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'jenis' => ['required', Rule::in(['dashboard', 'question'])],
            'id_metabase' => ['required', 'integer', 'min:1'],
            'kebenaran' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+\.[a-z0-9_]+\.[a-z_]+$/'],
            'parameter_kunci' => ['nullable', 'array'],
            'parameter_kunci.*' => [Rule::in(['daerah', 'mukim', 'bahagian'])],
            'aktif' => ['sometimes', 'boolean'],
        ]);
        foreach (array_keys((array) ($data['parameter_kunci'] ?? [])) as $name) {
            abort_unless(is_string($name) && preg_match('/^[a-z0-9_]+$/', $name), 422, __('lap::lap.bi.parameter_tidak_sah'));
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function present(PapanBi $p, ?User $user): array
    {
        return [
            'id' => $p->id, 'kod' => $p->kod, 'nama' => $p->nama, 'keterangan' => $p->keterangan, 'jenis' => $p->jenis, 'id_metabase' => $p->id_metabase,
            'kebenaran' => $p->kebenaran, 'parameter_kunci' => $p->parameter_kunci ?? [], 'aktif' => $p->aktif, 'boleh_buka' => $user ? $this->may($user, $p) : false,
        ];
    }
}
