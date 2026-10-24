<?php

namespace App\Modules\Hkm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PermohonanHakMilikKekal;
use App\Modules\Hkm\Models\PermohonanPeringkat;
use App\Modules\Hkm\Services\PermanentTitleService;
use App\Modules\Hkm\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-HKM-16 — permanent-title applications. */
class PermohonanController extends Controller
{
    public function __construct(private PermanentTitleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'peringkat' => ['nullable', Rule::in(array_keys(Kod::PERINGKAT_HMK))],
            'status' => ['nullable', Rule::in(array_keys(Kod::STATUS_HMK))],
        ]);

        $page = PermohonanHakMilikKekal::query()->whereHas('hakMilikSementara')->with(['hakMilikSementara', 'hakMilikBaharu'])
            ->when($request->query('peringkat'), fn ($q, $p) => $q->where('peringkat_semasa', $p))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status_kelulusan', $s))
            ->when($request->query('pejabat_tanah'), fn ($q, $p) => $q->whereRaw('lower(pejabat_tanah) like ?', ['%'.mb_strtolower((string) $p).'%']))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->whereRaw('lower(no_permohonan) like ?', ['%'.mb_strtolower((string) $t).'%'])
                ->orWhereHas('hakMilikSementara', fn ($h) => $h->whereRaw('lower(no_hak_milik) like ?', ['%'.mb_strtolower((string) $t).'%']))))
            ->orderByDesc('tarikh_permohonan')->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (PermohonanHakMilikKekal $p) => $this->row($p))->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function show(PermohonanHakMilikKekal $permohonan): JsonResponse
    {
        $this->assertScope($permohonan);
        $permohonan->load(['hakMilikSementara', 'hakMilikBaharu', 'peringkat.pegawai']);

        return response()->json(['data' => $this->row($permohonan) + [
            'senarai_semak_dokumen' => $permohonan->senarai_semak_dokumen,
            'semak_lengkap' => $permohonan->semakLengkap(),
            'jumlah_bayaran' => $permohonan->jumlah_bayaran,
            'tarikh_bayaran' => $permohonan->tarikh_bayaran?->format('Y-m-d'),
            'no_resit' => $permohonan->no_resit,
            'catatan' => $permohonan->catatan,
            'sejarah_peringkat' => $permohonan->peringkat->sortBy('id')->map(fn (PermohonanPeringkat $s) => [
                'peringkat' => $s->peringkat,
                'peringkat_nama' => Kod::PERINGKAT_HMK[$s->peringkat] ?? $s->peringkat,
                'status' => $s->status,
                'tarikh' => $s->tarikh->format('Y-m-d'),
                'catatan' => $s->catatan,
                'pegawai' => $s->pegawai?->name,
            ])->values(),
        ]]);
    }

    public function store(Request $request, HakMilik $hakMilik): JsonResponse
    {
        $application = $this->service->create($hakMilik, $request->all(), $request->user());

        return response()->json(['data' => $this->row($application->load(['hakMilikSementara'])), 'message' => __('hkm::hkm.mesej.permohonan_didaftar')], 201);
    }

    public function checklist(Request $request, PermohonanHakMilikKekal $permohonan): JsonResponse
    {
        $this->assertScope($permohonan);
        $this->service->checklist($permohonan, (array) $request->input('senarai', []));

        return $this->show($permohonan);
    }

    public function advance(Request $request, PermohonanHakMilikKekal $permohonan): JsonResponse
    {
        $this->assertScope($permohonan);
        $this->service->advance($permohonan, $request->all(), $request->user());

        return $this->show($permohonan->refresh());
    }

    public function payment(Request $request, PermohonanHakMilikKekal $permohonan): JsonResponse
    {
        $this->assertScope($permohonan);
        $this->service->payment($permohonan, $request->all());

        return $this->show($permohonan->refresh());
    }

    public function receive(Request $request, PermohonanHakMilikKekal $permohonan): JsonResponse
    {
        $this->assertScope($permohonan);
        if ($request->filled('peti_besi') && ! $request->user()->can('hkm.peti_besi.cipta')) {
            abort(403, __('hkm::hkm.ralat.tiada_kebenaran_peti_besi'));
        }
        abort_unless($request->user()->can('hkm.hak_milik.cipta'), 403);
        $this->service->receive($permohonan, $request->all(), $request->user());

        return $this->show($permohonan->refresh());
    }

    private function assertScope(PermohonanHakMilikKekal $permohonan): void
    {
        abort_unless(HakMilik::query()->whereKey($permohonan->hak_milik_sementara_id)->exists(), 404);
    }

    /** @return array<string, mixed> */
    private function row(PermohonanHakMilikKekal $p): array
    {
        return [
            'id' => $p->id,
            'no_permohonan' => $p->no_permohonan,
            'tarikh_permohonan' => $p->tarikh_permohonan->format('Y-m-d'),
            'pejabat_tanah' => $p->pejabat_tanah,
            'hak_milik_sementara_id' => $p->hak_milik_sementara_id,
            'no_hak_milik_sementara' => $p->hakMilikSementara->no_hak_milik ?? null,
            'hak_milik_baharu_id' => $p->hak_milik_baharu_id,
            'no_hak_milik_baharu' => $p->relationLoaded('hakMilikBaharu') ? $p->hakMilikBaharu?->no_hak_milik : null,
            'lot_id' => $p->lot_id,
            'peringkat_semasa' => $p->peringkat_semasa,
            'peringkat_semasa_nama' => Kod::PERINGKAT_HMK[$p->peringkat_semasa] ?? $p->peringkat_semasa,
            'status_kelulusan' => $p->status_kelulusan,
            'status_kelulusan_nama' => Kod::STATUS_HMK[$p->status_kelulusan] ?? $p->status_kelulusan,
            'tarikh_terima_hak_milik_baharu' => $p->tarikh_terima_hak_milik_baharu?->format('Y-m-d'),
            'tempoh_proses_hari' => $p->tempoh_proses_hari ?? (int) $p->tarikh_permohonan->diffInDays(today()),
        ];
    }
}
