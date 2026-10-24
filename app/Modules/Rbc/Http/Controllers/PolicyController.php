<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rbc\Services\SecurityPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Security policy settings (SRS-RBC-12/13/16/18/19, acceptance #4: timeout
 * changeable by Pentadbir Sistem without a release). Interim home until
 * MOD-ADM centralises system parameters.
 */
class PolicyController extends Controller
{
    public function __construct(private SecurityPolicy $policy) {}

    public function show(): JsonResponse
    {
        $values = $this->policy->all();

        return response()->json(['data' => collect(SecurityPolicy::DEFINITIONS)->map(fn ($definition, $key) => [
            'kunci' => $key,
            'label' => $definition['label'],
            'jenis' => $definition['type'],
            'nilai' => $values[$key],
            'lalai' => $definition['default'],
            'min' => $definition['min'] ?? null,
            'max' => $definition['max'] ?? null,
            'pilihan' => $definition['options'] ?? null,
        ])->values()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['nilai' => ['required', 'array']]);

        $this->policy->update($data['nilai'], $request->user());

        return $this->show();
    }
}
