<?php

namespace App\Modules\Kes\Http\Controllers;

use App\Modules\Kes\Http\Present;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Models\TarikhPenting;
use App\Modules\Kes\Services\ProsidingService;
use App\Modules\Kes\Services\Rujukan;
use App\Modules\Kes\Support\Akses;
use App\Modules\Kes\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-KES-07/08/10: proceedings and important dates. */
class ProsidingController extends KesBaseController
{
    public function __construct(Akses $akses, private ProsidingService $service, private Present $present, private Rujukan $ref)
    {
        parent::__construct($akses);
    }

    public function store(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $p = $this->service->rekod($k, $request->validate($this->rules(true)), $user);

        return response()->json(['data' => $this->present->prosiding($p), 'message' => __('kes::kes.mesej.prosiding')], 201);
    }

    public function update(Request $request, string $kes, int $prosiding): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $p = Prosiding::query()->where('kes_mahkamah_id', $k->id)->findOrFail($prosiding);
        $p = $this->service->kemasKini($k, $p, $request->validate($this->rules(false)), $user);

        return response()->json(['data' => $this->present->prosiding($p), 'message' => __('kes::kes.mesej.prosiding')]);
    }

    public function tambahTarikh(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate(['jenis' => ['required', Rule::in(array_keys(Kod::JENIS_TARIKH))], 'tarikh' => ['required', 'date_format:Y-m-d'], 'keterangan' => ['nullable', 'string', 'max:500']]);

        return response()->json(['data' => $this->present->tarikh($this->service->tarikhPenting($k, $d, $user)), 'message' => __('kes::kes.mesej.disimpan')], 201);
    }

    public function kemasKiniTarikh(Request $request, string $kes, int $tarikh): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $this->urus($request, $k);
        $t = TarikhPenting::query()->where('kes_mahkamah_id', $k->id)->findOrFail($tarikh);
        $t->fill($request->validate(['status' => ['sometimes', Rule::in(['belum', 'selesai', 'dibatalkan'])], 'tarikh' => ['sometimes', 'date_format:Y-m-d'], 'keterangan' => ['nullable', 'string', 'max:500']]))->save();

        return response()->json(['data' => $this->present->tarikh($t), 'message' => __('kes::kes.mesej.disimpan')]);
    }

    /** @return array<string, list<mixed>> */
    private function rules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'tarikh_prosiding' => [$req, 'date'],
            'jenis_prosiding' => [$req, Rule::in(array_keys(Kod::JENIS_PROSIDING))],
            'status' => ['nullable', Rule::in(array_keys(Kod::STATUS_PROSIDING))],
            'hasil' => ['nullable', 'string', 'max:5000'],
            'arahan_mahkamah' => ['nullable', 'string', 'max:5000'],
            'tarikh_berikutnya' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tarikh_prosiding'],
            'jenis_berikutnya' => ['nullable', Rule::in(array_keys(Kod::JENIS_PROSIDING))],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'status_kes' => ['nullable', 'string', Rule::in(array_keys($this->ref->status()))],
            'ikut_jenis' => ['nullable', 'boolean'],
        ];
    }
}
