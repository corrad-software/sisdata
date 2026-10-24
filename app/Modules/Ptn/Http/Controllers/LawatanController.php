<?php

namespace App\Modules\Ptn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Ptn\Http\Present;
use App\Modules\Ptn\Models\GambarLawatan;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\LaporanLawatan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Services\LaporanService;
use App\Modules\Ptn\Services\LawatanService;
use App\Modules\Ptn\Support\Akses;
use App\Modules\Ptn\Support\Geometri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Visit records for supervisors / district officers (scoped), report, comparison, photo review. */
class LawatanController extends Controller
{
    public function __construct(private Present $present, private Akses $akses, private LotRegistry $lots) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'kod_daerah' => ['nullable', 'string'], 'status' => ['nullable', 'string'], 'lot_id' => ['nullable', 'uuid'], 'q' => ['nullable', 'string', 'max:50'],
            'perlu_semakan' => ['nullable', 'boolean'], 'dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date'], 'per_page' => ['nullable', 'integer', 'max:100'],
        ]);
        $user = $request->user();
        $q = RekodLawatan::query()->denganKoordinat()->with('pegawai')
            ->when(! $this->akses->lihatSemua($user), fn ($q) => $q->where('id_pegawai', $user->id))
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status_lawatan', $v))
            ->when($f['lot_id'] ?? null, fn ($q, $v) => $q->where('lot_id', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('no_rujukan', 'ilike', '%'.$v.'%'))
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->where('tarikh_lawatan', '>=', $v))
            ->when($f['hingga'] ?? null, fn ($q, $v) => $q->where('tarikh_lawatan', '<=', $v))
            ->when(! empty($f['perlu_semakan']), fn ($q) => $q->whereExists(fn ($s) => $s->from('gambar_lawatan')->whereColumn('gambar_lawatan.id_lawatan', 'rekod_lawatan.id')->where('perlu_semakan', true)->whereNull('keputusan_semakan')))
            ->orderByDesc('tarikh_lawatan')->orderByDesc('created_at');
        $page = $q->paginate((int) ($f['per_page'] ?? 25));
        $lots = $this->lots->summaries($page->getCollection()->pluck('lot_id')->unique()->values()->all(), $user);

        return response()->json([
            'data' => $page->getCollection()->map(fn (RekodLawatan $l) => $this->present->lawatan($l, $lots[$l->lot_id]['no_lot'] ?? null))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    private function cari(Request $request, string $id): RekodLawatan
    {
        $l = RekodLawatan::query()->denganKoordinat()->with(['pegawai', 'pengesah'])->find($id);
        if ($l === null || ! $this->akses->bolehLihatLawatan($request->user(), $l)) {
            abort(404);
        }

        return $l;
    }

    public function show(Request $request, string $lawatan, Geometri $geo): JsonResponse
    {
        $l = $this->cari($request, $lawatan);
        $lot = $this->lots->find($l->lot_id, $request->user());
        $user = $request->user();

        return response()->json(['data' => $this->present->lawatan($l, $lot['no_lot'] ?? null) + [
            'lot' => $lot ? $lot + ['pautan' => $this->lots->profileLink($l->lot_id), 'geometri' => $geo->geojson($l->lot_id)] : null,
            'templat' => $l->templat ? ['id' => $l->templat->id, 'nama' => $l->templat->nama, 'versi' => $l->versi_templat, 'senarai_item' => $l->templat->senarai_item] : null,
            'item' => $l->item->map(fn ($i) => ['kod_item' => $i->kod_item, 'kategori_item' => $i->kategori_item, 'label' => $i->label, 'status_item' => $i->status_item, 'catatan' => $i->catatan, 'kategori_isu' => $i->kategori_isu, 'tahap_keterukan' => $i->tahap_keterukan])->values(),
            'gambar' => GambarLawatan::query()->denganKoordinat()->where('id_lawatan', $l->id)->orderBy('id')->get()->map(fn ($g) => $this->present->gambar($g))->values(),
            'isu' => IsuPemantauan::query()->where('id_lawatan', $l->id)->get()->map(fn ($i) => $this->present->isu($i))->values(),
            'laporan' => LaporanLawatan::query()->where('id_lawatan', $l->id)->with('pengesah')->orderBy('tarikh_jana')->get()->map(fn ($r) => $this->present->laporan($r))->values(),
            'cadangan' => $l->cadangan->map(fn ($c) => ['id' => $c->id, 'medan' => $c->medan, 'nilai_semasa' => $c->nilai_semasa, 'nilai_dicadang' => $c->nilai_dicadang, 'justifikasi' => $c->justifikasi, 'status' => $c->status, 'ulasan_keputusan' => $c->ulasan_keputusan])->values(),
            'penyelenggaraan' => $l->penyelenggaraan->map(fn ($k) => ['id' => $k->id, 'jenis' => $k->jenis, 'keterangan' => $k->keterangan, 'keutamaan' => $k->keutamaan, 'status' => $k->status, 'tarikh_jadual' => $k->tarikh_jadual?->toDateString()])->values(),
            'boleh' => [
                'semak' => $this->akses->penyelia($user, $l->pegawai) && ! $l->dikunci(),
                'konflik' => $l->status_lawatan === 'konflik' && $this->akses->penyelia($user, $l->pegawai),
                'tambahan' => $user->can('ptn.lawatan.kemas_kini') && $l->dikunci(),
                'ubah' => $this->akses->bolehUbahLawatan($user, $l) && $l->bolehDiubah(),
            ],
        ]]);
    }

    /** SRS-PTN-20: current visit next to the previous visit of the same lot. */
    public function perbandingan(Request $request, string $lawatan): JsonResponse
    {
        $l = $this->cari($request, $lawatan);
        $prev = RekodLawatan::query()->where('lot_id', $l->lot_id)->where('id', '!=', $l->id)->whereIn('status_lawatan', ['dihantar', 'disahkan'])
            ->where('tarikh_lawatan', '<=', $l->tarikh_lawatan)->orderByDesc('tarikh_lawatan')->orderByDesc('created_at')->first();
        if ($request->filled('banding') && is_string($request->query('banding'))) {
            $prev = RekodLawatan::query()->where('lot_id', $l->lot_id)->whereKey($request->query('banding'))->first() ?? $prev;
        }
        $items = fn (?RekodLawatan $v) => $v ? $v->item()->get()->keyBy('kod_item') : collect();
        $photos = fn (?RekodLawatan $v) => $v ? GambarLawatan::query()->denganKoordinat()->where('id_lawatan', $v->id)->orderBy('id')->get() : collect();
        $now = $items($l);
        $before = $items($prev);
        $rows = [];
        foreach ($now->keys()->merge($before->keys())->unique()->values() as $kod) {
            $a = $before->get($kod);
            $b = $now->get($kod);
            $rows[] = ['kod_item' => $kod, 'label' => $b->label ?? $a->label ?? $kod, 'sebelum' => $a?->status_item, 'catatan_sebelum' => $a?->catatan, 'semasa' => $b?->status_item, 'catatan_semasa' => $b?->catatan,
                'perubahan' => match (true) {
                    $a === null || $b === null => 'baharu',
                    $a->status_item === $b->status_item => 'sama',
                    ['baik' => 0, 'isu' => 1, 'kritikal' => 2][$b->status_item] > ['baik' => 0, 'isu' => 1, 'kritikal' => 2][$a->status_item] => 'merosot',
                    default => 'bertambah_baik',
                }];
        }
        $p = fn ($v) => $v ? ['id' => $v->id, 'no_rujukan' => $v->no_rujukan, 'tarikh_lawatan' => $v->tarikh_lawatan->toDateString(), 'keadaan_tapak' => $v->keadaan_tapak, 'pegawai' => $v->pegawai?->name] : null;
        $sejarah = RekodLawatan::query()->where('lot_id', $l->lot_id)->whereIn('status_lawatan', ['dihantar', 'disahkan'])->orderByDesc('tarikh_lawatan')->limit(20)->get(['id', 'no_rujukan', 'tarikh_lawatan'])
            ->map(fn ($v) => ['id' => $v->id, 'no_rujukan' => $v->no_rujukan, 'tarikh_lawatan' => $v->tarikh_lawatan->toDateString()]);

        return response()->json(['data' => [
            'semasa' => $p($l), 'sebelum' => $p($prev), 'item' => $rows, 'sejarah' => $sejarah,
            'gambar_semasa' => $photos($l)->map(fn ($g) => $this->present->gambar($g))->values(),
            'gambar_sebelum' => $photos($prev)->map(fn ($g) => $this->present->gambar($g))->values(),
        ]]);
    }

    public function urlGambar(Request $request, string $lawatan, int $gambar, DocumentStore $documents): JsonResponse
    {
        $l = $this->cari($request, $lawatan);
        $g = GambarLawatan::query()->where('id_lawatan', $l->id)->findOrFail($gambar);
        $url = $g->laluan_simpanan ? $documents->temporaryUrl($g->laluan_simpanan, $request->user()) : null;

        return response()->json(['data' => ['url' => $url, 'imej_kecil' => $g->imej_kecil]]);
    }

    /** SRS-PTN-07: supervisor accepts / rejects a photo flagged outside the lot (or without location). */
    public function semakGambar(Request $request, string $lawatan, int $gambar, LawatanService $service): JsonResponse
    {
        $l = $this->cari($request, $lawatan);
        if (! $this->akses->penyelia($request->user(), $l->pegawai) || $l->id_pegawai === $request->user()->id) {
            abort(403);
        }
        $d = $request->validate(['keputusan' => ['required', Rule::in(['diterima', 'ditolak'])], 'catatan' => ['nullable', 'string', 'max:1000']]);
        $g = GambarLawatan::query()->where('id_lawatan', $l->id)->findOrFail($gambar);
        if ($l->dikunci()) {
            throw ValidationException::withMessages(['lawatan' => __('ptn::ptn.lawatan_dikunci')]);
        }
        $service->semakGambar($g, $d['keputusan'], $d['catatan'] ?? null, $request->user());

        return response()->json(['data' => $this->present->gambar($g->refresh())]);
    }

    public function konflik(Request $request, string $lawatan, LawatanService $service): JsonResponse
    {
        $l = $this->cari($request, $lawatan);
        if (! $this->akses->penyelia($request->user(), $l->pegawai)) {
            abort(403);
        }
        $d = $request->validate(['keputusan' => ['required', Rule::in(['terima', 'tolak'])], 'catatan' => ['nullable', 'string', 'max:1000']]);
        $service->selesaikanKonflik($l, $d['keputusan'], $d['catatan'] ?? null, $request->user());

        return response()->json(['data' => $this->present->lawatan($l->refresh())]);
    }

    private function laporanBoleh(Request $request, string $laporan): LaporanLawatan
    {
        $r = LaporanLawatan::query()->with('pengesah')->findOrFail($laporan);
        $this->cari($request, $r->id_lawatan);

        return $r;
    }

    /** SRS-PTN-09: the standard PDF (DMS copy URL once validated; DRAF rendering before). */
    public function pdf(Request $request, string $laporan, LaporanService $service, AuditLogger $audit): Response|JsonResponse
    {
        $r = $this->laporanBoleh($request, $laporan);
        if ($r->status_kunci && $r->id_dokumen_dms && ($url = $service->muatTurunUrl($r, $request->user()))) {
            return response()->json(['data' => ['url' => $url]]);
        }
        $audit->record('muat_turun', 'ptn', 'laporan_lawatan', $r->id, meta: ['klasifikasi' => $r->klasifikasi, 'draf' => ! $r->status_kunci]);

        return new Response($service->pdf($r), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="laporan-'.($r->kandungan['no_rujukan'] ?? $r->id).'.pdf"', 'Cache-Control' => 'no-store']);
    }

    /** SRS-PTN-10: addendum to a locked report. */
    public function tambahan(Request $request, string $laporan, LaporanService $service): JsonResponse
    {
        $r = $this->laporanBoleh($request, $laporan);
        $d = $request->validate(['catatan' => ['required', 'string', 'min:10', 'max:5000']]);
        $new = $service->tambahan($r, $d['catatan'], $request->user());

        return response()->json(['data' => $this->present->laporan($new)], 201);
    }

    public function mulaPengesahan(Request $request, string $laporan, LaporanService $service): JsonResponse
    {
        $r = $this->laporanBoleh($request, $laporan);
        $service->mulaPengesahan($r, $request->user());
        if ($r->refresh()->id_aliran === null) {
            throw ValidationException::withMessages(['aliran' => __('ptn::ptn.aliran_tiada')]);
        }

        return response()->json(['data' => $this->present->laporan($r)]);
    }
}
