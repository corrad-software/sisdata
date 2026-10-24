<?php

namespace App\Modules\Adu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\PenyegerakanPusat;
use App\Modules\Adu\Services\AduanService;
use App\Modules\Adu\Services\SegerakPusat;
use App\Modules\Adu\Support\Rujukan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SRS-ADU-07 / acceptance #6: Sistem Aduan berpusat MAIS — inbound push
 * endpoint (machine account with adu.integrasi.cipta, via the API Gateway),
 * pull import, the status outbox and its manual retry (SRS-INT-10).
 */
class IntegrasiController extends Controller
{
    public function __construct(private SegerakPusat $pusat) {}

    /** Inbound complaint pushed by the central system. Idempotent on id_luaran. */
    public function terima(Request $request, AduanService $service): JsonResponse
    {
        $data = $request->validate([
            'id_luaran' => ['required', 'string', 'max:100'],
            'kategori' => ['required', 'string', 'max:20'],
            'keterangan' => ['required', 'string', 'max:5000'],
            'lokasi_teks' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:0.5,7.5'],
            'lng' => ['nullable', 'numeric', 'between:99,120'],
            'kod_daerah' => ['nullable', 'string', 'size:4'],
            'nama' => ['nullable', 'string', 'max:200'],
            'no_telefon' => ['nullable', 'string', 'max:30'],
            'emel' => ['nullable', 'email', 'max:200'],
            'tanpa_nama' => ['nullable', 'boolean'],
        ]);
        $existing = Aduan::query()->withoutGlobalScope('access')->where('saluran_penerimaan', 'sistem_aduan_pusat')->where('rujukan_sumber', $data['id_luaran'])->first();
        if (! app(Rujukan::class)->kategoriSah($data['kategori'])) {
            $data['kategori'] = 'LAIN';
        }
        $aduan = $existing ?? $this->pusat->terimaSatu($service, $data);

        return response()->json(['data' => ['id' => $aduan->id, 'no_rujukan_aduan' => $aduan->no_rujukan_aduan, 'sedia_ada' => $existing !== null]], $existing ? 200 : 201);
    }

    public function import(): JsonResponse
    {
        return response()->json(['data' => $this->pusat->import(), 'message' => __('adu::adu.mesej.import_selesai')]);
    }

    public function outbox(Request $request): JsonResponse
    {
        $page = PenyegerakanPusat::query()->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))->orderByDesc('id')->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (PenyegerakanPusat $p) => $p->only(['id', 'aduan_id', 'id_aduan_sistem_luaran', 'status_aduan', 'status', 'cubaan', 'ralat_terakhir']) + ['dihantar_pada' => $p->dihantar_pada?->toIso8601String(), 'dicipta' => $p->created_at?->toIso8601String()])->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function ulangCuba(): JsonResponse
    {
        return response()->json(['data' => $this->pusat->ulangCuba(), 'message' => __('adu::adu.mesej.ulang_cuba')]);
    }
}
