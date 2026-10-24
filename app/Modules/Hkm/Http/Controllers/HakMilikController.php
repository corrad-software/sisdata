<?php

namespace App\Modules\Hkm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Hkm\Http\Present;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\HakMilikVersi;
use App\Modules\Hkm\Models\KronologiPemilikan;
use App\Modules\Hkm\Models\PermohonanHakMilikKekal;
use App\Modules\Hkm\Models\SejarahStatusTanah;
use App\Modules\Hkm\Models\SekatanKepentingan;
use App\Modules\Hkm\Models\UrusanPendaftaranPt;
use App\Modules\Hkm\Models\WarisPemilikBersama;
use App\Modules\Hkm\Services\TitleSearch;
use App\Modules\Hkm\Services\TitleService;
use App\Modules\Hkm\Support\Bendera;
use App\Modules\Hkm\Support\Eksport;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Hkm\Support\LotLookup;
use App\Modules\Hkm\Support\StatusTanah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** SRS-HKM-01/02/10/14/15/21 — title register, search, export, history. */
class HakMilikController extends Controller
{
    public function __construct(
        private TitleService $titles,
        private TitleSearch $search,
        private LotLookup $lots,
        private Present $present,
        private AuditLogger $audit,
        private StatusTanah $statuses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:100'],
            'susun' => ['nullable', Rule::in(['no_hak_milik', 'tarikh_pendaftaran', 'tarikh_tamat_tempoh', 'keluasan', 'dikemas_kini'])],
            'keluasan_min' => ['nullable', 'numeric'],
            'keluasan_max' => ['nullable', 'numeric'],
        ]);

        $sort = (string) $request->query('susun', 'no_hak_milik');
        $page = $this->search->query($request->query())
            ->with(['daftarPetiBesi', 'sekatan', 'waris'])
            ->orderBy($sort === 'dikemas_kini' ? 'updated_at' : $sort, in_array($sort, ['dikemas_kini', 'tarikh_pendaftaran'], true) ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate((int) $request->query('per_halaman', 25));

        $lots = $this->lots->summaries($page->getCollection()->pluck('lot_id')->all());

        return response()->json([
            'data' => $page->getCollection()->map(fn (HakMilik $h) => $this->present->title($h, $lots[$h->lot_id] ?? null))->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    /** SRS-HKM-14: export of the (combined-criteria) search result. */
    public function export(Request $request): Response
    {
        $request->validate(['format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])]]);
        $format = (string) $request->query('format', 'xlsx');
        $rows = $this->search->query($request->query())->with(['daftarPetiBesi', 'sekatan', 'waris'])->orderBy('kod_daerah')->orderBy('kod_mukim')->orderBy('no_hak_milik')->limit(20000)->get();
        $lots = $this->lots->summaries($rows->pluck('lot_id')->all());

        $criteria = collect($request->only(TitleSearch::FILTERS))->filter(fn ($v) => $v !== null && $v !== '')->all();
        $this->audit->record('eksport', 'hkm', 'hak_milik', null, meta: ['klasifikasi' => 'sulit', 'format' => $format, 'kriteria' => $criteria, 'bilangan' => $rows->count()]);

        $baris = $rows->map(function (HakMilik $h) use ($lots) {
            $p = $this->present->title($h, $lots[$h->lot_id] ?? null);

            return [
                $p['no_hak_milik'], $p['jenis_hak_milik_nama'], $p['no_lot'], $p['mukim'], $p['daerah'], $p['kategori_tanah_nama'],
                $p['keluasan'] !== null ? $p['keluasan'].' '.$p['unit_keluasan'] : null, $p['pemilik_berdaftar'], $p['status_semasa_nama'],
                $p['tarikh_pendaftaran'], $p['tarikh_tamat_tempoh'], implode('; ', array_map(fn ($f) => $f['label'], $p['bendera'])),
            ];
        })->all();

        $user = $request->user();

        return Eksport::download($format, 'carian-hak-milik-'.now()->format('Ymd-His'), __('hkm::hkm.eksport.tajuk_carian'), [[
            'tajuk' => __('hkm::hkm.eksport.keputusan', ['n' => count($baris)]),
            'lajur' => ['No. hak milik', 'Jenis', 'No. lot', 'Mukim', 'Daerah', 'Kategori', 'Keluasan', 'Pemilik berdaftar', 'Status', 'Tarikh daftar', 'Tarikh tamat', 'Bendera'],
            'baris' => $baris,
        ]], $this->footer($user, $criteria));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->titles->validate($request->all());
        $vault = $request->input('peti_besi');
        if ($vault !== null && ! $request->user()->can('hkm.peti_besi.cipta')) {
            abort(403, __('hkm::hkm.ralat.tiada_kebenaran_peti_besi'));
        }
        $title = $this->titles->register((string) $data['lot_id'], $data, $request->user(), is_array($vault) ? $vault : null);

        return response()->json(['data' => $this->present->title($title->refresh()), 'message' => __('hkm::hkm.mesej.didaftar')], 201);
    }

    public function show(Request $request, HakMilik $hakMilik): JsonResponse
    {
        $user = $request->user();
        $hakMilik->load(['daftarPetiBesi.petiBesi', 'daftarPetiBesi.pengeluaranTerbuka.pegawaiKeluar', 'sekatan', 'waris', 'urusan', 'digantikan', 'pengganti']);

        // SRS-GEN-02: viewing a Sulit record is an explicit audit action.
        $this->audit->record('lihat', 'hkm', 'hak_milik', $hakMilik->id, meta: ['klasifikasi' => $hakMilik->klasifikasi]);

        $lot = $this->lots->find($hakMilik->lot_id, $user);

        return response()->json([
            'data' => $this->present->title($hakMilik, $lot ? ['no_lot' => $lot['no_lot'], 'bahagian_pemilik' => $lot['bahagian_pemilik'], 'kategori_guna_tanah' => $lot['kategori_guna_tanah']] : null) + [
                'pautan_lot' => $this->lots->profileLink($hakMilik->lot_id),
                'digantikan' => $hakMilik->digantikan ? ['id' => $hakMilik->digantikan->id, 'no_hak_milik' => $hakMilik->digantikan->no_hak_milik] : null,
                'pengganti' => $hakMilik->pengganti->map(fn (HakMilik $h) => ['id' => $h->id, 'no_hak_milik' => $h->no_hak_milik])->values(),
                'pemilikan_belum_selesai' => Bendera::ownershipUnresolved($hakMilik),
                'waris' => $hakMilik->waris->sortBy('id')->map(fn (WarisPemilikBersama $w) => $this->present->heir($w, $user))->values(),
                'sekatan' => $hakMilik->sekatan->sortByDesc('tarikh_masuk')->map(fn (SekatanKepentingan $s) => [
                    ...$s->only(['id', 'jenis_sekatan', 'no_rujukan_sekatan', 'pihak_terlibat', 'keterangan', 'status_sekatan', 'id_dokumen_rujukan', 'id_kes_mahkamah']),
                    'jenis_sekatan_nama' => Kod::JENIS_SEKATAN[$s->jenis_sekatan] ?? $s->jenis_sekatan,
                    'status_sekatan_nama' => Kod::STATUS_SEKATAN[$s->status_sekatan] ?? $s->status_sekatan,
                    'tarikh_masuk' => $s->tarikh_masuk?->format('Y-m-d'),
                    'tarikh_lupus' => $s->tarikh_lupus?->format('Y-m-d'),
                ])->values(),
                'dokumen_asal' => $user->can('hkm.peti_besi.lihat') ? $hakMilik->daftarPetiBesi->map(fn ($d) => $this->present->entry($d))->values() : null,
                'urusan' => $hakMilik->urusan->sortByDesc('tarikh_perserahan')->map(fn (UrusanPendaftaranPt $u) => $this->transaction($u))->values(),
                'permohonan_hmk' => PermohonanHakMilikKekal::query()->where('hak_milik_sementara_id', $hakMilik->id)->orWhere('hak_milik_baharu_id', $hakMilik->id)
                    ->orderByDesc('tarikh_permohonan')->get(['id', 'no_permohonan', 'peringkat_semasa', 'status_kelulusan', 'tarikh_permohonan'])
                    ->map(fn (PermohonanHakMilikKekal $p) => [
                        'id' => $p->id, 'no_permohonan' => $p->no_permohonan, 'tarikh_permohonan' => $p->tarikh_permohonan->format('Y-m-d'),
                        'peringkat_semasa_nama' => Kod::PERINGKAT_HMK[$p->peringkat_semasa] ?? $p->peringkat_semasa,
                        'status_kelulusan_nama' => Kod::STATUS_HMK[$p->status_kelulusan] ?? $p->status_kelulusan,
                    ])->values(),
                'perubahan_menunggu' => $hakMilik->perubahan()->where('status', 'menunggu_kelulusan')->value('id'),
            ],
        ]);
    }

    /** SRS-HKM-09/10/19: status history, ownership chronology, versions (before/after). */
    public function history(HakMilik $hakMilik): JsonResponse
    {
        return response()->json(['data' => [
            'status' => $hakMilik->sejarahStatus()->with(['pegawai', 'pelulus'])->orderByDesc('tarikh_berkuat_kuasa')->orderByDesc('id')->get()
                ->map(fn (SejarahStatusTanah $s) => $this->statusRow($s))->values(),
            'kronologi' => $hakMilik->kronologi()->with('pegawai')->orderBy('susunan_kronologi')->get()
                ->map(fn (KronologiPemilikan $k) => $this->ownerRow($k))->values(),
            'versi' => $hakMilik->versi()->with(['pengguna', 'pelulus'])->orderByDesc('versi')->get()->map(fn (HakMilikVersi $v) => [
                'versi' => $v->versi,
                'jenis_perubahan' => $v->jenis_perubahan,
                'jenis_perubahan_nama' => Kod::JENIS_PERUBAHAN[$v->jenis_perubahan] ?? __('hkm::hkm.versi.'.$v->jenis_perubahan),
                'data_sebelum' => $v->data_sebelum,
                'data_selepas' => $v->data_selepas,
                'medan_berubah' => $v->medan_berubah,
                'justifikasi' => $v->justifikasi,
                'no_rujukan_dokumen' => $v->no_rujukan_dokumen,
                'id_perubahan' => $v->id_perubahan,
                'pengguna' => $v->pengguna?->name,
                'pelulus' => $v->pelulus?->name,
                'tarikh' => $v->created_at->toIso8601String(),
            ])->values(),
        ]]);
    }

    /** Titles of one lot (for MOD-PRO / GIS drill-down). */
    public function forLot(string $lot): JsonResponse
    {
        $titles = HakMilik::query()->where('lot_id', $lot)->with(['daftarPetiBesi', 'sekatan', 'waris'])->orderByDesc('tarikh_pendaftaran')->get();
        $summary = $this->lots->summaries([$lot]);

        return response()->json(['data' => $titles->map(fn (HakMilik $h) => $this->present->title($h, $summary[$lot] ?? null))->values()]);
    }

    /** SRS-HKM-10: ownership timeline of a lot across all its titles, earliest first. */
    public function lotChronology(string $lot): JsonResponse
    {
        $ids = HakMilik::query()->where('lot_id', $lot)->pluck('no_hak_milik', 'id');

        return response()->json(['data' => KronologiPemilikan::query()->whereIn('hak_milik_id', $ids->keys())
            ->orderByRaw('tarikh_pertukaran asc nulls first')->orderBy('created_at')->orderBy('susunan_kronologi')->get()
            ->map(fn (KronologiPemilikan $k) => $this->ownerRow($k) + ['no_hak_milik' => $ids[$k->hak_milik_id]])->values()]);
    }

    public function verify(Request $request, HakMilik $hakMilik): JsonResponse
    {
        $this->titles->verify($hakMilik, $request->user());

        return response()->json(['data' => $this->present->title($hakMilik->refresh()), 'message' => __('hkm::hkm.mesej.disahkan')]);
    }

    public function storeTransaction(Request $request, HakMilik $hakMilik): JsonResponse
    {
        $valid = $request->validate($this->transactionRules(true), [], ['no_perserahan' => 'nombor perserahan', 'tarikh_perserahan' => 'tarikh perserahan', 'pejabat_pendaftaran' => 'pejabat pendaftaran']);
        $row = $hakMilik->urusan()->create($valid + ['id_pegawai' => $request->user()->id, 'status_urusan' => $valid['status_urusan'] ?? 'diserah']);

        return response()->json(['data' => $this->transaction($row), 'message' => __('hkm::hkm.mesej.urusan_direkod')], 201);
    }

    public function updateTransaction(Request $request, UrusanPendaftaranPt $urusan): JsonResponse
    {
        abort_unless(HakMilik::query()->whereKey($urusan->hak_milik_id)->exists(), 404);
        $valid = $request->validate($this->transactionRules(false));
        if (in_array($valid['status_urusan'] ?? null, ['didaftarkan', 'ditolak', 'ditarik_balik'], true) && empty($valid['tarikh_selesai']) && $urusan->tarikh_selesai === null) {
            $valid['tarikh_selesai'] = today()->toDateString();
        }
        $urusan->fill($valid)->save();

        return response()->json(['data' => $this->transaction($urusan), 'message' => __('hkm::hkm.mesej.dikemas_kini')]);
    }

    /** @return array<string, mixed> */
    private function transactionRules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'jenis_urusan' => [$req, Rule::in(array_keys(Kod::JENIS_URUSAN))],
            'pejabat_pendaftaran' => [$req, 'string', 'max:150'],
            'no_perserahan' => [$req, 'string', 'max:50'],
            'tarikh_perserahan' => [$req, 'date_format:Y-m-d', 'before_or_equal:today'],
            'status_urusan' => ['sometimes', Rule::in(array_keys(Kod::STATUS_URUSAN))],
            'tarikh_selesai' => ['nullable', 'date_format:Y-m-d'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, mixed> */
    private function transaction(UrusanPendaftaranPt $u): array
    {
        return [
            ...$u->only(['id', 'hak_milik_id', 'jenis_urusan', 'pejabat_pendaftaran', 'no_perserahan', 'status_urusan', 'catatan', 'permohonan_hak_milik_kekal_id']),
            'jenis_urusan_nama' => Kod::JENIS_URUSAN[$u->jenis_urusan] ?? $u->jenis_urusan,
            'status_urusan_nama' => Kod::STATUS_URUSAN[$u->status_urusan] ?? $u->status_urusan,
            'tarikh_perserahan' => $u->tarikh_perserahan->format('Y-m-d'),
            'tarikh_selesai' => $u->tarikh_selesai?->format('Y-m-d'),
            'tempoh_hari' => (int) $u->tarikh_perserahan->diffInDays($u->tarikh_selesai ?? today()),
        ];
    }

    /** @return array<string, mixed> */
    private function statusRow(SejarahStatusTanah $s): array
    {
        return [
            'id' => $s->id,
            'status_sebelum' => $s->status_sebelum,
            'status_sebelum_nama' => $this->statuses->label($s->status_sebelum),
            'status_selepas' => $s->status_selepas,
            'status_selepas_nama' => $this->statuses->label($s->status_selepas),
            'tarikh_berkuat_kuasa' => $s->tarikh_berkuat_kuasa->format('Y-m-d'),
            'sebab_perubahan' => $s->sebab_perubahan,
            'id_dokumen_rujukan' => $s->id_dokumen_rujukan,
            'no_rujukan_dokumen' => $s->no_rujukan_dokumen,
            'pegawai' => $s->pegawai?->name,
            'pelulus' => $s->pelulus?->name,
            'id_perubahan' => $s->id_perubahan,
            'tarikh_masa_rekod' => $s->tarikh_masa_rekod->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function ownerRow(KronologiPemilikan $k): array
    {
        return [
            'id' => $k->id,
            'hak_milik_id' => $k->hak_milik_id,
            'susunan' => $k->susunan_kronologi,
            'pemilik_sebelum' => $k->pemilik_sebelum,
            'pemilik_selepas' => $k->pemilik_selepas,
            'tarikh_pertukaran' => $k->tarikh_pertukaran?->format('Y-m-d'),
            'sebab_pertukaran' => $k->sebab_pertukaran,
            'sumber' => $k->sumber,
            'id_pemerolehan' => $k->id_pemerolehan,
            'id_dokumen_rujukan' => $k->id_dokumen_rujukan,
            'pegawai' => $k->pegawai?->name,
        ];
    }

    /** @param array<string, mixed> $criteria */
    private function footer(User $user, array $criteria): string
    {
        $c = $criteria === [] ? __('hkm::hkm.eksport.semua') : collect($criteria)->map(fn ($v, $k) => $k.'='.(is_array($v) ? implode(',', $v) : $v))->implode('; ');

        return __('hkm::hkm.eksport.kaki', ['nama' => $user->name, 'masa' => now()->format('d/m/Y H:i'), 'kriteria' => $c]);
    }
}
