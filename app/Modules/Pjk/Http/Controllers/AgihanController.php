<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Models\User;
use App\Modules\Pjk\Http\Present;
use App\Modules\Pjk\Models\DasarAgihanManfaat;
use App\Modules\Pjk\Models\KiraanAgihanManfaat;
use App\Modules\Pjk\Services\AgihanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** SRS-PJK-22..25: Dasar Agihan Manfaat — policies, calculations, simulation, confirmation. */
class AgihanController
{
    public function __construct(private AgihanService $service) {}

    public function policies(): JsonResponse
    {
        return response()->json(['data' => DasarAgihanManfaat::query()->orderBy('kategori_hartanah')->orderBy('status_perolehan')->orderByDesc('versi_dasar')->get()->map(fn ($d) => Present::dasar($d))->all()]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => Present::dasar($this->service->createPolicy($request->all(), $user)), 'message' => __('pjk::pjk.mesej.dasar_draf')], 201);
    }

    public function submitPolicy(Request $request, DasarAgihanManfaat $dasar): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => Present::dasar($this->service->submit($dasar, $user)), 'message' => __('pjk::pjk.mesej.dihantar_kelulusan')]);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = KiraanAgihanManfaat::query()->whereHas('premis')->with('premis:id,kod_premis,nama_premis')
            ->when($request->filled('tempoh'), fn ($q) => $q->where('tempoh_perakaunan', $request->query('tempoh')))
            ->when($request->filled('kod_daerah'), fn ($q) => $q->where('kod_daerah', $request->query('kod_daerah')))
            ->when($request->filled('kategori_hartanah'), fn ($q) => $q->where('kategori_hartanah', $request->query('kategori_hartanah')))
            ->when($request->filled('status_perolehan'), fn ($q) => $q->where('status_perolehan', $request->query('status_perolehan')))
            ->orderByDesc('tempoh_perakaunan')->orderBy('premis_id')->limit(1000)->get();

        return response()->json(['data' => $rows->map(fn (KiraanAgihanManfaat $k) => $k->toArray() + ['kod_premis' => $k->premis->kod_premis, 'nama_premis' => $k->premis->nama_premis])->all()]);
    }

    public function calculate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = Validator::make($request->all(), ['tempoh' => ['required', 'string'], 'premis_ids' => ['nullable', 'array'], 'premis_ids.*' => ['integer']])->validate();

        return response()->json(['data' => $this->service->calculate($data['tempoh'], $data['premis_ids'] ?? null, $user)]);
    }

    /** SRS-PJK-25: what-if with proposed rates ("kategori:status" => kadar) — nothing is stored. */
    public function simulate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = Validator::make($request->all(), [
            'tempoh' => ['required', 'string'], 'premis_ids' => ['nullable', 'array'], 'premis_ids.*' => ['integer'],
            'kadar' => ['required', 'array'], 'kadar.*' => ['decimal:0,4', 'min:0', 'max:100'],
        ])->validate();

        return response()->json(['data' => $this->service->calculate($data['tempoh'], $data['premis_ids'] ?? null, $user, array_map('strval', $data['kadar']))]);
    }

    public function confirm(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = Validator::make($request->all(), ['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])->validate();

        return response()->json(['data' => ['disahkan' => $this->service->confirm(array_map('intval', $data['ids']), $user)], 'message' => __('pjk::pjk.mesej.agihan_disahkan')]);
    }
}
