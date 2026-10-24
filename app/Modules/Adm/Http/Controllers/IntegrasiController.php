<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Models\Integrasi;
use App\Modules\Adm\Models\Rahsia;
use App\Modules\Adm\Services\IntegrasiService;
use App\Modules\Adm\Services\KelulusanKonfigurasi;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SRS-ADM-04 integration settings. Every create/change goes through
 * second-admin approval (SRS-ADM-18 "tetapan integrasi"); the secret is only
 * referenced by key, never stored here (SRS-ADM-28).
 */
class IntegrasiController extends Controller
{
    public function __construct(private KelulusanKonfigurasi $approvals, private IntegrasiService $integrations) {}

    public function index(): JsonResponse
    {
        $secrets = Rahsia::query()->pluck('kunci')->flip();

        return response()->json(['data' => Integrasi::query()->orderBy('nama')->get()->map(fn (Integrasi $i) => [
            ...$i->toArray(),
            'rahsia_wujud' => $i->id_rahsia !== null && isset($secrets[$i->id_rahsia]),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $approval = $this->approvals->request('integrasi', $data['kod'], null, array_intersect_key($data, array_flip(Integrasi::EDITABLE)), $data['justifikasi'], $request->user());

        return response()->json(['message' => __('adm::adm.kelulusan.submitted'), 'data' => KelulusanController::present($approval)], 202);
    }

    public function update(Request $request, Integrasi $integrasi): JsonResponse
    {
        $data = $this->validated($request, $integrasi);
        $before = $integrasi->only(Integrasi::EDITABLE);
        $after = array_merge($before, array_intersect_key($data, array_flip(Integrasi::EDITABLE)));
        if ($after == $before) {
            throw ValidationException::withMessages(['nama' => __('adm::adm.integrasi.no_change')]);
        }

        $approval = $this->approvals->request('integrasi', $integrasi->kod, $before, $after, $data['justifikasi'], $request->user());

        return response()->json(['message' => __('adm::adm.kelulusan.submitted'), 'data' => KelulusanController::present($approval)], 202);
    }

    public function test(Integrasi $integrasi): JsonResponse
    {
        return response()->json(['data' => $this->integrations->test($integrasi)]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Integrasi $existing): array
    {
        $req = $existing ? 'sometimes' : 'required';
        $data = $request->validate([
            'kod' => $existing ? ['prohibited'] : ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'unique:adm_integrasi,kod'],
            'nama' => [$req, 'string', 'max:200'],
            'jenis' => [$req, 'in:rest,soap,sftp,fail,ogc'],
            'endpoint' => [$req, 'string', 'max:500', 'regex:#^(https?|sftp|ftps?|file)://#i'],
            'id_rahsia' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_.-]+$/'],
            'tempoh_tamat_masa' => [$req, 'integer', 'min:1', 'max:600'],
            'kadar_capaian' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'jadual' => ['nullable', 'string', 'max:100'],
            'status' => [$req, 'in:aktif,tidak_aktif'],
            'justifikasi' => ['required', 'string', 'max:1000'],
        ], [], (array) __('adm::adm.attributes'));

        if (! empty($data['jadual']) && ! CronExpression::isValidExpression($data['jadual'])) {
            throw ValidationException::withMessages(['jadual' => __('adm::adm.tugasan.invalid_cron')]);
        }

        return $data;
    }
}
