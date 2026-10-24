<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Models\User;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\TemplatSurat;
use App\Modules\Pjk\Services\SuratService;
use App\Modules\Pjk\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** SRS-PJK-09: letter templates and generated letters. */
class SuratController
{
    public function __construct(private SuratService $service) {}

    public function templates(Request $request): JsonResponse
    {
        $rows = TemplatSurat::query()->when(! $request->boolean('semua_versi'), fn ($q) => $q->where('status_aktif', true))->orderBy('kod')->orderByDesc('versi')->get();

        return response()->json(['data' => $rows->map(fn (TemplatSurat $t) => [
            'id' => $t->id, 'kod' => $t->kod, 'jenis_surat' => $t->jenis_surat, 'jenis_surat_nama' => Kod::JENIS_SURAT[$t->jenis_surat] ?? $t->jenis_surat,
            'tajuk' => $t->tajuk, 'kandungan' => $t->kandungan, 'medan_gabungan' => $t->medan_gabungan, 'versi' => $t->versi, 'status_aktif' => $t->status_aktif,
        ])->all(), 'medan' => SuratService::MEDAN]);
    }

    public function update(Request $request, TemplatSurat $templat): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $t = $this->service->updateTemplate($templat, $request->all(), $user);

        return response()->json(['data' => ['id' => $t->id, 'versi' => $t->versi], 'message' => __('pjk::pjk.mesej.templat_versi', ['versi' => $t->versi])]);
    }

    public function generate(Request $request, Kontrak $kontrak): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = Validator::make($request->all(), ['templat_id' => ['required', 'integer']], [], ['templat_id' => 'templat'])->validate();
        $s = $this->service->generate($kontrak, TemplatSurat::query()->findOrFail($data['templat_id']), $user);

        return response()->json(['data' => ['id' => $s->id, 'no_rujukan_surat' => $s->no_rujukan_surat, 'kandungan' => $s->kandungan, 'id_dokumen' => $s->id_dokumen], 'message' => __('pjk::pjk.mesej.surat_dijana', ['no' => $s->no_rujukan_surat])], 201);
    }

    /** Preview merge fields without generating (no number used). */
    public function preview(Kontrak $kontrak, TemplatSurat $templat): JsonResponse
    {
        return response()->json(['data' => ['kandungan' => $this->service->merge($templat->kandungan, $this->service->fields($kontrak) + ['no_rujukan_surat' => '(akan dijana)'])]]);
    }
}
