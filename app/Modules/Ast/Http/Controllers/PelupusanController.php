<?php

namespace App\Modules\Ast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ast\Http\Present;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetPelupusan;
use App\Modules\Ast\Services\PelupusanService;
use App\Modules\Ast\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Disposal / write-off / demolition requests (approval through MOD-WFE only). */
class PelupusanController extends Controller
{
    public function __construct(private PelupusanService $service, private Present $present) {}

    public function index(string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);

        return response()->json(['data' => AsetPelupusan::query()->with('pemohon')->where('id_aset', $a->id)->orderByDesc('created_at')->get()->map(fn ($p) => $this->present->pelupusan($p))->values()]);
    }

    public function store(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $d = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(Lalai::JENIS_PELUPUSAN))], 'sebab' => ['required', 'string', 'min:10', 'max:5000'],
            'kaedah' => ['nullable', Rule::in(array_keys(Lalai::KAEDAH_PELUPUSAN))], 'nilai_pelupusan' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'tarikh_dicadang' => ['nullable', 'date'],
        ]);
        $p = $this->service->mohon($a, $d, $request->user());

        return response()->json(['data' => $this->present->pelupusan($p->load('pemohon')), 'message' => __('ast::ast.pelupusan_dihantar')], 201);
    }
}
