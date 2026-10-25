<?php

namespace App\Modules\Lap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Lap\Models\DefinisiLaporan;
use App\Modules\Lap\Services\Enjin;
use App\Modules\Lap\Services\Katalog;
use App\Modules\Lap\Support\SetData;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Saved ad hoc report definitions (SRS-LAP-03) and sharing (SRS-LAP-09):
 * a shared definition is visible to the chosen audience but always runs with
 * the reader's own permissions and row scope.
 */
class DefinisiController extends Controller
{
    public function __construct(private Katalog $katalog, private Enjin $enjin, private AccessControl $access) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $roles = $this->access->roles($user);
        $rows = DefinisiLaporan::query()->with('pencipta:id,name')
            ->where(fn ($q) => $q->where('id_pencipta', $user->id)->orWhere('status_kongsi', 'semua')
                ->orWhere(fn ($w) => $w->where('status_kongsi', 'peranan')->where(function ($r) use ($roles) {
                    foreach ($roles ?: ['__tiada__'] as $role) {
                        $r->orWhereJsonContains('kongsi_peranan', $role);
                    }
                })))
            ->orderByDesc('updated_at')->limit(200)->get()
            ->filter(fn (DefinisiLaporan $d) => $this->katalog->canOpenDefinition($user, $d))
            ->map(fn (DefinisiLaporan $d) => $this->present($d, $user))->values();

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $d = $this->find($request, $id);

        return response()->json(['data' => $this->present($d, $request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validated($request, $user);
        $d = DefinisiLaporan::query()->create($data + ['id_pencipta' => $user->id]);

        return response()->json(['message' => __('lap::lap.disimpan'), 'data' => $this->present($d, $user)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $d = $this->find($request, $id);
        abort_unless($d->id_pencipta === $user->id, 403, __('lap::lap.ralat.bukan_pemilik'));
        $d->fill($this->validated($request, $user))->save();

        return response()->json(['message' => __('lap::lap.disimpan'), 'data' => $this->present($d, $user)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $d = $this->find($request, $id);
        abort_unless($d->id_pencipta === $request->user()?->id, 403, __('lap::lap.ralat.bukan_pemilik'));
        $d->delete();

        return response()->json(['message' => __('lap::lap.dipadam')]);
    }

    private function find(Request $request, string $id): DefinisiLaporan
    {
        /** @var User $user */
        $user = $request->user();
        $d = DefinisiLaporan::query()->with('pencipta:id,name')->findOrFail($id);
        // Not shared with this user = does not exist for them.
        abort_unless($this->katalog->canOpenDefinition($user, $d), 404);

        return $d;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, User $user): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:200'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'kod_laporan' => ['nullable', 'string', 'max:80'],
            'pertanyaan' => ['required', 'array'],
            'pertanyaan.set' => ['required', 'string', Rule::in(array_keys(SetData::all()))],
            'parameter_lalai' => ['nullable', 'array'],
            'status_kongsi' => ['required', Rule::in(DefinisiLaporan::KONGSI)],
            'kongsi_peranan' => ['nullable', 'array', 'required_if:status_kongsi,peranan'],
            'kongsi_peranan.*' => ['string', 'max:60'],
            'sumber' => ['nullable', Rule::in(['pembina', 'ai_prompt'])],
        ]);
        abort_unless($this->katalog->canUseSet($user, $data['pertanyaan']['set']), 403, __('lap::lap.ralat.tiada_akses'));
        $norm = $this->enjin->normalise($data['pertanyaan'], $user);
        unset($norm['medan_disembunyikan']);

        return [
            'nama' => $data['nama'], 'keterangan' => $data['keterangan'] ?? null, 'kod_laporan' => $data['kod_laporan'] ?? null,
            'set_data' => $norm['set'], 'pertanyaan' => $norm, 'parameter_lalai' => $data['parameter_lalai'] ?? null,
            'status_kongsi' => $data['status_kongsi'], 'kongsi_peranan' => $data['status_kongsi'] === 'peranan' ? array_values(array_unique($data['kongsi_peranan'] ?? [])) : null,
            'sumber' => $data['sumber'] ?? 'pembina',
        ];
    }

    /** @return array<string, mixed> */
    private function present(DefinisiLaporan $d, ?User $user): array
    {
        return [
            'id' => $d->id, 'nama' => $d->nama, 'keterangan' => $d->keterangan, 'kod_laporan' => $d->kod_laporan, 'set_data' => $d->set_data,
            'set_label' => SetData::get($d->set_data)['label'] ?? $d->set_data, 'pertanyaan' => $d->pertanyaan, 'parameter_lalai' => $d->parameter_lalai,
            'status_kongsi' => $d->status_kongsi, 'kongsi_peranan' => $d->kongsi_peranan ?? [], 'sumber' => $d->sumber,
            'pencipta' => $d->pencipta?->name, 'milik_saya' => $user !== null && $d->id_pencipta === $user->id, 'dikemas_kini' => $d->updated_at?->toIso8601String(),
        ];
    }
}
