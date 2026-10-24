<?php

namespace App\Modules\Hkm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hkm\Models\CadanganPerubahan;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Services\ProposalService;
use App\Modules\Hkm\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-HKM-18 — inbox of external change proposals (drafts). */
class CadanganController extends Controller
{
    public function __construct(private ProposalService $proposals) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(array_keys(Kod::STATUS_CADANGAN))]]);

        $page = CadanganPerubahan::query()
            // Matched proposals follow the title's scope; unmatched ones are visible to every reviewer.
            ->where(fn ($q) => $q->whereNull('hak_milik_id')->orWhereHas('hakMilik'))
            ->with(['hakMilik', 'penyemak'])
            ->when($request->query('status', 'draf'), fn ($q, $s) => $s === 'semua' ? $q : $q->where('status', $s))
            ->when($request->query('sumber'), fn ($q, $s) => $q->where('sumber', $s))
            ->orderByDesc('created_at')->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (CadanganPerubahan $c) => $this->row($c))->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function show(CadanganPerubahan $cadangan): JsonResponse
    {
        $this->assertScope($cadangan);

        return response()->json(['data' => $this->row($cadangan->load(['hakMilik', 'penyemak']))]);
    }

    public function store(Request $request): JsonResponse
    {
        $proposal = $this->proposals->receive($request->all(), $request->user());

        return response()->json(['data' => $this->row($proposal), 'message' => __('hkm::hkm.mesej.cadangan_diterima')], $proposal->wasRecentlyCreated ? 201 : 200);
    }

    public function confirm(Request $request, CadanganPerubahan $cadangan): JsonResponse
    {
        $this->assertScope($cadangan);
        abort_unless($request->user()->can('hkm.hak_milik.kemas_kini'), 403);
        $change = $this->proposals->confirm($cadangan, $request->all(), $request->user());

        return response()->json(['data' => $this->row($cadangan->refresh()->load(['hakMilik', 'penyemak'])) + ['id_perubahan' => $change->id], 'message' => __('hkm::hkm.mesej.cadangan_disahkan')]);
    }

    public function reject(Request $request, CadanganPerubahan $cadangan): JsonResponse
    {
        $this->assertScope($cadangan);
        $valid = $request->validate(['catatan' => ['required', 'string', 'min:5', 'max:2000']], [], ['catatan' => 'catatan']);
        $this->proposals->reject($cadangan, $valid['catatan'], $request->user());

        return response()->json(['data' => $this->row($cadangan->refresh()), 'message' => __('hkm::hkm.mesej.cadangan_ditolak')]);
    }

    private function assertScope(CadanganPerubahan $cadangan): void
    {
        abort_unless($cadangan->hak_milik_id === null || HakMilik::query()->whereKey($cadangan->hak_milik_id)->exists(), 404);
    }

    /** @return array<string, mixed> */
    private function row(CadanganPerubahan $c): array
    {
        return [
            'id' => $c->id,
            'hak_milik_id' => $c->hak_milik_id,
            'no_hak_milik' => $c->no_hak_milik,
            'kod_mukim' => $c->kod_mukim,
            'sumber' => $c->sumber,
            'sumber_nama' => Kod::SUMBER_CADANGAN[$c->sumber] ?? $c->sumber,
            'rujukan_luaran' => $c->rujukan_luaran,
            'jenis_perubahan' => $c->jenis_perubahan,
            'jenis_perubahan_nama' => Kod::JENIS_PERUBAHAN[$c->jenis_perubahan] ?? $c->jenis_perubahan,
            'data_cadangan' => $c->data_cadangan,
            'keterangan' => $c->keterangan,
            'status' => $c->status,
            'status_nama' => Kod::STATUS_CADANGAN[$c->status] ?? $c->status,
            'penyemak' => $c->relationLoaded('penyemak') ? $c->penyemak?->name : null,
            'tarikh_semakan' => $c->tarikh_semakan?->toIso8601String(),
            'catatan_semakan' => $c->catatan_semakan,
            'id_perubahan' => $c->id_perubahan,
            'diterima' => $c->created_at->toIso8601String(),
        ];
    }
}
