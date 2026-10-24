<?php

namespace App\Modules\Wfe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Support\WfeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Demo of the module contract: starts the declared `contoh.kelulusan` flow
 * for a throw-away record, exactly as an operating module would.
 */
class ContohController extends Controller
{
    public function __invoke(Request $request, WorkflowEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'tajuk' => ['required', 'string', 'max:200'],
            'nilai' => ['required', 'numeric', 'min:0'],
            'kod_daerah' => ['nullable', 'string', 'max:20'],
            'kategori_tanah' => ['nullable', 'string', 'max:50'],
        ]);

        $recordId = (string) Str::uuid();
        try {
            $id = $engine->start('contoh.kelulusan', 'contoh_permohonan', $recordId, $request->user(), [
                'nilai' => (float) $data['nilai'],
                'kod_daerah' => $data['kod_daerah'] ?? null,
                'kategori_tanah' => $data['kategori_tanah'] ?? null,
            ], $data['tajuk']);
        } catch (InvalidArgumentException $e) {
            throw new WfeException($e->getMessage(), 'tiada_aliran_aktif', 422);
        }

        return response()->json(['message' => __('wfe::wfe.message.contoh_dimulakan'), 'data' => ['id_instans' => $id, 'id_rekod' => $recordId]], 201);
    }
}
