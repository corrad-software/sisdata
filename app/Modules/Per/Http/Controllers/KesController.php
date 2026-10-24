<?php

namespace App\Modules\Per\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Per\Http\CasePresenter;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PeringkatPemerolehan;
use App\Modules\Per\Services\CaseService;
use App\Modules\Per\Services\LotLinker;
use App\Modules\Per\Services\Reports;
use App\Modules\Per\Support\ChannelCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** /api/v1/per/kes — acquisition cases (SRS-PER-01..21). */
class KesController extends Controller
{
    public function __construct(
        private CaseService $cases,
        private CasePresenter $presenter,
        private AuditLogger $audit,
    ) {}

    /** GET /kes — list with filters (channel, stage, district, status…). */
    public function index(Request $request, Reports $reports): JsonResponse
    {
        $f = $request->validate([
            'saluran' => ['nullable', 'string', 'max:40'],
            'peringkat' => ['nullable', 'string', 'max:40'],
            'daerah' => ['nullable', 'regex:/^\d{4}$/'],
            'status' => ['nullable', Rule::in(Pemerolehan::STATUS)],
            'bahagian' => ['nullable', Rule::in(array_keys(ChannelCatalog::BAHAGIAN))],
            'q' => ['nullable', 'string', 'max:100'],
            'lot' => ['nullable', 'uuid'],
            'dokumen' => ['nullable', 'in:lengkap,tidak_lengkap'],
            'lewat' => ['nullable', 'boolean'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = $reports->query($f)->with(['saluran', 'peringkat', 'pegawai'])
            ->when($f['q'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn ($w) => $w->where('no_kes', 'ilike', $like)->orWhere('tajuk', 'ilike', $like)->orWhere('no_lot_dijangka', 'ilike', $like));
            })
            ->when($f['lot'] ?? null, fn ($q, $lot) => $q->where('lot_id', $lot))
            ->when($f['dokumen'] ?? null, fn ($q, $s) => $q->where('status_kelengkapan_dokumen', $s))
            ->when($request->boolean('lewat'), fn ($q) => $q->whereHas('peringkat', fn ($s) => $s
                ->where('status_peringkat', PeringkatPemerolehan::SEMASA)
                ->whereRaw('(current_date - tarikh_masuk::date) > tempoh_sasaran_hari')))
            ->orderByDesc('tarikh_mula_kes')->orderByDesc('created_at');

        $page = $query->paginate((int) ($f['per_halaman'] ?? 20));

        return response()->json([
            'data' => collect($page->items())->map(fn (Pemerolehan $c) => $this->presenter->summary($c))->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem()],
        ]);
    }

    /** POST /kes — register a case (wizard: channel → channel form → parties). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kod_saluran' => ['required', 'string', Rule::in(ChannelCatalog::codes())],
            'tajuk' => ['required', 'string', 'max:255'],
            'lot_id' => ['nullable', 'uuid'],
            'no_lot' => ['nullable', 'required_without:lot_id', 'string', 'max:50'],
            'kod_mukim' => ['nullable', 'required_without:lot_id', 'regex:/^\d{6}$/'],
            'tarikh_mula_kes' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'id_pegawai_bertanggungjawab' => ['nullable', 'integer', 'exists:users,id'],
            ...$this->valueRules(),
            'catatan' => ['nullable', 'string', 'max:5000'],
            'butiran' => ['nullable', 'array'],
            'pihak' => ['nullable', 'array', 'max:50'],
            ...$this->partyRules('pihak.*.'),
        ]);

        $case = $this->cases->create($data, $this->user($request));

        return response()->json(['data' => $this->presenter->detail($case, $this->user($request), $this->cases), 'message' => __('per::per.kes_didaftar', ['no_kes' => $case->no_kes])], 201);
    }

    public function show(Request $request, string $kes): JsonResponse
    {
        $case = $this->find($kes);
        $this->audit->record('lihat', 'per', 'pemerolehan', $case->id, meta: ['no_kes' => $case->no_kes, 'klasifikasi' => 'sulit']);

        return response()->json(['data' => $this->presenter->detail($case, $this->user($request), $this->cases)]);
    }

    public function update(Request $request, string $kes): JsonResponse
    {
        $case = $this->find($kes);
        $data = $request->validate([
            'tajuk' => ['sometimes', 'required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'tarikh_pemerolehan' => ['nullable', 'date_format:Y-m-d'],
            'id_pegawai_bertanggungjawab' => ['nullable', 'integer', 'exists:users,id'],
            ...$this->valueRules(),
            'butiran' => ['sometimes', 'array'],
            'sebab' => ['nullable', 'string', 'max:500'],
        ]);

        $case = $this->cases->update($case, $data, $this->user($request));

        return response()->json(['data' => $this->presenter->detail($case, $this->user($request), $this->cases), 'message' => __('per::per.kes_dikemas_kini')]);
    }

    /** POST /kes/{id}/maju — next stage (or (re)submit for approval at the approval stage). */
    public function advance(Request $request, string $kes): JsonResponse
    {
        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:2000'],
            'id_pegawai_seterusnya' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $case = $this->cases->advance($this->find($kes), $this->user($request), $data['catatan'] ?? null, $data['id_pegawai_seterusnya'] ?? null);

        return response()->json(['data' => $this->presenter->detail($case, $this->user($request), $this->cases), 'message' => __('per::per.kes_dimajukan')]);
    }

    public function assign(Request $request, string $kes): JsonResponse
    {
        $data = $request->validate(['id_pegawai' => ['required', 'integer', 'exists:users,id']]);
        $case = $this->find($kes);
        $this->cases->assignOfficer($case, (int) $data['id_pegawai']);

        return response()->json(['data' => $this->presenter->detail($case->refresh(), $this->user($request), $this->cases), 'message' => __('per::per.pegawai_ditetapkan')]);
    }

    /** POST /kes/{id}/pautkan-lot — SRS-PER-20 on demand. */
    public function relink(Request $request, string $kes, LotLinker $linker): JsonResponse
    {
        $case = $this->find($kes);
        $linked = $linker->link($case, $this->user($request));

        return response()->json([
            'data' => $this->presenter->detail($case->refresh(), $this->user($request), $this->cases),
            'message' => $linked ? __('per::per.lot_dipautkan') : __('per::per.lot_belum_didaftar'),
            'dipautkan' => $linked,
        ]);
    }

    /** POST /kes/{id}/semakan-awam — accept or reject a portal submission (SRS-PER-25). */
    public function review(Request $request, string $kes): JsonResponse
    {
        $data = $request->validate(['terima' => ['required', 'boolean'], 'catatan' => ['nullable', 'required_if:terima,false', 'string', 'max:2000']]);
        $case = $this->cases->acceptPublic($this->find($kes), $this->user($request), (bool) $data['terima'], $data['catatan'] ?? null);

        return response()->json(['data' => $this->presenter->detail($case, $this->user($request), $this->cases)]);
    }

    public function cancel(Request $request, string $kes): JsonResponse
    {
        $data = $request->validate(['sebab' => ['required', 'string', 'min:5', 'max:2000']]);
        $case = $this->cases->cancel($this->find($kes), $this->user($request), $data['sebab']);

        return response()->json(['data' => $this->presenter->detail($case, $this->user($request), $this->cases), 'message' => __('per::per.kes_dibatalkan')]);
    }

    /** POST /kes/{id}/tutup — SRS-PER-21/22: create the title via MOD-HKM and deposit it in the vault. */
    public function close(Request $request, string $kes): JsonResponse
    {
        $data = $request->validate([
            'hak_milik' => ['required', 'array'],
            'hak_milik.no_hak_milik' => ['required', 'string', 'max:60'],
            'hak_milik.jenis_hak_milik' => ['required', 'string', 'max:30'],
            'hak_milik.tarikh_pendaftaran' => ['nullable', 'date_format:Y-m-d'],
            'hak_milik.tarikh_tamat_tempoh' => ['nullable', 'date_format:Y-m-d'],
            'hak_milik.pejabat_pendaftaran' => ['nullable', 'string', 'max:150'],
            'hak_milik.keluasan' => ['nullable', 'numeric', 'min:0'],
            'hak_milik.kategori_tanah' => ['nullable', 'string', 'max:50'],
            'hak_milik.syarat_nyata' => ['nullable', 'string', 'max:2000'],
            'peti_besi' => ['required', 'array'],
            'peti_besi.kod_peti_besi' => ['required', 'string', 'max:30'],
            'peti_besi.no_fail' => ['nullable', 'string', 'max:50'],
            'peti_besi.lokasi_rak' => ['nullable', 'string', 'max:50'],
            'peti_besi.no_kotak_pengikat' => ['nullable', 'string', 'max:50'],
            'peti_besi.jenis_dokumen_asal' => ['nullable', 'string', 'max:50'],
        ]);

        /** @var array{no_hak_milik: string, jenis_hak_milik: string} $title */
        $title = $data['hak_milik'];
        /** @var array{kod_peti_besi: string} $vault */
        $vault = $data['peti_besi'];
        $case = $this->cases->close($this->find($kes), $title, $vault, $this->user($request));

        return response()->json(['data' => $this->presenter->detail($case, $this->user($request), $this->cases), 'message' => __('per::per.kes_ditutup_berjaya', ['hari' => $case->tempoh_keseluruhan_hari, 'sasaran' => $case->tempoh_sasaran_hari])]);
    }

    /** GET /kes/{id}/garis-masa — SRS-PER-19: stages, officers and durations in order. */
    public function timeline(string $kes): JsonResponse
    {
        $case = $this->find($kes)->load(['peringkat.pegawai', 'kelulusan.pelulus']);
        $items = [];
        foreach ($case->peringkat as $s) {
            if ($s->tarikh_masuk === null) {
                continue;
            }
            $items[] = [
                'tarikh' => $s->tarikh_masuk->toIso8601String(), 'jenis' => 'peringkat', 'kod' => $s->kod_peringkat, 'tajuk' => $s->nama_peringkat,
                'pegawai' => $s->pegawai?->name, 'tarikh_keluar' => $s->tarikh_keluar?->toIso8601String(), 'tempoh_hari' => $s->tempoh_hari ?? $s->hariBerjalan(),
                'tempoh_sasaran_hari' => $s->tempoh_sasaran_hari, 'lewat' => $s->lewat(), 'status' => $s->status_peringkat, 'dieskalasi' => $s->status_eskalasi === 'dieskalasi',
            ];
        }
        foreach ($case->kelulusan as $k) {
            $items[] = [
                'tarikh' => $k->tarikh_masa->toIso8601String(), 'jenis' => 'kelulusan', 'kod' => $k->aras_kelulusan, 'tajuk' => $k->tindakan,
                'pegawai' => $k->pelulus?->name, 'justifikasi' => $k->justifikasi,
            ];
        }
        usort($items, fn ($a, $b) => strcmp($a['tarikh'], $b['tarikh']));

        return response()->json(['data' => $items]);
    }

    /** GET /pegawai — officers to assign (active users). */
    public function officers(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        return response()->json(['data' => User::query()->where('is_active', true)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'ilike', '%'.$q.'%')->orWhere('email', 'ilike', '%'.$q.'%')))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'jawatan', 'bahagian'])
            ->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->name, 'jawatan' => $u->jawatan, 'bahagian' => $u->bahagian])]);
    }

    // ------------------------------------------------------------------

    /** @return array<string, list<mixed>> */
    private function valueRules(): array
    {
        return [
            'nilai_pasaran' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'nilai_pemerolehan' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'sumber_dana' => ['nullable', 'string', 'max:100'],
            'rujukan_penilaian_jpph' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function partyRules(string $prefix = ''): array
    {
        return [
            $prefix.'peranan_pihak' => ['required', Rule::in(array_keys(ChannelCatalog::PERANAN_PIHAK))],
            $prefix.'nama' => ['required', 'string', 'max:200'],
            $prefix.'hubungan' => ['nullable', 'string', 'max:100'],
            $prefix.'jenis_dokumen_pengenalan' => ['nullable', 'in:mykad,pasport,no_syarikat,lain'],
            $prefix.'no_dokumen_pengenalan' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
            $prefix.'bahagian_pusaka' => ['nullable', 'string', 'max:30'],
            $prefix.'status_pengesahan' => ['nullable', 'in:belum_disahkan,disahkan,ditolak'],
            $prefix.'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function find(string $id): Pemerolehan
    {
        abort_unless(Str::isUuid($id), 404);

        return Pemerolehan::query()->with(['saluran', 'peringkat'])->findOrFail($id);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
