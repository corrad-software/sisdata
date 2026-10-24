<?php

namespace App\Modules\Ast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ast\Http\Present;
use App\Modules\Ast\Models\AsetAudit;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Models\AsetKondisiSejarah;
use App\Modules\Ast\Models\AsetNilaiSejarah;
use App\Modules\Ast\Models\AsetTag;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Services\AsetService;
use App\Modules\Ast\Services\LaporanService;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ast\Support\Rujukan;
use App\Modules\Ast\Support\SusutNilai;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * /api/v1/ast/aset — the register (SRS-AST-01..05, 11, 18, 23). Records are
 * scoped by the lot's daerah / mukim / klasifikasi (ScopedByAccess) so an
 * out-of-scope asset is a 404.
 */
class AsetController extends Controller
{
    public function __construct(private AsetService $service, private Present $present, private Rujukan $ref) {}

    public function rujukan(Parameters $parameters): JsonResponse
    {
        return response()->json(['data' => [
            'daerah' => Rujukan::senarai($this->ref->daerah()), 'mukim' => Rujukan::senarai($this->ref->mukim()),
            'kategori' => AsetKategori::query()->where('status_aktif', true)->orderBy('susunan')->get(['id', 'kod', 'nama_kategori', 'jenis_lalai'])
                ->map(fn ($k) => ['id' => $k->id, 'kod' => $k->kod, 'nama' => $k->nama_kategori, 'jenis_lalai' => $k->jenis_lalai])->all(),
            'jenis_aset' => Rujukan::senarai(Lalai::JENIS_ASET), 'kondisi' => Rujukan::senarai(Lalai::KONDISI), 'status_aset' => Rujukan::senarai(Lalai::STATUS_ASET),
            'unit_ukuran' => Rujukan::senarai(Lalai::UNIT_UKURAN), 'jenis_penyelenggaraan' => Rujukan::senarai(Lalai::JENIS_PENYELENGGARAAN),
            'jenis_kerja' => Rujukan::senarai(Lalai::JENIS_KERJA), 'keutamaan' => Rujukan::senarai(Lalai::KEUTAMAAN), 'status_kerja' => Rujukan::senarai(Lalai::STATUS_KERJA),
            'kaedah_penilaian' => Rujukan::senarai(Lalai::KAEDAH_PENILAIAN), 'kategori_dokumen' => Rujukan::senarai(Lalai::KATEGORI_DOKUMEN),
            'kategori_gambar' => Rujukan::senarai(Lalai::KATEGORI_GAMBAR), 'jenis_pelupusan' => Rujukan::senarai(Lalai::JENIS_PELUPUSAN),
            'kaedah_pelupusan' => Rujukan::senarai(Lalai::KAEDAH_PELUPUSAN), 'senarai_semak' => Rujukan::senarai(Lalai::SENARAI_SEMAK),
            'templat_label' => array_map(fn ($k, $v) => ['kod' => $k, 'nama' => $v[0]], array_keys(Lalai::TEMPLAT_LABEL), Lalai::TEMPLAT_LABEL),
            'tag_berstruktur' => array_values((array) $parameters->get('ast.tag_berstruktur', [])),
            'laporan' => Rujukan::senarai(LaporanService::LAPORAN),
        ]]);
    }

    public function index(Request $request, LaporanService $laporan): JsonResponse
    {
        $f = $request->only(['kod_daerah', 'kod_mukim', 'status_kondisi', 'jenis_aset', 'status_aset', 'lot_id', 'id_kategori', 'tag', 'q', 'termasuk_arkib']);
        $q = $laporan->asetQuery($request->user(), $f);
        if ($request->boolean('arkib_sahaja')) {
            $q->where('aset_fizikal.status_arkib', true);
        }
        if ($request->boolean('tanpa_pemeriksaan')) {
            $q = $laporan->tanpaPemeriksaanQuery($request->user(), $f);
        }
        $sort = in_array($request->query('susun'), ['nama_aset', 'no_rujukan_aset', 'status_kondisi', 'tarikh_pemeriksaan_terakhir', 'created_at'], true) ? (string) $request->query('susun') : 'created_at';
        $p = $q->orderBy('aset_fizikal.'.$sort, $sort === 'created_at' ? 'desc' : 'asc')->paginate(min(100, max(5, (int) $request->query('per_page', 20))));
        $lots = app(LotRegistry::class)->summaries(collect($p->items())->pluck('lot_id')->unique()->values()->all(), $request->user());

        return response()->json([
            'data' => collect($p->items())->map(fn (AsetFizikal $a) => $this->present->aset($a, $lots))->values(),
            'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()],
        ]);
    }

    public function ringkasan(Request $request, LaporanService $laporan): JsonResponse
    {
        $u = $request->user();
        $f = $request->only(['kod_daerah']);
        $base = fn () => $laporan->asetQuery($u, $f);
        $today = now()->toDateString();

        return response()->json(['data' => [
            'jumlah_aset' => $base()->count(),
            'kondisi' => $laporan->kumpul($u, $f, 'status_kondisi', fn ($k) => Lalai::KONDISI[(string) $k] ?? (string) $k),
            'kategori' => $laporan->kumpul($u, $f, 'id_kategori', fn ($k) => (string) AsetKategori::query()->whereKey((int) $k)->value('nama_kategori')),
            'jumlah_nilai' => $u->can('ast.laporan.lihat') ? SusutNilai::bundar((string) ($base()->reorder()->selectRaw('COALESCE(SUM(nilai_semasa),0)::text AS v')->value('v') ?? '0')) : null,
            'tanpa_pemeriksaan' => $laporan->tanpaPemeriksaanQuery($u, $f)->count(),
            'menunggu_kelulusan' => $base()->where('status_kelulusan', 'menunggu')->count(),
            'jadual_tertunggak' => $u->can('ast.penyelenggaraan.lihat') ? JadualPenyelenggaraan::query()->untuk($u)->where('status', 'aktif')->whereDate('tarikh_jatuh_tempoh', '<', $today)
                ->whereIn('id_aset', $base()->select('aset_fizikal.id'))->count() : null,
            'kerja_terbuka' => $u->can('ast.penyelenggaraan.lihat') ? RekodPenyelenggaraan::query()->untuk($u)->whereIn('status', Lalai::KERJA_TERBUKA)->count() : null,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $a = $this->service->daftar($this->validated($request, true), $request->user());

        return response()->json(['data' => $this->present->asetPenuh($a->load(['pegawai', 'induk', 'tag']), $request->user()), 'message' => __('ast::ast.didaftar', ['no' => $a->no_rujukan_aset])], 201);
    }

    public function show(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->with(['pegawai', 'induk', 'tag'])->findOrFail($aset);
        $lot = app(LotRegistry::class)->find($a->lot_id, $request->user());
        $sub = AsetFizikal::query()->where('id_aset_induk', $a->id)->orderBy('nama_aset')->get();
        $premis = $a->id_premis_pjk ? DB::table('premis_aset')->where('id', $a->id_premis_pjk)->first(['id', 'kod_premis', 'nama_premis', 'status_premis', 'jenis_aset']) : null;
        app(AuditLogger::class)->record('lihat', 'ast', 'aset_fizikal', $a->id, meta: ['no_rujukan_aset' => $a->no_rujukan_aset]);

        return response()->json(['data' => $this->present->asetPenuh($a, $request->user(), $lot ?? []) + [
            'subaset' => $sub->map(fn (AsetFizikal $s) => $this->present->aset($s))->values(),
            'premis_pjk' => $premis ? ['id' => (int) $premis->id, 'kod_premis' => $premis->kod_premis, 'nama_premis' => $premis->nama_premis, 'status_premis' => $premis->status_premis, 'pautan' => '/admin/pjk/premis/'.$premis->id] : null,
            'qr' => $a->qr()->orderByDesc('tarikh_jana')->get()->map(fn ($q) => ['qr_ref' => $q->qr_ref, 'pautan_awam' => $q->pautan_awam, 'status_aktif' => $q->status_aktif, 'tarikh_jana' => $q->tarikh_jana->toIso8601String(), 'bilangan_cetakan' => $q->bilangan_cetakan, 'sebab_nyahaktif' => $q->sebab_nyahaktif])->values(),
        ]]);
    }

    public function update(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $a = $this->service->kemaskini($a, $this->validated($request, false), $request->user());

        return response()->json(['data' => $this->present->asetPenuh($a->load(['pegawai', 'induk', 'tag']), $request->user()), 'message' => __('ast::ast.dikemas_kini')]);
    }

    public function kondisi(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $d = $request->validate(['status_kondisi' => ['required', Rule::in(array_keys(Lalai::KONDISI))], 'catatan' => ['nullable', 'string', 'max:1000']]);
        if ($a->status_arkib) {
            abort(422, __('ast::ast.aset_diarkib'));
        }
        $this->service->ubahKondisi($a, $d['status_kondisi'], $request->user(), $d['catatan'] ?? null);

        return response()->json(['data' => $this->present->aset($a->refresh()), 'message' => __('ast::ast.kondisi_dikemas_kini')]);
    }

    public function nilai(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $d = $request->validate([
            'nilai' => ['required', 'numeric', 'min:0', 'max:99999999999999'], 'tarikh_penilaian' => ['required', 'date', 'before_or_equal:today'],
            'kaedah_penilaian' => ['required', Rule::in(array_keys(Lalai::KAEDAH_PENILAIAN))], 'id_laporan_penilaian' => ['nullable', 'string', 'max:80'],
            'id_dokumen_dms' => ['nullable', 'string', 'max:64'], 'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        $row = $this->service->kemaskiniNilai($a, $d, $request->user());

        return response()->json(['data' => ['id' => $row->id, 'nilai' => $row->nilai, 'tahun' => $row->tahun], 'message' => __('ast::ast.nilai_dikemas_kini')], 201);
    }

    /** SRS-AST-18: value history by year + depreciation schedule. */
    public function sejarahNilai(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $k = AsetKategori::query()->find($a->id_kategori);
        $rows = AsetNilaiSejarah::query()->with('pengguna')->where('id_aset', $a->id)->orderByDesc('tarikh_penilaian')->orderByDesc('id')->get();

        return response()->json(['data' => [
            'sejarah' => $rows->map(fn (AsetNilaiSejarah $n) => [
                'id' => $n->id, 'tahun' => $n->tahun, 'nilai' => $n->nilai, 'nilai_sebelum' => $n->nilai_sebelum, 'kaedah_penilaian' => $n->kaedah_penilaian,
                'kaedah_nama' => Lalai::KAEDAH_PENILAIAN[$n->kaedah_penilaian] ?? $n->kaedah_penilaian, 'id_laporan_penilaian' => $n->id_laporan_penilaian,
                'id_dokumen_dms' => $n->id_dokumen_dms, 'tarikh_penilaian' => $n->tarikh_penilaian->toDateString(), 'tarikh_rekod' => $n->tarikh_rekod->toIso8601String(),
                'pengguna' => $n->pengguna?->name, 'catatan' => $n->catatan,
            ])->values(),
            'susut_nilai' => $a->kos_perolehan !== null && $k !== null ? SusutNilai::jadual((string) $a->kos_perolehan, (int) ($a->tahun_perolehan ?? $a->created_at->year), $k->hayat_guna_tahun, (string) $k->kadar_nilai_sisa, now()->year) : [],
        ]]);
    }

    /** SRS-AST-05/23: condition history + audit trail of value / condition / officer. */
    public function sejarah(string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);

        return response()->json(['data' => [
            'kondisi' => AsetKondisiSejarah::query()->with('pengguna')->where('id_aset', $a->id)->orderByDesc('tarikh_perubahan')->orderByDesc('id')->get()
                ->map(fn (AsetKondisiSejarah $k) => [
                    'id' => $k->id, 'status_lama' => $k->status_lama, 'status_baharu' => $k->status_baharu, 'lama_nama' => $k->status_lama ? (Lalai::KONDISI[$k->status_lama] ?? $k->status_lama) : null,
                    'baharu_nama' => Lalai::KONDISI[$k->status_baharu] ?? $k->status_baharu, 'tarikh_perubahan' => $k->tarikh_perubahan->toIso8601String(),
                    'pengguna' => $k->pengguna?->name, 'id_pemeriksaan' => $k->id_pemeriksaan, 'catatan' => $k->catatan,
                ])->values(),
            'audit' => AsetAudit::query()->with('pengguna')->where('id_aset', $a->id)->orderByDesc('cap_masa')->orderByDesc('id')->limit(200)->get()
                ->map(fn (AsetAudit $x) => [
                    'id' => $x->id, 'medan' => $x->medan_diubah, 'medan_nama' => ['nilai_semasa' => 'Nilai semasa (RM)', 'status_kondisi' => 'Status kondisi', 'id_pegawai_bertanggungjawab' => 'Pegawai bertanggungjawab'][$x->medan_diubah] ?? $x->medan_diubah,
                    'nilai_sebelum' => $x->nilai_sebelum, 'nilai_selepas' => $x->nilai_selepas, 'pengguna' => $x->pengguna?->name, 'sebab' => $x->sebab, 'cap_masa' => $x->cap_masa->toIso8601String(),
                ])->values(),
        ]]);
    }

    public function cariLot(Request $request, LotRegistry $lots): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(['data' => $q === '' ? [] : array_map(fn ($l) => $l + ['daerah' => $this->ref->namaDaerah($l['kod_daerah']), 'mukim' => $this->ref->namaMukim($l['kod_mukim'])], $lots->search($q, $request->user(), 15))]);
    }

    /** Responsible-officer picker (active users). */
    public function pegawai(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(['data' => User::query()->where('is_active', true)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'ilike', '%'.$q.'%')->orWhere('email', 'ilike', '%'.$q.'%')))
            ->orderBy('name')->limit(20)->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'nama' => $u->name])->all()]);
    }

    /** MOD-PJK premises on a lot that are not linked to an asset yet (read-only on premis_aset). */
    public function premis(Request $request, LotRegistry $lots): JsonResponse
    {
        $lotId = (string) $request->query('lot_id', '');
        if (! $lots->exists($lotId, $request->user())) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => DB::table('premis_aset')->whereNull('deleted_at')->where('lot_id', $lotId)
            ->whereNotIn('id', DB::table('aset_fizikal')->whereNotNull('id_premis_pjk')->when($request->query('kecuali'), fn ($q, $id) => $q->where('id', '!=', $id))->select('id_premis_pjk'))
            ->orderBy('nama_premis')->get(['id', 'kod_premis', 'nama_premis', 'jenis_aset', 'status_premis', 'keluasan'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'kod_premis' => $p->kod_premis, 'nama_premis' => $p->nama_premis, 'jenis_aset' => $p->jenis_aset, 'status_premis' => $p->status_premis, 'keluasan' => $p->keluasan])->all()]);
    }

    /** SRS-AST-11 tag cloud for filters. */
    public function tag(Request $request): JsonResponse
    {
        return response()->json(['data' => AsetTag::query()->whereIn('id_aset', AsetFizikal::query()->select('id'))
            ->groupBy('nama_tag', 'jenis_tag')->selectRaw('nama_tag, jenis_tag, COUNT(*) AS n')->orderByDesc('n')->limit(100)->get()
            ->map(fn ($t) => ['nama' => $t->nama_tag, 'jenis' => $t->jenis_tag, 'bilangan' => (int) $t->getAttribute('n')])->all()]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return $request->validate([
            'lot_id' => [$create ? 'required' : 'prohibited', 'uuid'],
            'id_kategori' => [$req, 'integer'],
            'jenis_aset' => ['sometimes', Rule::in(array_keys(Lalai::JENIS_ASET))],
            'nama_aset' => [$req, 'string', 'max:200'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
            'tahun_pembinaan' => ['nullable', 'integer', 'between:1800,'.(now()->year + 5)],
            'tahun_perolehan' => ['nullable', 'integer', 'between:1800,'.now()->year],
            'tarikh_perolehan' => ['nullable', 'date', 'before_or_equal:today'],
            'saiz_keluasan' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'unit_ukuran' => ['nullable', Rule::in(array_keys(Lalai::UNIT_UKURAN))],
            'kos_perolehan' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'nilai_semasa' => [$create ? 'nullable' : 'prohibited', 'numeric', 'min:0', 'max:99999999999999'],
            'kaedah_penilaian' => ['nullable', Rule::in(array_keys(Lalai::KAEDAH_PENILAIAN))],
            'id_laporan_penilaian' => ['nullable', 'string', 'max:80'],
            'status_kondisi' => [$create ? 'nullable' : 'prohibited', Rule::in(array_keys(Lalai::KONDISI))],
            'status_aset' => [$create ? 'prohibited' : 'sometimes', Rule::in(['aktif', 'dalam_penyelenggaraan', 'tidak_aktif'])],
            'id_pegawai_bertanggungjawab' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'id_aset_induk' => ['nullable', 'uuid'],
            'id_premis_pjk' => ['nullable', 'integer'],
            'tag' => ['sometimes', 'array', 'max:30'],
            'tag.*' => ['string', 'max:60'],
            'sebab' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
