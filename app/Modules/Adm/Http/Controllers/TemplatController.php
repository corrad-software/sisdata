<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Models\Templat;
use App\Modules\Adm\Models\TemplatVersi;
use App\Modules\Adm\Services\TemplatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-ADM-03: report templates (notification templates live in MOD-NTF). */
class TemplatController extends Controller
{
    public function __construct(private TemplatService $templates) {}

    public function index(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');

        return response()->json(['data' => Templat::query()
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('kod', 'ilike', "%{$q}%")->orWhere('nama', 'ilike', "%{$q}%")))
            ->orderBy('nama')->get()]);
    }

    public function show(Templat $templat): JsonResponse
    {
        return response()->json(['data' => [...$templat->toArray(), 'versi_list' => $templat->versiList()->orderByDesc('versi')->get()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + ['kod' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_.-]+$/', 'unique:adm_templat,kod']], [], (array) __('adm::adm.attributes'));

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $this->templates->create($data, $request->user())], 201);
    }

    public function update(Request $request, Templat $templat): JsonResponse
    {
        $data = $request->validate(array_map(fn ($r) => ['sometimes', ...array_diff($r, ['required'])], $this->rules()), [], (array) __('adm::adm.attributes'));

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $this->templates->update($templat, $data, $request->user())]);
    }

    public function restore(Request $request, Templat $templat, int $versi): JsonResponse
    {
        $data = $request->validate(['justifikasi' => ['nullable', 'string', 'max:1000']]);
        $version = TemplatVersi::query()->where('id_templat', $templat->id)->where('versi', $versi)->firstOrFail();

        return response()->json(['message' => __('adm::adm.templat.restored', ['versi' => $versi]), 'data' => $this->templates->restore($templat, $version, $data['justifikasi'] ?? null, $request->user())]);
    }

    public function preview(Request $request, Templat $templat): JsonResponse
    {
        $data = $request->validate(['data' => ['nullable', 'array'], 'data.*' => ['nullable']]);
        $sample = $data['data'] ?? array_combine($templat->medan ?? [], array_map(fn ($f) => '['.$f.']', $templat->medan ?? []));

        return response()->json(['data' => [
            'format' => $templat->format_output,
            'kandungan' => TemplatService::fill($templat->kandungan, $sample),
        ]]);
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:200'],
            'format_output' => ['required', Rule::in(Templat::FORMATS)],
            'kandungan' => ['required', 'string', 'max:200000'],
            'medan' => ['nullable', 'array', 'max:200'],
            'medan.*' => ['string', 'regex:/^[a-z0-9_]+$/i', 'max:60'],
            'status' => ['nullable', 'in:aktif,draf,tidak_aktif'],
            'justifikasi' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
