<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Services\ParameterService;
use App\Modules\Adm\Services\Siem\LogKeselamatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-ADM-29: public status for the UI banner + admin switch. */
class PenyelenggaraanController extends Controller
{
    public function __construct(private ParameterService $parameters) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->state()]);
    }

    public function update(Request $request, LogKeselamatan $security): JsonResponse
    {
        $data = $request->validate([
            'aktif' => ['required', 'boolean'],
            'notis' => ['nullable', 'string', 'max:1000'],
            'tamat_dijangka' => ['nullable', 'date'],
            'justifikasi' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $request->user();

        if (array_key_exists('notis', $data) && $data['notis'] !== null) {
            $this->parameters->store('adm.notis_penyelenggaraan', $data['notis'], $data['justifikasi'] ?? null, $user);
        }
        $this->parameters->store('adm.penyelenggaraan_tamat', $data['tamat_dijangka'] ?? '', $data['justifikasi'] ?? null, $user);
        $this->parameters->store('adm.mod_penyelenggaraan', (bool) $data['aktif'], $data['justifikasi'] ?? null, $user);

        $security->rekod($data['aktif'] ? 'mod_penyelenggaraan_hidup' : 'mod_penyelenggaraan_mati', 'success', $user->id, 'Mod penyelenggaraan');

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $this->state()]);
    }

    /** @return array{aktif: bool, notis: string, tamat_dijangka: ?string} */
    private function state(): array
    {
        $end = (string) $this->parameters->get('adm.penyelenggaraan_tamat', '');

        return [
            'aktif' => (bool) $this->parameters->get('adm.mod_penyelenggaraan', false),
            'notis' => (string) $this->parameters->get('adm.notis_penyelenggaraan', ''),
            'tamat_dijangka' => $end !== '' ? $end : null,
        ];
    }
}
