<?php

namespace App\Modules\Hkm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hkm\Http\Present;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PerubahanHakMilik;
use App\Modules\Hkm\Services\ChangeService;
use App\Modules\Hkm\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-HKM-19 — change requests (approval itself happens in MOD-WFE). */
class PerubahanController extends Controller
{
    public function __construct(private ChangeService $changes, private Present $present) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(array_keys(Kod::STATUS_PERUBAHAN))], 'saya' => ['nullable', 'boolean']]);

        $page = PerubahanHakMilik::query()->whereHas('hakMilik')->with(['hakMilik', 'pemohon'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('saya'), fn ($q) => $q->where('id_pemohon', $request->user()->id))
            ->when($request->query('hak_milik_id'), fn ($q, $id) => $q->where('hak_milik_id', $id))
            ->orderByDesc('created_at')->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (PerubahanHakMilik $c) => $this->present->change($c))->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function show(PerubahanHakMilik $perubahan): JsonResponse
    {
        abort_unless(HakMilik::query()->whereKey($perubahan->hak_milik_id)->exists(), 404);

        return response()->json(['data' => $this->present->change($perubahan->load(['hakMilik', 'pemohon']))]);
    }

    public function store(Request $request, HakMilik $hakMilik): JsonResponse
    {
        $change = $this->changes->request($hakMilik, $request->all(), $request->user());

        return response()->json(['data' => $this->present->change($change->load(['hakMilik', 'pemohon'])), 'message' => __('hkm::hkm.mesej.perubahan_dihantar')], 201);
    }
}
