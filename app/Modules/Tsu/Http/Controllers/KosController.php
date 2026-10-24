<?php

namespace App\Modules\Tsu\Http\Controllers;

use App\Modules\Tsu\Http\Present;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Services\KosService;
use App\Modules\Tsu\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-TSU-14: costs per stage and category; only Bahagian Kewangan changes them. */
class KosController extends TsuController
{
    public function __construct(private KosService $service, private Present $present) {}

    public function index(string $id): JsonResponse
    {
        $u = $this->urusan($id);

        return response()->json(['data' => [
            'item' => KosUrusan::query()->where('urusan_id', $u->id)->orderBy('id')->get()->map(fn ($k) => $this->present->kos($k))->values(),
            'ringkasan' => $this->service->summary($u),
        ]]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'kategori_kos' => ['required', Rule::in(array_keys(Lalai::KATEGORI_KOS))], 'amaun' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'peringkat_id' => ['nullable', 'integer'], 'keterangan' => ['nullable', 'string', 'max:255'], 'no_invois' => ['nullable', 'string', 'max:60'],
            'tarikh_invois' => ['nullable', 'date_format:Y-m-d'], 'tarikh_akhir_bayaran' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $k = $this->service->store($this->urusan($id), $data, $this->user($request));

        return response()->json(['message' => __('tsu::tsu.kos.direkod'), 'data' => $this->present->kos($k)], 201);
    }

    public function update(Request $request, int $kos): JsonResponse
    {
        $k = KosUrusan::query()->findOrFail($kos);
        $this->urusan($k->urusan_id); // scope check
        $data = $request->validate([
            'kategori_kos' => ['sometimes', Rule::in(array_keys(Lalai::KATEGORI_KOS))], 'amaun' => ['sometimes', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'keterangan' => ['sometimes', 'nullable', 'string', 'max:255'], 'status_bayaran' => ['sometimes', Rule::in(array_keys(Lalai::STATUS_BAYARAN))],
            'no_invois' => ['sometimes', 'nullable', 'string', 'max:60'], 'tarikh_invois' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'tarikh_akhir_bayaran' => ['sometimes', 'nullable', 'date_format:Y-m-d'], 'tarikh_bayaran' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'no_resit' => ['sometimes', 'nullable', 'string', 'max:60'], 'id_dokumen' => ['sometimes', 'nullable', 'string', 'max:64'], 'rujukan_grp' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);
        $k = $this->service->update($k, $data, $this->user($request));

        return response()->json(['message' => __('tsu::tsu.disimpan'), 'data' => $this->present->kos($k)]);
    }
}
