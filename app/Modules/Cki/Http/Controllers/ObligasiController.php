<?php

namespace App\Modules\Cki\Http\Controllers;

use App\Modules\Cki\Http\Present;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Services\ObligasiService;
use App\Modules\Cki\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-CKI-01/06: tax register (obligations per lot / premise), scoped by district. */
class ObligasiController
{
    public function __construct(private ObligasiService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = Obligasi::query()->with('agensi')->leftJoin('lot_tanah as l', 'l.id', '=', 'obligasi_cukai.lot_id')->select('obligasi_cukai.*', 'l.no_lot')
            ->when($request->filled('lot_id'), fn ($w) => $w->where('obligasi_cukai.lot_id', $request->query('lot_id')))
            ->when($request->filled('jenis_cukai'), fn ($w) => $w->where('obligasi_cukai.jenis_cukai', $request->query('jenis_cukai')))
            ->when($request->filled('kod_daerah'), fn ($w) => $w->where('obligasi_cukai.kod_daerah', $request->query('kod_daerah')))
            ->when($request->filled('kod_agensi'), fn ($w) => $w->where('obligasi_cukai.kod_agensi', $request->query('kod_agensi')))
            ->when($request->filled('liputan'), fn ($w) => $w->where('obligasi_cukai.status_liputan_api', $request->query('liputan')))
            ->when($request->filled('q'), function ($w) use ($request) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->query('q')).'%';
                $w->where(fn ($x) => $x->where('obligasi_cukai.no_akaun_cukai', 'ilike', $like)->orWhere('l.no_lot', 'ilike', $like));
            })
            ->orderBy('obligasi_cukai.kod_daerah')->orderBy('l.no_lot');
        $page = $q->paginate(min(100, max(5, (int) $request->query('per_page', 20))));

        return response()->json([
            'data' => collect($page->items())->map(fn (Obligasi $o) => Present::obligasi($o))->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lot_id' => 'required|uuid',
            'id_premis' => 'nullable|integer',
            'jenis_cukai' => ['required', Rule::in(array_keys(Kod::JENIS_CUKAI))],
            'pihak_pengeluar' => ['nullable', Rule::in(array_keys(Kod::PIHAK_PENGELUAR))],
            'kod_pbt' => 'nullable|string|max:20',
            'no_akaun_cukai' => 'required|string|max:60',
            'catatan' => 'nullable|string|max:2000',
        ]);
        $o = $this->service->cipta($data, $request->user());

        return response()->json(['data' => Present::obligasi($o->load('agensi')), 'message' => __('cki::cki.mesej.obligasi_dicipta')], 201);
    }

    public function update(Request $request, Obligasi $obligasi): JsonResponse
    {
        $data = $request->validate([
            'no_akaun_cukai' => 'sometimes|string|max:60',
            'id_premis' => 'nullable|integer',
            'pihak_pengeluar' => ['sometimes', Rule::in(array_keys(Kod::PIHAK_PENGELUAR))],
            'aktif' => 'sometimes|boolean',
            'catatan' => 'nullable|string|max:2000',
        ]);

        return response()->json(['data' => Present::obligasi($this->service->kemaskini($obligasi, $data)->load('agensi'))]);
    }
}
