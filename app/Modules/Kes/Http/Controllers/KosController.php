<?php

namespace App\Modules\Kes\Http\Controllers;

use App\Modules\Kes\Http\Present;
use App\Modules\Kes\Models\KosGuaman;
use App\Modules\Kes\Services\KosService;
use App\Modules\Kes\Support\Akses;
use App\Modules\Kes\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-KES-15: legal costs, payment approval (WFE) and GRP MAIS verification. */
class KosController extends KesBaseController
{
    public function __construct(Akses $akses, private KosService $service, private Present $present)
    {
        parent::__construct($akses);
    }

    public function store(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $c = $this->service->tambah($k, $request->validate($this->rules(true)), $user);

        return response()->json(['data' => $this->present->kos($c), 'message' => __('kes::kes.mesej.kos')], 201);
    }

    public function update(Request $request, string $kes, int $kos): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $this->urus($request, $k);
        $c = KosGuaman::query()->where('kes_mahkamah_id', $k->id)->findOrFail($kos);

        return response()->json(['data' => $this->present->kos($this->service->kemasKini($k, $c, $request->validate($this->rules(false)))), 'message' => __('kes::kes.mesej.kos')]);
    }

    public function mohonBayaran(Request $request, string $kes, int $kos): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $c = KosGuaman::query()->where('kes_mahkamah_id', $k->id)->findOrFail($kos);
        $this->service->mohonBayaran($k, $c, $user);

        return response()->json(['data' => $this->present->kos($c->refresh()), 'message' => __('kes::kes.mesej.aliran_dimulakan')]);
    }

    public function semakGrp(Request $request, string $kes, int $kos): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $this->urus($request, $k);
        $c = KosGuaman::query()->where('kes_mahkamah_id', $k->id)->findOrFail($kos);
        $status = $this->service->semakGrp($c);

        return response()->json(['data' => $this->present->kos($c->refresh()), 'message' => __('kes::kes.mesej.grp', ['status' => Kod::STATUS_BAYARAN[$status] ?? $status])]);
    }

    /** @return array<string, list<mixed>> */
    private function rules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'kategori_kos' => [$req, Rule::in(array_keys(Kod::KATEGORI_KOS))],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'amaun' => [$req, 'regex:/^\d{1,12}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'tarikh' => [$req, 'date_format:Y-m-d'],
            'no_resit' => ['nullable', 'string', 'max:60'],
            'id_dokumen' => ['nullable', 'string', 'max:64'],
            'kes_panel_peguam_id' => ['nullable', 'integer', 'exists:kes_panel_peguam,id'],
        ];
    }
}
