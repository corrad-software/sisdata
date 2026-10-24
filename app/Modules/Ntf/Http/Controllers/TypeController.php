<?php

namespace App\Modules\Ntf\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ntf\Models\Jenis;
use App\Modules\Ntf\Models\PenerimaPeraturan;
use App\Modules\Ntf\Models\Templat;
use App\Modules\Ntf\Services\Settings;
use App\Modules\Ntf\Services\TemplateRenderer;
use App\Modules\Ntf\Services\TypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin configuration of types, recipient rules and versioned templates
 * without code changes (SRS-NTF-03/05/08, SRS-ADM-03). Every change is a
 * model change, recorded before/after by MOD-AUD.
 */
class TypeController extends Controller
{
    public function __construct(private TemplateRenderer $renderer, private Settings $settings) {}

    public function index(Request $request, TypeRegistry $types): JsonResponse
    {
        $types->syncAll();

        $rows = Jenis::query()->with('templatAktif')->withCount('peraturan')
            ->when($request->query('modul'), fn ($q, $v) => $q->where('modul', $v))
            ->orderBy('modul')->orderBy('nama')->get()
            ->map(fn (Jenis $j) => $this->present($j) + [
                'versi_templat' => $j->templatAktif?->versi,
                'bilangan_peraturan' => $j->getAttribute('peraturan_count'),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function show(Jenis $jenis): JsonResponse
    {
        $jenis->load(['templat' => fn ($q) => $q->orderByDesc('versi'), 'peraturan']);

        return response()->json(['data' => $this->present($jenis) + [
            'templat' => $jenis->templat->map(fn (Templat $t) => [
                'id' => $t->id,
                'versi' => $t->versi,
                'subjek' => $t->subjek,
                'kandungan' => $t->kandungan,
                'aktif' => $t->aktif,
                'catatan' => $t->catatan,
                'dicipta_oleh' => $t->dicipta_oleh,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'peraturan' => $jenis->peraturan->map(fn (PenerimaPeraturan $p) => [
                'id' => $p->id, 'sasaran' => $p->sasaran, 'nilai' => $p->nilai, 'aktif' => $p->aktif,
            ]),
            'medan_sistem' => TemplateRenderer::SYSTEM_FIELDS,
            'medan_sensitif_global' => $this->settings->sensitiveFields(),
        ]]);
    }

    public function update(Request $request, Jenis $jenis): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['sometimes', 'string', 'max:255'],
            'keutamaan' => ['sometimes', Rule::in(Jenis::KEUTAMAAN)],
            'wajib' => ['sometimes', 'boolean'],
            'saluran_lalai' => ['sometimes', 'array', 'min:1'],
            'saluran_lalai.*' => [Rule::in(Jenis::SALURAN)],
            'kekerapan' => ['sometimes', Rule::in(Jenis::KEKERAPAN)],
            'tempoh_eskalasi_minit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:43200'],
            'tempoh_pendiaman_minit' => ['sometimes', 'integer', 'min:0', 'max:43200'],
            'hari_peringatan' => ['sometimes', 'nullable', 'array', 'max:12'],
            'hari_peringatan.*' => ['integer', 'min:0', 'max:730'],
            'medan_sensitif' => ['sometimes', 'array'],
            'medan_sensitif.*' => ['string', 'max:60'],
            'aktif' => ['sometimes', 'boolean'],
        ]);

        $jenis->fill($data);
        if ($jenis->kekerapan === 'ringkasan' && ! $jenis->bolehRingkasan()) {
            throw ValidationException::withMessages(['kekerapan' => __('ntf::ntf.ringkasan_tidak_dibenarkan')]);
        }
        if (isset($data['hari_peringatan'])) {
            $days = array_values(array_unique(array_map('intval', $data['hari_peringatan'])));
            rsort($days);
            $jenis->hari_peringatan = $days;
        }
        if (isset($data['saluran_lalai'])) {
            $jenis->saluran_lalai = array_values(array_unique($data['saluran_lalai']));
        }
        $jenis->save();

        return response()->json(['message' => __('ntf::ntf.jenis_dikemas_kini'), 'data' => $this->present($jenis->fresh() ?? $jenis)]);
    }

    /** New template version, immediately active; older versions stay for history/rollback. */
    public function storeTemplate(Request $request, Jenis $jenis): JsonResponse
    {
        $data = $request->validate([
            'subjek' => ['required', 'string', 'max:200'],
            'kandungan' => ['required', 'string', 'max:5000'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);
        $this->assertPlaceholders($jenis, $data['subjek'].' '.$data['kandungan']);

        $templat = DB::transaction(function () use ($jenis, $data, $request) {
            $versi = (int) $jenis->templat()->max('versi') + 1;
            $jenis->templat()->where('aktif', true)->get()->each(fn (Templat $t) => $t->update(['aktif' => false]));

            return $jenis->templat()->create($data + ['versi' => $versi, 'aktif' => true, 'dicipta_oleh' => $request->user()->id]);
        });

        return response()->json(['message' => __('ntf::ntf.templat_disimpan', ['versi' => $templat->versi]), 'data' => ['id' => $templat->id, 'versi' => $templat->versi]], 201);
    }

    public function activateTemplate(Jenis $jenis, Templat $templat): JsonResponse
    {
        abort_unless($templat->jenis_id === $jenis->id, 404);

        DB::transaction(function () use ($jenis, $templat) {
            $jenis->templat()->where('aktif', true)->whereKeyNot($templat->id)->get()->each(fn (Templat $t) => $t->update(['aktif' => false]));
            $templat->update(['aktif' => true]);
        });

        return response()->json(['message' => __('ntf::ntf.templat_diaktifkan', ['versi' => $templat->versi])]);
    }

    public function preview(Request $request, Jenis $jenis): JsonResponse
    {
        $data = $request->validate([
            'subjek' => ['required', 'string', 'max:200'],
            'kandungan' => ['required', 'string', 'max:5000'],
            'data' => ['sometimes', 'array'],
            'data.*' => ['nullable', 'string', 'max:500'],
        ]);

        $sample = [];
        foreach ($jenis->medan as $field) {
            $sample[$field] = $data['data'][$field] ?? '['.$field.']';
        }

        $allowed = array_merge($jenis->medan, TemplateRenderer::SYSTEM_FIELDS);

        return response()->json(['data' => [
            'subjek' => $this->renderer->render($data['subjek'], $sample),
            'kandungan' => $this->renderer->render($data['kandungan'], $sample),
            'medan_tidak_dikenali' => array_values(array_diff($this->renderer->placeholders($data['subjek'].' '.$data['kandungan']), $allowed)),
        ]]);
    }

    public function storeRule(Request $request, Jenis $jenis): JsonResponse
    {
        $data = $request->validate([
            'sasaran' => ['required', Rule::in([RecipientDirectory::PERANAN, RecipientDirectory::KEBENARAN, RecipientDirectory::BAHAGIAN, RecipientDirectory::DAERAH, RecipientDirectory::PENGGUNA])],
            'nilai' => ['required', 'string', 'max:255', Rule::unique('ntf_penerima_peraturan')->where('jenis_id', $jenis->id)->where('sasaran', $request->input('sasaran'))],
        ]);

        $rule = $jenis->peraturan()->create($data + ['aktif' => true]);

        return response()->json(['message' => __('ntf::ntf.peraturan_ditambah'), 'data' => ['id' => $rule->id]], 201);
    }

    public function destroyRule(Jenis $jenis, PenerimaPeraturan $peraturan): JsonResponse
    {
        abort_unless($peraturan->jenis_id === $jenis->id, 404);
        $peraturan->delete();

        return response()->json(['message' => __('ntf::ntf.peraturan_dipadam')]);
    }

    /** Template may use only declared, non-sensitive fields (SRS-NTF-05/19). */
    private function assertPlaceholders(Jenis $jenis, string $text): void
    {
        $sensitive = array_merge($jenis->medan_sensitif ?? [], $this->settings->sensitiveFields());

        foreach ($this->renderer->placeholders($text) as $field) {
            if (in_array($field, $sensitive, true)) {
                throw ValidationException::withMessages(['kandungan' => __('ntf::ntf.medan_sensitif_templat', ['medan' => $field])]);
            }
            if (! in_array($field, array_merge($jenis->medan, TemplateRenderer::SYSTEM_FIELDS), true)) {
                throw ValidationException::withMessages(['kandungan' => __('ntf::ntf.medan_tidak_dikenali', ['medan' => $field])]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function present(Jenis $j): array
    {
        return [
            'id' => $j->id,
            'kod' => $j->kod,
            'nama' => $j->nama,
            'modul' => $j->modul,
            'keutamaan' => $j->keutamaan,
            'wajib' => $j->wajib,
            'saluran_lalai' => $j->saluran_lalai,
            'kekerapan' => $j->kekerapan,
            'tempoh_eskalasi_minit' => $j->tempoh_eskalasi_minit,
            'tempoh_pendiaman_minit' => $j->tempoh_pendiaman_minit,
            'hari_peringatan' => $j->hari_peringatan,
            'medan' => $j->medan,
            'medan_sensitif' => $j->medan_sensitif,
            'aktif' => $j->aktif,
        ];
    }
}
