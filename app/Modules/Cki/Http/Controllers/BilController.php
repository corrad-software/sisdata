<?php

namespace App\Modules\Cki\Http\Controllers;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Cki\Http\Present;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Konflik;
use App\Modules\Cki\Models\Lejar;
use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Models\Pelarasan;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Models\Transaksi;
use App\Modules\Cki\Services\BilService;
use App\Modules\Cki\Support\Akses;
use App\Modules\Cki\Support\Dokumen;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Wang;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** SRS-CKI-02/07/13/16/19: bills, manual fallback entry, corrections, documents, receipts and the per-lot payment timeline. */
class BilController
{
    public function __construct(private BilService $service, private DocumentStore $documents, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $q = $this->filtered($request);
        $page = $q->paginate(min(100, max(5, (int) $request->query('per_page', 20))));

        return response()->json([
            'data' => collect($page->items())->map(fn (Cukai $c) => Present::bil($c))->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function export(Request $request): Response
    {
        $rows = $this->filtered($request)->limit(10000)->get()->map(fn (Cukai $c) => [
            $c->no_bil, $c->getAttribute('no_lot'), $c->kod_daerah, $c->kod_mukim, $c->kod_agensi, Kod::JENIS_CUKAI[$c->jenis] ?? $c->jenis, $c->tahun_taksiran,
            Wang::norm($c->amaun_pokok), Wang::norm($c->amaun_denda), Wang::norm($c->amaun_dibayar), $c->tarikh_akhir?->format('d/m/Y'), $c->tarikh_bayar?->format('d/m/Y'),
            Kod::STATUS_BAYARAN[$c->status_bayaran] ?? $c->status_bayaran, Kod::SUMBER_REKOD[$c->sumber_rekod] ?? $c->sumber_rekod, $c->no_resit,
        ])->all();
        $this->audit->record('eksport', 'cki', 'cukai', null, meta: ['penapis' => $request->query(), 'bil_baris' => count($rows), 'klasifikasi' => 'dalaman']);

        return Dokumen::download(Dokumen::xlsx('Bil cukai', ['No. bil', 'Lot', 'Daerah', 'Mukim', 'Agensi', 'Jenis', 'Tahun', 'Pokok', 'Denda', 'Dibayar', 'Tarikh akhir', 'Tarikh bayar', 'Status', 'Sumber', 'No. resit'], $rows),
            'bil-cukai-'.now()->format('Ymd').'.xlsx', Dokumen::XLSX);
    }

    public function show(Request $request, Cukai $cukai): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $o = $cukai->obligasi_id ? Obligasi::query()->withoutGlobalScope('access')->with('agensi')->find($cukai->obligasi_id) : null;
        $tx = Transaksi::query()->withoutGlobalScope('access')->with('kelulusan')->where('id_bil', $cukai->id)->orderByDesc('created_at')->get();
        $noLot = DB::table('lot_tanah')->where('id', $cukai->lot_id)->value('no_lot');

        return response()->json(['data' => Present::bil($cukai, $noLot === null ? null : (string) $noLot) + [
            'obligasi' => $o ? Present::obligasi($o) : null,
            'lejar' => Lejar::query()->where('id_bil', $cukai->id)->orderBy('id')->get()->map(fn (Lejar $l) => [
                'id' => $l->id, 'tarikh' => $l->tarikh->format('Y-m-d'), 'jenis' => $l->jenis, 'debit' => Present::wang($l->debit), 'kredit' => Present::wang($l->kredit),
                'rujukan' => $l->rujukan, 'keterangan' => $l->keterangan,
            ])->all(),
            'transaksi' => $tx->map(fn (Transaksi $t) => Present::transaksi($t))->all(),
            'resit' => Resit::query()->where('id_bil', $cukai->id)->orderBy('id')->get()->map(fn (Resit $r) => Present::resit($r))->all(),
            'pelarasan' => Pelarasan::query()->where('id_bil', $cukai->id)->orderBy('id')->get()->map(fn (Pelarasan $p) => [
                'id' => $p->id, 'medan' => $p->medan, 'nilai_lama' => $p->nilai_lama, 'nilai_baharu' => $p->nilai_baharu, 'justifikasi' => $p->justifikasi, 'tarikh' => $p->getAttribute('created_at'),
            ])->all(),
            'konflik' => Konflik::query()->withoutGlobalScope('access')->where('id_bil', $cukai->id)->orderByDesc('id')->get()->map(fn (Konflik $k) => Present::konflik($k))->all(),
            'dokumen' => $this->documents->forOwner(Cukai::SUBJECT, $cukai->id, $user),
            'boleh_rekod_manual' => $o ? $this->manualAllowed($o) : true,
        ]]);
    }

    /** SRS-CKI-07: manual (fallback) bill entry — same fields as API records, bill image optional, sumber_rekod = manual. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'obligasi_id' => 'required|uuid',
            'tahun_taksiran' => 'required|integer|min:1990|max:2100',
            'no_bil' => 'required|string|max:50',
            'amaun_pokok' => 'required|numeric|min:0|max:999999999',
            'amaun_denda' => 'nullable|numeric|min:0|max:999999999',
            'tarikh_bil' => 'nullable|date',
            'tarikh_akhir' => 'required|date',
            'fail' => 'nullable|file|max:25600',
        ]);
        $o = Obligasi::query()->find($data['obligasi_id']);
        if ($o === null) {
            throw ValidationException::withMessages(['obligasi_id' => __('cki::cki.ralat.obligasi_tiada')]);
        }
        if (! $this->manualAllowed($o)) {
            throw ValidationException::withMessages(['obligasi_id' => __('cki::cki.ralat.manual_tidak_perlu')]);
        }
        $bil = $this->service->cipta($o, $data, $request->user(), 'manual', $request->file('fail'));

        return response()->json(['data' => Present::bil($bil), 'message' => __('cki::cki.mesej.bil_dicipta')], 201);
    }

    public function adjust(Request $request, Cukai $cukai): JsonResponse
    {
        $data = $request->validate([
            'medan' => ['required', Rule::in(BilService::MEDAN_PELARASAN)],
            'nilai' => 'required|string|max:255',
            'justifikasi' => 'required|string|min:10|max:2000',
        ]);
        if (in_array($data['medan'], ['amaun_pokok', 'amaun_denda'], true) && ! is_numeric($data['nilai'])) {
            throw ValidationException::withMessages(['nilai' => __('cki::cki.ralat.nilai_nombor')]);
        }
        if (in_array($data['medan'], ['tarikh_bil', 'tarikh_akhir'], true) && strtotime($data['nilai']) === false) {
            throw ValidationException::withMessages(['nilai' => __('cki::cki.ralat.nilai_tarikh')]);
        }
        $this->service->pelarasan($cukai, $data['medan'], $data['nilai'], $data['justifikasi'], $request->user());

        return response()->json(['data' => Present::bil($cukai->refresh()), 'message' => __('cki::cki.mesej.pelarasan')]);
    }

    /** SRS-CKI-16: bill image, voucher, proof or approval document attached through DMS. */
    public function document(Request $request, Cukai $cukai): JsonResponse
    {
        $data = $request->validate(['fail' => 'required|file|max:25600', 'jenis' => ['required', Rule::in(array_keys(Kod::KATEGORI_DOKUMEN))]]);
        $id = $this->service->simpanDokumen($cukai, $request->file('fail'), $data['jenis'], $request->user());
        if ($data['jenis'] === 'bil' && $cukai->id_dokumen_bil === null) {
            $cukai->forceFill(['id_dokumen_bil' => $id])->save();
        }

        return response()->json(['data' => ['id' => $id], 'message' => __('cki::cki.mesej.dokumen')], 201);
    }

    /** A receipt that cannot be matched to a bill yet (reconciliation: "resit tanpa bil"). */
    public function orphanReceipt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'no_resit' => 'required|string|max:60', 'no_bil_rujukan' => 'nullable|string|max:60', 'kod_agensi' => 'nullable|string|max:30',
            'amaun' => 'required|numeric|min:0.01', 'tarikh_resit' => 'required|date', 'kod_daerah' => 'required|string|size:4',
        ]);
        /** @var User $user */
        $user = $request->user();
        abort_unless(app(Akses::class)->meliputi($user, $data['kod_daerah']), 403);
        $r = Resit::query()->create($data + ['amaun' => Wang::norm((string) $data['amaun']), 'sumber' => 'manual', 'id_pengguna' => $user->id]);

        return response()->json(['data' => Present::resit($r)], 201);
    }

    /** SRS-CKI-19: per-lot payment timeline across years (visual + table + export). */
    public function timeline(Request $request, string $lotId, LotRegistry $lots): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $lot = $lots->find($lotId, $user);
        abort_if($lot === null, 404);

        return response()->json(['data' => ['lot' => $lot, 'pautan_profil' => $lots->profileLink($lotId), 'bil' => $this->timelineRows($lotId, $user)]]);
    }

    public function timelineExport(Request $request, string $lotId, LotRegistry $lots): Response
    {
        /** @var User $user */
        $user = $request->user();
        $lot = $lots->find($lotId, $user);
        abort_if($lot === null, 404);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'pdf';
        $head = ['Tahun', 'Jenis', 'No. bil', 'Amaun', 'Denda', 'Tarikh akhir', 'Tarikh bayar', 'Amaun dibayar', 'No. resit', 'Status'];
        $rows = array_map(fn ($b) => [(string) $b['tahun_taksiran'], $b['jenis_cukai_nama'], $b['no_bil'], $b['amaun_pokok']['nilai'], $b['amaun_denda']['nilai'],
            $b['tarikh_akhir'], $b['tarikh_bayar'] ?? '—', $b['amaun_dibayar']['nilai'], $b['no_resit'] ?? '—', $b['status_bayaran_nama']], $this->timelineRows($lotId, $user));
        $this->audit->record('eksport', 'cki', 'lot_tanah', $lotId, meta: ['laporan' => 'garis_masa', 'format' => $format, 'klasifikasi' => 'dalaman']);
        $name = 'garis-masa-cukai-'.preg_replace('/[^A-Za-z0-9]+/', '-', $lot['no_lot']).'.'.$format;
        if ($format === 'xlsx') {
            return Dokumen::download(Dokumen::xlsx('Garis masa cukai', $head, $rows), $name, Dokumen::XLSX);
        }

        return Dokumen::download(Dokumen::pdf(__('cki::cki.dokumen.garis_masa', ['lot' => $lot['no_lot']]), [['kepala' => $head, 'jadual' => array_map(fn ($r) => array_map(fn ($c) => (string) ($c ?? ''), $r), $rows)]], true), $name, 'application/pdf');
    }

    /** @return list<array<string, mixed>> */
    private function timelineRows(string $lotId, User $user): array
    {
        return app(Akses::class)->cukai($user)->where('lot_id', $lotId)->orderBy('tahun_taksiran')->orderBy('jenis')->get()
            ->map(fn (Cukai $c) => Present::bil($c))->values()->all();
    }

    private function manualAllowed(Obligasi $o): bool
    {
        if ($o->status_liputan_api === 'luar') {
            return true;
        }
        $a = Liputan::query()->where('kod_agensi', $o->kod_agensi)->first();

        return $a === null || ! $a->perkhidmatan_semakan || $a->dalamSandaran();
    }

    /** @return Builder<Cukai> */
    private function filtered(Request $request)
    {
        $today = Carbon::today()->format('Y-m-d');

        return Cukai::query()->whereNotNull('cukai.obligasi_id')->leftJoin('lot_tanah as l', 'l.id', '=', 'cukai.lot_id')->select('cukai.*', 'l.no_lot')
            ->when($request->filled('status'), fn ($w) => $request->query('status') === 'tertunggak'
                ? $w->where('cukai.status_bayaran', '<>', 'dibayar')->whereDate('cukai.tarikh_akhir', '<', $today)
                : $w->where('cukai.status_bayaran', $request->query('status')))
            ->when($request->filled('jenis_cukai'), fn ($w) => $w->where('cukai.jenis', $request->query('jenis_cukai')))
            ->when($request->filled('kod_daerah'), fn ($w) => $w->where('cukai.kod_daerah', $request->query('kod_daerah')))
            ->when($request->filled('kod_mukim'), fn ($w) => $w->where('cukai.kod_mukim', $request->query('kod_mukim')))
            ->when($request->filled('kod_agensi'), fn ($w) => $w->where('cukai.kod_agensi', $request->query('kod_agensi')))
            ->when($request->filled('tahun'), fn ($w) => $w->where('cukai.tahun_taksiran', (int) $request->query('tahun')))
            ->when($request->filled('sumber'), fn ($w) => $w->where('cukai.sumber_rekod', $request->query('sumber')))
            ->when($request->filled('lot_id'), fn ($w) => $w->where('cukai.lot_id', $request->query('lot_id')))
            ->when($request->filled('q'), function ($w) use ($request) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->query('q')).'%';
                $w->where(fn ($x) => $x->where('cukai.no_bil', 'ilike', $like)->orWhere('l.no_lot', 'ilike', $like));
            })
            ->orderByDesc('cukai.tahun_taksiran')->orderBy('cukai.tarikh_akhir')->orderBy('cukai.id');
    }
}
