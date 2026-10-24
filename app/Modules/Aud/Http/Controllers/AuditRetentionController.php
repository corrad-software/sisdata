<?php

namespace App\Modules\Aud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Models\AuditRetensiPolisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-AUD-20: retention policy (Pentadbir Sistem). Changes are audited by the global listener. */
class AuditRetentionController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->present(AuditRetensiPolisi::current())]);
    }

    public function update(Request $request): JsonResponse
    {
        $min = (int) config('aud.retensi_minimum_bulan', 24);
        $validated = $request->validate([
            'tempoh_dalam_talian_bulan' => ['required', 'integer', "min:{$min}", 'max:600'],
            'tempoh_arkib_tahun' => ['nullable', 'integer', 'min:1', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [
            'tempoh_dalam_talian_bulan.min' => __('aud::aud.retensi_minimum', ['bulan' => $min]),
        ]);

        $policy = AuditRetensiPolisi::current();
        $policy->update($validated);

        return response()->json(['data' => $this->present($policy), 'message' => __('aud::aud.retensi_dikemas_kini')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AuditRetensiPolisi $policy): array
    {
        return [
            'tempoh_dalam_talian_bulan' => $policy->tempoh_dalam_talian_bulan,
            'tempoh_arkib_tahun' => $policy->tempoh_arkib_tahun,
            'catatan' => $policy->catatan,
            'minimum_bulan' => (int) config('aud.retensi_minimum_bulan', 24),
            'dikemas_kini_pada' => $policy->updated_at?->toIso8601String(),
        ];
    }
}
