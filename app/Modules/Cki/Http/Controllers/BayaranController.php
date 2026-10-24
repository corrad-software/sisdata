<?php

namespace App\Modules\Cki\Http\Controllers;

use App\Modules\Cki\Http\Present;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Models\Transaksi;
use App\Modules\Cki\Services\BayaranService;
use App\Modules\Cki\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-CKI-11..15: payment requests, execution after approval, method-specific recording, receipts and completion. */
class BayaranController
{
    public function __construct(private BayaranService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = Transaksi::query()->with(['kelulusan', 'bil'])
            ->when($request->filled('status'), fn ($w) => $w->where('status_transaksi', $request->query('status')))
            ->when($request->filled('kod_daerah'), fn ($w) => $w->where('kod_daerah', $request->query('kod_daerah')))
            ->orderByDesc('created_at');
        $page = $q->paginate(min(100, max(5, (int) $request->query('per_page', 20))));

        return response()->json([
            'data' => collect($page->items())->map(fn (Transaksi $t) => Present::transaksi($t) + ['no_bil' => $t->bil?->no_bil, 'jenis_cukai_nama' => Kod::JENIS_CUKAI[$t->bil->jenis ?? ''] ?? null])->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function store(Request $request, Cukai $cukai): JsonResponse
    {
        $data = $request->validate([
            'kaedah_bayaran' => ['required', Rule::in(array_keys(Kod::KAEDAH_BAYARAN))],
            'amaun' => 'nullable|numeric|min:0.01',
            'catatan' => 'nullable|string|max:2000',
        ]);
        $t = $this->service->mohon($cukai, $data + ['amaun' => isset($data['amaun']) ? (string) $data['amaun'] : null], $request->user());

        return response()->json(['data' => Present::transaksi($t->refresh()), 'message' => __('cki::cki.mesej.permohonan')], 201);
    }

    public function show(Transaksi $transaksi): JsonResponse
    {
        return response()->json(['data' => Present::transaksi($transaksi) + [
            'resit' => Resit::query()->where('id_transaksi', $transaksi->id)->get()->map(fn (Resit $r) => Present::resit($r))->all(),
        ]]);
    }

    public function execute(Request $request, Transaksi $transaksi): JsonResponse
    {
        $t = $this->service->laksana($transaksi, $request->user());

        return response()->json(['data' => Present::transaksi($t->refresh()), 'message' => __('cki::cki.mesej.dilaksana')]);
    }

    public function record(Request $request, Transaksi $transaksi): JsonResponse
    {
        $data = $request->validate([
            'tarikh_bayaran' => 'nullable|date|before_or_equal:today',
            'no_rujukan_eft' => 'nullable|string|max:60', 'no_bank_draf' => 'nullable|string|max:60', 'bank_penerbit' => 'nullable|string|max:120',
            'rujukan_fpx' => 'nullable|string|max:60', 'nama_kaunter' => 'nullable|string|max:120', 'no_resit' => 'nullable|string|max:60',
        ]);

        return response()->json(['data' => Present::transaksi($this->service->rekod($transaksi, $data, $request->user())), 'message' => __('cki::cki.mesej.direkod')]);
    }

    public function receipt(Request $request, Transaksi $transaksi): JsonResponse
    {
        $data = $request->validate(['fail' => 'required|file|max:25600', 'no_resit' => 'required|string|max:60', 'amaun' => 'nullable|numeric|min:0.01', 'tarikh_resit' => 'nullable|date']);
        $bil = Cukai::query()->withoutGlobalScope('access')->findOrFail($transaksi->id_bil);
        $r = $this->service->resit($bil, $transaksi, $request->file('fail'), [
            'no_resit' => $data['no_resit'], 'amaun' => isset($data['amaun']) ? (string) $data['amaun'] : null, 'tarikh_resit' => $data['tarikh_resit'] ?? null,
        ], $request->user());

        return response()->json(['data' => Present::resit($r), 'message' => __('cki::cki.mesej.resit')], 201);
    }

    /** Receipt for a bill paid outside a SISDATA transaction (manual fallback, SRS-CKI-07). */
    public function billReceipt(Request $request, Cukai $cukai): JsonResponse
    {
        $data = $request->validate(['fail' => 'required|file|max:25600', 'no_resit' => 'required|string|max:60', 'amaun' => 'nullable|numeric|min:0.01', 'tarikh_resit' => 'nullable|date']);
        $r = $this->service->resit($cukai, null, $request->file('fail'), [
            'no_resit' => $data['no_resit'], 'amaun' => isset($data['amaun']) ? (string) $data['amaun'] : null, 'tarikh_resit' => $data['tarikh_resit'] ?? null,
        ], $request->user());

        return response()->json(['data' => Present::resit($r), 'message' => __('cki::cki.mesej.resit')], 201);
    }

    public function complete(Request $request, Transaksi $transaksi): JsonResponse
    {
        return response()->json(['data' => Present::transaksi($this->service->selesai($transaksi, $request->user())->refresh()), 'message' => __('cki::cki.mesej.selesai')]);
    }

    public function grpStatus(Transaksi $transaksi): JsonResponse
    {
        return response()->json(['data' => Present::transaksi($this->service->semakGrp($transaksi)->refresh())]);
    }
}
