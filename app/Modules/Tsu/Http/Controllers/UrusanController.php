<?php

namespace App\Modules\Tsu\Http\Controllers;

use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Tsu\Http\Present;
use App\Modules\Tsu\Models\KemaskiniStatusPtd;
use App\Modules\Tsu\Models\LotSejarahPecahan;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanLot;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Services\DokumenService;
use App\Modules\Tsu\Services\KosService;
use App\Modules\Tsu\Services\PemindahanService;
use App\Modules\Tsu\Services\PtdService;
use App\Modules\Tsu\Services\UrusanService;
use App\Modules\Tsu\Support\AliranTsu;
use App\Modules\Tsu\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** SRS-TSU-01..07, 22..25: urusan register, stage timeline, documents, Land Office status, record transfer. */
class UrusanController extends TsuController
{
    public function __construct(private UrusanService $service, private Present $present, private LotRegistry $lots) {}

    public function rujukan(PtdService $ptd): JsonResponse
    {
        return response()->json(['data' => [
            'jenis_urusan' => Lalai::senarai(Lalai::JENIS_URUSAN), 'pihak' => Lalai::senarai(Lalai::PIHAK), 'kategori_kos' => Lalai::senarai(Lalai::KATEGORI_KOS),
            'kategori_dokumen' => Lalai::senarai(Lalai::KATEGORI_DOKUMEN), 'status_urusan' => Lalai::senarai(Lalai::STATUS_URUSAN),
            'status_peringkat' => Lalai::senarai(Lalai::STATUS_PERINGKAT), 'status_bayaran' => Lalai::senarai(Lalai::STATUS_BAYARAN),
            'status_ptd' => Lalai::senarai(Lalai::STATUS_PTD), 'kaedah_pelupusan' => Lalai::senarai(Lalai::KAEDAH_PELUPUSAN),
            'status_sebutharga' => Lalai::senarai(Lalai::STATUS_SEBUTHARGA), 'sistem_koordinat' => [
                ['kod' => '4326', 'nama' => 'WGS84 (EPSG:4326)'], ['kod' => '4742', 'nama' => 'GDM2000 geografi (EPSG:4742)'],
                ['kod' => '3375', 'nama' => 'GDM2000 / Peninsula RSO (EPSG:3375)'], ['kod' => '3380', 'nama' => 'GDM2000 / Selangor Cassini (EPSG:3380)'],
                ['kod' => '3168', 'nama' => 'Kertau (RSO) / RSO Malaya (EPSG:3168)'], ['kod' => '4245', 'nama' => 'Kertau 1968 (EPSG:4245)'],
            ],
            'kesan' => Lalai::senarai([
                'perlu_lantikan' => 'Perlu lantikan jurukur', 'perlu_hasil_ukur_diterima' => 'Perlu hasil ukur diterima', 'perlu_rujukan_etanah' => 'Perlu no. rujukan e-Tanah',
                'perlu_rujukan_ptd' => 'Perlu no. rujukan PTD', 'perlu_kos_dibayar' => 'Semua kos peringkat dibayar', 'perlu_penilaian' => 'Perlu laporan penilaian terkini',
                'perlu_pemindahan_selesai' => 'Pemindahan rekod selesai', 'kemas_kini_syarat' => 'Kemas kini syarat lot', 'cipta_lot_baharu' => 'Cipta lot baharu',
                'arkib_lot_dilupus' => 'Arkib lot dilupuskan', 'cadang_geometri' => 'Cadang geometri kepada MOD-PRO',
            ]),
            'aliran' => [AliranTsu::URUSAN, AliranTsu::PELUPUSAN],
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'jenis_urusan' => ['nullable', Rule::in(array_keys(Lalai::JENIS_URUSAN))], 'status' => ['nullable', 'string', 'max:30'], 'kod_daerah' => ['nullable', 'string', 'size:4'],
            'q' => ['nullable', 'string', 'max:60'], 'terbuka' => ['nullable', 'boolean'], 'lewat' => ['nullable', 'boolean'], 'saya' => ['nullable', 'boolean'],
            'lot_id' => ['nullable', 'uuid'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $q = UrusanTanah::query()->with('peringkat')
            ->when($f['jenis_urusan'] ?? null, fn ($q, $v) => $q->where('jenis_urusan', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v))
            ->when(! empty($f['terbuka']), fn ($q) => $q->whereIn('status', Lalai::URUSAN_TERBUKA))
            ->when(! empty($f['saya']), fn ($q) => $q->where('id_pegawai_bertanggungjawab', $request->user()?->id))
            ->when($f['lot_id'] ?? null, fn ($q, $v) => $q->whereHas('lot', fn ($l) => $l->where('lot_id', $v)))
            ->when(! empty($f['lewat']), fn ($q) => $q->whereIn('status', Lalai::URUSAN_TERBUKA)->whereExists(fn ($s) => $s->from('urusan_peringkat')->whereColumn('urusan_peringkat.urusan_id', 'urusan_tanah.id')->whereIn('urusan_peringkat.status', ['aktif', 'menunggu_kelulusan'])->where('urusan_peringkat.tarikh_sasaran', '<', today())))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('no_rujukan_mais', 'ilike', '%'.$v.'%')->orWhere('no_rujukan_ptd', 'ilike', '%'.$v.'%')->orWhere('tajuk', 'ilike', '%'.$v.'%')))
            ->orderByDesc('created_at');
        $page = $q->paginate((int) ($f['per_page'] ?? 25));
        $user = $this->user($request);
        $first = UrusanLot::query()->whereIn('urusan_id', $page->getCollection()->pluck('id'))->orderBy('id')->get()->groupBy('urusan_id');
        $sum = $this->lots->summaries($first->flatten()->pluck('lot_id')->unique()->values()->all(), $user);

        return response()->json([
            'data' => $page->getCollection()->map(fn (UrusanTanah $u) => $this->present->urusan($u, $user, collect($first[$u->id] ?? [])->map(fn ($l) => $sum[$l->lot_id]['no_lot'] ?? null)->filter()->implode(', ') ?: null))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis_urusan' => ['required', Rule::in(array_keys(Lalai::JENIS_URUSAN))],
            'lot_ids' => ['required', 'array', 'min:1', 'max:20'], 'lot_ids.*' => ['uuid'],
            'tajuk' => ['nullable', 'string', 'max:255'], 'keterangan' => ['nullable', 'string', 'max:5000'],
            'tarikh_mula' => ['nullable', 'date_format:Y-m-d'], 'pejabat_tanah' => ['nullable', 'string', 'max:150'], 'no_rujukan_etanah' => ['nullable', 'string', 'max:60'],
            'kaedah_pelupusan' => ['nullable', Rule::in(array_keys(Lalai::KAEDAH_PELUPUSAN))], 'justifikasi' => ['nullable', 'string', 'max:5000'],
            'butiran' => ['nullable', 'array'], 'butiran.kategori_guna_tanah_baharu' => ['nullable', 'string', 'max:30'], 'butiran.syarat_nyata_baharu' => ['nullable', 'string', 'max:2000'],
            'butiran.zoning_baharu' => ['nullable', 'string', 'max:50'], 'butiran.pihak_penerima' => ['nullable', 'string', 'max:255'], 'butiran.bilangan_lot_dicadang' => ['nullable', 'integer', 'min:1', 'max:500'],
            'butiran.no_hak_milik_baharu' => ['nullable', 'string', 'max:50'],
            'id_pegawai_bertanggungjawab' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $u = $this->service->create($data, $this->user($request));

        return response()->json(['message' => __('tsu::tsu.urusan.didaftar', ['no' => $u->no_rujukan_mais]), 'data' => $this->present->urusan($u->load('peringkat'), $this->user($request))], 201);
    }

    public function show(Request $request, string $id, PtdService $ptd, KosService $kos): JsonResponse
    {
        $user = $this->user($request);
        $u = $this->urusan($id)->load(['peringkat', 'dokumen', 'keputusan']);
        $this->service->auditView($u);
        $links = UrusanLot::query()->where('urusan_id', $u->id)->orderBy('id')->get();
        $sum = $this->lots->summaries($links->pluck('lot_id')->all(), $user);
        $lotIds = $links->pluck('lot_id')->all();

        return response()->json(['data' => [
            ...$this->present->urusan($u, $user),
            'keterangan' => $u->keterangan, 'butiran' => $u->butiran, 'pejabat_tanah' => $u->pejabat_tanah, 'kaedah_pelupusan' => $u->kaedah_pelupusan,
            'kaedah_pelupusan_nama' => $u->kaedah_pelupusan ? (Lalai::KAEDAH_PELUPUSAN[$u->kaedah_pelupusan] ?? $u->kaedah_pelupusan) : null,
            'justifikasi' => $u->justifikasi, 'tarikh_tutup' => $u->tarikh_tutup?->toIso8601String(),
            'nilai_penilaian' => $user->can('tsu.rahsia.lihat') ? ($u->nilai_penilaian !== null ? (string) $u->nilai_penilaian : null) : null,
            'nilai_penilaian_tersembunyi' => $u->nilai_penilaian !== null && ! $user->can('tsu.rahsia.lihat'),
            'penilaian_sah' => $this->service->latestValuation($u) !== null,
            'templat' => ['id' => $u->templat_id, 'versi' => $u->templat->versi, 'nama' => $u->templat->nama],
            'lot' => $links->map(fn (UrusanLot $l) => [
                'id' => $l->lot_id, 'peranan_lot' => $l->peranan_lot, 'peranan_nama' => Lalai::PERANAN_LOT[$l->peranan_lot] ?? $l->peranan_lot,
                'no_lot' => $sum[$l->lot_id]['no_lot'] ?? null, 'kod_mukim' => $sum[$l->lot_id]['kod_mukim'] ?? null, 'keluasan' => $sum[$l->lot_id]['keluasan'] ?? null,
                'unit_keluasan' => $sum[$l->lot_id]['unit_keluasan'] ?? null, 'kategori_guna_tanah' => $sum[$l->lot_id]['kategori_guna_tanah'] ?? null,
                'status_lot' => $sum[$l->lot_id]['status_lot'] ?? null, 'pautan' => $this->lots->profileLink($l->lot_id),
            ])->values(),
            'peringkat' => $u->peringkat->map(fn (UrusanPeringkat $p) => $this->present->peringkat($p, (int) $u->peringkat_semasa))->values(),
            'dokumen_belum_lengkap' => $u->terbuka() ? $this->service->missingDocuments($u, $this->service->current($u)) : [],
            'dokumen' => $u->dokumen->sortByDesc('id')->map(fn (UrusanDokumen $d) => $this->present->dokumen($d))->values(),
            'keputusan' => $u->keputusan->map(fn ($k) => $this->present->keputusan($k))->values(),
            'kos' => $user->can('tsu.kos.lihat') ? $kos->summary($u) : null,
            'ptd' => [
                'liputan_api' => $ptd->liputanApi($u), 'sumber' => $u->sumber_status_ptd, 'tarikh_status' => $u->tarikh_status_ptd?->toIso8601String(),
                'tarikh_susulan' => $u->tarikh_susulan_ptd?->toDateString(),
                'sejarah' => KemaskiniStatusPtd::query()->where('urusan_id', $u->id)->orderByDesc('id')->get()->map(fn ($s) => $this->present->ptd($s))->values(),
            ],
            'sejarah_pecahan' => LotSejarahPecahan::query()->where('urusan_id', $u->id)->orWhereIn('id_lot_asal', $lotIds)->orWhereIn('id_lot_baharu', $lotIds)->orderBy('id')->get()
                ->map(fn ($s) => ['id_lot_asal' => $s->id_lot_asal, 'id_lot_baharu' => $s->id_lot_baharu, 'jenis_perubahan' => $s->jenis_perubahan, 'tarikh' => $s->tarikh_berkuat_kuasa->toDateString(), 'urusan_id' => $s->urusan_id])->values(),
        ]]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $u = $this->urusan($id);
        $this->service->assertOpen($u);
        $data = $request->validate([
            'tajuk' => ['sometimes', 'string', 'max:255'], 'keterangan' => ['sometimes', 'nullable', 'string', 'max:5000'], 'pejabat_tanah' => ['sometimes', 'nullable', 'string', 'max:150'],
            'no_rujukan_etanah' => ['sometimes', 'nullable', 'string', 'max:60'], 'no_rujukan_ptd' => ['sometimes', 'nullable', 'string', 'max:60'],
            'justifikasi' => ['sometimes', 'nullable', 'string', 'max:5000'], 'butiran' => ['sometimes', 'array'],
            'kaedah_pelupusan' => ['sometimes', Rule::in(array_keys(Lalai::KAEDAH_PELUPUSAN))], 'id_pegawai_bertanggungjawab' => ['sometimes', 'integer', 'exists:users,id'],
        ]);
        if ($u->status === 'menunggu_kelulusan' && array_intersect_key($data, array_flip(['justifikasi', 'butiran', 'kaedah_pelupusan'])) !== []) {
            throw ValidationException::withMessages(['status' => [__('tsu::tsu.urusan.aliran_berjalan')]]);
        }
        if (isset($data['butiran'])) {
            $data['butiran'] = array_merge($u->butiran, array_intersect_key($data['butiran'], array_flip(['kategori_guna_tanah_baharu', 'syarat_nyata_baharu', 'zoning_baharu', 'pihak_penerima', 'bilangan_lot_dicadang', 'no_hak_milik_baharu', 'no_hak_milik_sementara'])));
        }
        $u->forceFill($data)->save();

        return response()->json(['message' => __('tsu::tsu.disimpan'), 'data' => $this->present->urusan($u->load('peringkat'), $this->user($request))]);
    }

    public function selesaiPeringkat(Request $request, string $id): JsonResponse
    {
        $u = $this->urusan($id)->load('templat');
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']]);
        $stage = $this->service->completeStage($u, $this->user($request), $data['catatan'] ?? null);
        $msg = $stage->status === 'menunggu_kelulusan' ? __('tsu::tsu.peringkat.dihantar_kelulusan') : __('tsu::tsu.peringkat.selesai');

        return response()->json(['message' => $msg, 'data' => ['status' => $stage->status, 'id_aliran_kerja' => $stage->id_aliran_kerja]]);
    }

    public function tutup(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']]);
        $u = $this->service->close($this->urusan($id), $this->user($request), $data['catatan'] ?? null);

        return response()->json(['message' => __('tsu::tsu.urusan.ditutup'), 'data' => $this->present->urusan($u, $this->user($request))]);
    }

    public function batal(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['sebab' => ['required', 'string', 'max:2000']]);
        $u = $this->service->cancel($this->urusan($id), $this->user($request), $data['sebab']);

        return response()->json(['message' => __('tsu::tsu.urusan.dibatalkan'), 'data' => $this->present->urusan($u, $this->user($request))]);
    }

    public function muatNaik(Request $request, string $id, DokumenService $docs): JsonResponse
    {
        $u = $this->urusan($id);
        $data = $request->validate([
            'fail' => ['required', 'file', 'max:51200'], 'kategori_dokumen' => ['required', Rule::in(array_keys(Lalai::KATEGORI_DOKUMEN))],
            'peringkat_id' => ['nullable', 'integer'], 'tarikh_dokumen' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'tarikh_luput' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tarikh_dokumen'], 'nilai_penilaian' => ['nullable', 'regex:/^\d{1,14}(\.\d{1,2})?$/'],
        ]);
        try {
            $d = $docs->upload($u, $request->file('fail'), $data, $this->user($request));
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => [$e->getMessage()]]);
        }

        return response()->json(['message' => __('tsu::tsu.dokumen.dimuat_naik'), 'data' => $this->present->dokumen($d)], 201);
    }

    public function urlDokumen(Request $request, string $id, int $dokumen, DocumentStore $store): JsonResponse
    {
        $u = $this->urusan($id);
        $d = UrusanDokumen::query()->where('urusan_id', $u->id)->findOrFail($dokumen);
        $url = $store->temporaryUrl($d->id_dokumen_dms, $this->user($request));
        if ($url === null) {
            throw new HttpException(403, __('tsu::tsu.dokumen.tiada_capaian'));
        }

        return response()->json(['data' => ['url' => $url]]);
    }

    // ---- Land Office status (SRS-TSU-22) ----------------------------------------------

    public function ptdHantar(Request $request, string $id, PtdService $ptd): JsonResponse
    {
        $ref = $ptd->submit($this->urusan($id), $this->user($request));

        return response()->json(['message' => __('tsu::tsu.ptd.dihantar', ['no' => $ref]), 'data' => ['no_rujukan_ptd' => $ref]]);
    }

    public function ptdSegerak(Request $request, string $id, PtdService $ptd): JsonResponse
    {
        $u = $this->urusan($id);
        if (! $ptd->liputanApi($u)) {
            throw ValidationException::withMessages(['status_ptd' => [__('tsu::tsu.ptd.luar_liputan')]]);
        }
        $changed = $ptd->sync($u);

        return response()->json(['message' => $changed ? __('tsu::tsu.ptd.dikemas_kini') : __('tsu::tsu.ptd.tiada_perubahan'), 'data' => ['berubah' => $changed, 'status_ptd' => $u->refresh()->status_ptd]]);
    }

    public function ptdManual(Request $request, string $id, PtdService $ptd): JsonResponse
    {
        $data = $request->validate([
            'status_ptd' => ['required', Rule::in(array_keys(Lalai::STATUS_PTD))], 'tarikh_status' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:2000'], 'rujukan_ptd' => ['nullable', 'string', 'max:60'], 'tarikh_susulan' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ]);
        $row = $ptd->manual($this->urusan($id), $data, $this->user($request));

        return response()->json(['message' => __('tsu::tsu.ptd.dikemas_kini'), 'data' => $this->present->ptd($row)], 201);
    }

    // ---- Record transfer (SRS-TSU-20) ----------------------------------------------------

    public function pemindahan(Request $request, string $id, PemindahanService $svc): JsonResponse
    {
        return response()->json(['data' => $svc->screen($this->urusan($id), $this->user($request))]);
    }

    public function petakan(Request $request, string $id, PemindahanService $svc): JsonResponse
    {
        $data = $request->validate([
            'pemetaan' => ['required', 'array', 'min:1', 'max:500'], 'pemetaan.*.jenis_rekod' => ['required', 'string', 'max:40'], 'pemetaan.*.id_rekod' => ['required', 'string', 'max:64'],
            'pemetaan.*.id_lot_asal' => ['required', 'uuid'], 'pemetaan.*.id_lot_baharu' => ['required', 'uuid'],
        ]);
        $n = $svc->map($this->urusan($id), $data['pemetaan'], $this->user($request));

        return response()->json(['message' => __('tsu::tsu.pemindahan.disimpan', ['n' => $n])]);
    }

    public function hantarPemindahan(Request $request, string $id, PemindahanService $svc): JsonResponse
    {
        $instance = $svc->submit($this->urusan($id), $this->user($request));

        return response()->json(['message' => __('tsu::tsu.pemindahan.dihantar'), 'data' => ['id_aliran_kerja' => $instance]]);
    }

    // ---- Lot picker / lot view ----------------------------------------------------------

    public function cariLot(Request $request): JsonResponse
    {
        $f = $request->validate(['q' => ['required', 'string', 'min:1', 'max:50'], 'kod_daerah' => ['nullable', 'string', 'size:4']]);

        return response()->json(['data' => $this->lots->search($f['q'], $this->user($request), 20, array_filter(['kod_daerah' => $f['kod_daerah'] ?? null]))]);
    }
}
