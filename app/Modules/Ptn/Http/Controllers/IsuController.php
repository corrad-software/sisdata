<?php

namespace App\Modules\Ptn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Ptn\Http\Present;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\TindakanPembetulan;
use App\Modules\Ptn\Services\PenemuanService;
use App\Modules\Ptn\Services\TindakanService;
use App\Modules\Ptn\Support\Akses;
use App\Modules\Ptn\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** SRS-PTN-13/17/18/19: monitoring issues and corrective actions. */
class IsuController extends Controller
{
    public function __construct(private Present $present, private LotRegistry $lots, private Akses $akses) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'kod_daerah' => ['nullable', 'string'], 'status' => ['nullable', 'string'], 'kategori_isu' => ['nullable', 'string'], 'tahap' => ['nullable', 'string'],
            'lot_id' => ['nullable', 'uuid'], 'q' => ['nullable', 'string', 'max:50'], 'terbuka' => ['nullable', 'boolean'], 'saya' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'max:100'],
        ]);
        $q = IsuPemantauan::query()
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status_isu', $v))
            ->when($f['kategori_isu'] ?? null, fn ($q, $v) => $q->where('kategori_isu', $v))
            ->when($f['tahap'] ?? null, fn ($q, $v) => $q->where('tahap_keterukan', $v))
            ->when($f['lot_id'] ?? null, fn ($q, $v) => $q->where('lot_id', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('no_rujukan', 'ilike', '%'.$v.'%'))
            ->when(! empty($f['terbuka']), fn ($q) => $q->whereIn('status_isu', IsuPemantauan::TERBUKA))
            ->when(! empty($f['saya']), fn ($q) => $q->whereHas('tindakan', fn ($t) => $t->where('id_pegawai_bertanggungjawab', $request->user()->id)))
            ->orderByRaw("CASE tahap_keterukan WHEN 'kritikal' THEN 0 WHEN 'tinggi' THEN 1 WHEN 'sederhana' THEN 2 ELSE 3 END")->orderByDesc('tarikh_dibuka');
        $page = $q->paginate((int) ($f['per_page'] ?? 25));
        $lots = $this->lots->summaries($page->getCollection()->pluck('lot_id')->unique()->values()->all(), $request->user());

        return response()->json([
            'data' => $page->getCollection()->map(fn ($i) => $this->present->isu($i, $lots[$i->lot_id]['no_lot'] ?? null) + [
                'tindakan_ringkas' => $i->tindakan->map(fn ($t) => $this->present->tindakan($t))->values(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, string $isu): JsonResponse
    {
        $i = IsuPemantauan::query()->with(['tindakan.pegawai', 'lawatan'])->findOrFail($isu);
        $lot = $this->lots->find($i->lot_id, $request->user());

        return response()->json(['data' => $this->present->isu($i, $lot['no_lot'] ?? null) + [
            'lot' => $lot ? $lot + ['pautan' => $this->lots->profileLink($i->lot_id)] : null,
            'lawatan' => $i->lawatan ? ['id' => $i->lawatan->id, 'no_rujukan' => $i->lawatan->no_rujukan, 'tarikh_lawatan' => $i->lawatan->tarikh_lawatan->toDateString()] : null,
            'tindakan' => $i->tindakan->map(fn ($t) => $this->present->tindakan($t) + ['boleh_urus' => $this->akses->bolehUrusTindakan($request->user(), $t)])->values(),
        ]]);
    }

    public function store(Request $request, PenemuanService $service): JsonResponse
    {
        $d = $request->validate([
            'lot_id' => ['required', 'uuid'], 'kategori_isu' => ['required', Rule::in(array_keys(Lalai::KATEGORI_ISU))],
            'tahap_keterukan' => ['required', Rule::in(array_keys(Lalai::TAHAP_KETERUKAN))], 'keterangan' => ['required', 'string', 'max:5000'],
        ]);
        $lot = $this->lots->find($d['lot_id'], $request->user());
        if ($lot === null) {
            throw ValidationException::withMessages(['lot_id' => __('ptn::ptn.lot_tiada')]);
        }
        $i = $service->cipta(['lot' => $lot] + $d, $request->user());

        return response()->json(['data' => $this->present->isu($i, $lot['no_lot'])], 201);
    }

    public function tambahTindakan(Request $request, string $isu, TindakanService $service): JsonResponse
    {
        $i = IsuPemantauan::query()->findOrFail($isu);
        $d = $request->validate(['langkah_tindakan' => ['required', 'string', 'max:5000'], 'id_pegawai_bertanggungjawab' => ['required', 'integer', 'exists:users,id'], 'tarikh_sasaran' => ['required', 'date', 'after_or_equal:today']]);
        $t = $service->cipta($i, $d, $request->user());

        return response()->json(['data' => $this->present->tindakan($t->load('pegawai'))], 201);
    }

    private function tindakanBoleh(Request $request, int $id): TindakanPembetulan
    {
        $t = TindakanPembetulan::query()->with(['pegawai', 'isu'])->findOrFail($id);
        if (! $this->akses->bolehUrusTindakan($request->user(), $t)) {
            abort(403);
        }

        return $t;
    }

    public function kemaskiniTindakan(Request $request, int $tindakan, TindakanService $service): JsonResponse
    {
        $t = $this->tindakanBoleh($request, $tindakan);
        $d = $request->validate([
            'status_kemajuan' => ['sometimes', Rule::in(['belum_mula', 'dalam_tindakan'])], 'peratus_kemajuan' => ['sometimes', 'integer', 'between:0,99'],
            'catatan_kemajuan' => ['sometimes', 'nullable', 'string', 'max:5000'], 'tarikh_sasaran' => ['sometimes', 'date'],
            'justifikasi_lanjutan' => ['nullable', 'string', 'max:2000'], 'id_pegawai_bertanggungjawab' => ['sometimes', 'integer', 'exists:users,id'],
        ]);
        if (isset($d['id_pegawai_bertanggungjawab']) && ! $request->user()->can('ptn.isu.kemas_kini')) {
            unset($d['id_pegawai_bertanggungjawab']);
        }
        $service->kemaskini($t, $d, $request->user());

        return response()->json(['data' => $this->present->tindakan($t->refresh()->load('pegawai'))]);
    }

    public function selesaiTindakan(Request $request, int $tindakan, TindakanService $service): JsonResponse
    {
        $t = $this->tindakanBoleh($request, $tindakan);
        $d = $request->validate(['catatan' => ['nullable', 'string', 'max:5000']]);
        $service->mohonPengesahan($t, $d['catatan'] ?? null, $request->user());

        return response()->json(['data' => $this->present->tindakan($t->refresh()->load('pegawai'))]);
    }

    public function eskalasiAduan(Request $request, string $isu, PenemuanService $service): JsonResponse
    {
        $i = IsuPemantauan::query()->with('lawatan')->findOrFail($isu);
        $id = $service->eskalasiAduan($i, $request->user());

        return response()->json(['data' => ['id_aduan' => $id, 'pautan' => '/admin/adu/aduan/'.$id], 'message' => __('ptn::ptn.aduan_dibuka')]);
    }

    public function eskalasiKes(Request $request, string $isu, PenemuanService $service): JsonResponse
    {
        $i = IsuPemantauan::query()->findOrFail($isu);
        $id = $service->eskalasiKes($i, $request->user());

        return response()->json(['data' => ['id_kes' => $id]]);
    }

    public function pegawai(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $rows = User::query()->where('is_active', true)->when($q !== '', fn ($w) => $w->where('name', 'ilike', '%'.$q.'%'))->orderBy('name')->limit(20)->get(['id', 'name']);

        return response()->json(['data' => $rows->map(fn ($u) => ['id' => $u->id, 'nama' => $u->name])]);
    }
}
