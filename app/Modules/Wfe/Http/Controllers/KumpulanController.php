<?php

namespace App\Modules\Wfe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wfe\Models\Kumpulan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-WFE-05: approver groups — any member may act on the group's task. */
class KumpulanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Kumpulan::query()->with('ahli:id,name,email')->orderBy('nama')->get()->map(fn (Kumpulan $k) => [
            'id' => $k->id,
            'kod' => $k->kod,
            'nama' => $k->nama,
            'ahli' => $k->ahli->map(fn ($u) => ['id' => $u->id, 'nama' => $u->name, 'emel' => $u->email])->values(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $group = Kumpulan::create(['kod' => $data['kod'], 'nama' => $data['nama']]);
        $group->ahli()->sync($data['ahli'] ?? []);

        return response()->json(['message' => __('wfe::wfe.message.kumpulan_disimpan'), 'data' => ['id' => $group->id]], 201);
    }

    public function update(Request $request, Kumpulan $kumpulan): JsonResponse
    {
        $data = $this->validated($request, $kumpulan);
        $kumpulan->fill(['nama' => $data['nama']])->save(); // kod is fixed: definitions refer to it
        $kumpulan->ahli()->sync($data['ahli'] ?? []);

        return response()->json(['message' => __('wfe::wfe.message.kumpulan_disimpan')]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Kumpulan $current = null): array
    {
        return $request->validate([
            'kod' => [$current ? 'sometimes' : 'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('wf_kumpulan', 'kod')->ignore($current?->id)],
            'nama' => ['required', 'string', 'max:255'],
            'ahli' => ['nullable', 'array', 'max:200'],
            'ahli.*' => ['integer', 'exists:users,id'],
        ]);
    }
}
