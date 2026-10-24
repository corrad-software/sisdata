<?php

namespace App\Modules\Adu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Adu\Http\Present;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Services\AduanService;
use App\Modules\Adu\Services\Penugasan;
use App\Modules\Adu\Services\PrivasiPengadu;
use App\Modules\Adu\Services\SlaAduan;
use App\Modules\Adu\Services\Triaj;
use App\Modules\Adu\Support\Akses;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** SRS-ADU-01/06/11/12/15/18/20: the single complaint queue, intake form, detail. */
class AduanController extends Controller
{
    public const SALURAN_DALAMAN = ['dalaman', 'kaunter', 'emel', 'telefon', 'lapangan'];

    /** Per-file ceiling at the API edge; MOD-DMS applies its own type/size rules after. */
    public const SAIZ_MAKS_KB = 20480;

    public function __construct(
        private AduanService $service,
        private Present $present,
        private Rujukan $ref,
        private Akses $akses,
        private PrivasiPengadu $privasi,
        private LotRegistry $lots,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:100'],
            'susun' => ['nullable', Rule::in(['tarikh_terima', 'sla', 'keutamaan'])],
        ]);
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $query = Aduan::query()->denganKoordinat()->with(['pegawai', 'slaSemasa'])
            ->when($status === 'terbuka', fn ($b) => $b->terbuka())
            ->when($status !== '' && $status !== 'terbuka', fn ($b) => $b->where('aduan.status', $status))
            ->when($request->query('kategori'), fn ($b, $v) => $b->where('aduan.kategori', $v))
            ->when($request->query('kod_daerah'), fn ($b, $v) => $b->where('aduan.kod_daerah', $v))
            ->when($request->query('kod_mukim'), fn ($b, $v) => $b->where('aduan.kod_mukim', $v))
            ->when($request->query('saluran'), fn ($b, $v) => $b->where('aduan.saluran_penerimaan', $v))
            ->when($request->query('keutamaan'), fn ($b, $v) => $b->where('aduan.tahap_keutamaan', $v))
            ->when($request->query('lot_id'), fn ($b, $v) => $b->where('aduan.lot_id', $v))
            ->when($request->boolean('tugasan_saya'), fn ($b) => $b->where('aduan.id_pegawai', $user->id))
            ->when($request->boolean('belum_ditugaskan'), fn ($b) => $b->whereNull('aduan.id_pegawai'))
            ->when($request->boolean('tanpa_nama'), fn ($b) => $b->where('aduan.status_tanpa_nama', true))
            ->when($request->boolean('bukan_tanah_mais'), fn ($b) => $b->where('aduan.status_bukan_tanah_mais', true))
            ->when($request->boolean('melepasi_sla'), fn ($b) => $b->terbuka()->whereHas('slaSemasa', fn ($s) => $s->where('masa_sasaran_selesai', '<', now())))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('aduan.no_rujukan_aduan', 'ilike', "%{$q}%")->orWhere('aduan.keterangan', 'ilike', "%{$q}%")->orWhere('aduan.lokasi_teks', 'ilike', "%{$q}%")));

        match ((string) $request->query('susun', 'tarikh_terima')) {
            'sla' => $query->leftJoin('jejak_sla_aduan as js', fn ($j) => $j->on('js.aduan_id', '=', 'aduan.id')->where('js.semasa', true))->orderByRaw('js.masa_sasaran_selesai ASC NULLS LAST'),
            'keutamaan' => $query->orderByRaw("CASE aduan.tahap_keutamaan WHEN 'kritikal' THEN 0 WHEN 'tinggi' THEN 1 WHEN 'sederhana' THEN 2 ELSE 3 END"),
            default => null,
        };
        $page = $query->orderByDesc('aduan.tarikh_terima')->orderBy('aduan.id')->paginate((int) $request->query('per_halaman', 25));
        $lots = $this->present->lotsFor($page->getCollection(), $user);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Aduan $a) => $this->present->aduan($a, $lots))->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    /** Internal intake (counter / e-mail / phone / field-mobile with GPS + photos). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + [
            'saluran_penerimaan' => ['required', Rule::in(self::SALURAN_DALAMAN)],
            'tanpa_nama' => ['nullable', 'boolean'],
            'pengadu.nama' => ['nullable', 'string', 'max:200'],
            'pengadu.no_telefon' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'pengadu.emel' => ['nullable', 'email', 'max:200'],
            'pengadu.alamat' => ['nullable', 'string', 'max:500'],
            'pengadu.saluran_makluman' => ['nullable', Rule::in(['emel', 'sms', 'tiada'])],
            'fail' => ['nullable', 'array', 'max:10'],
            'fail.*' => ['file', 'max:'.self::SAIZ_MAKS_KB],
        ]);
        $this->validateCodes($data);

        $aduan = $this->service->terima(
            $data,
            $data['saluran_penerimaan'],
            $request->user(),
            null,
            $data['pengadu'] ?? null,
            (bool) ($data['tanpa_nama'] ?? false),
            $request->file('fail', []),
        );

        return response()->json(['data' => $this->detail($aduan, $request->user()), 'message' => __('adu::adu.mesej.diterima', ['no' => $aduan->no_rujukan_aduan])], 201);
    }

    public function show(Request $request, Aduan $aduan): JsonResponse
    {
        return response()->json(['data' => $this->detail($aduan, $request->user())]);
    }

    public function update(Request $request, Aduan $aduan): JsonResponse
    {
        $this->bolehUrus($request->user(), $aduan);
        $data = $request->validate([
            'kategori' => ['sometimes', 'string', 'max:20'],
            'subkategori' => ['sometimes', 'nullable', 'string', 'max:30'],
            'tahap_keutamaan' => ['sometimes', Rule::in(array_keys(Lalai::KEUTAMAAN))],
            'lot_id' => ['sometimes', 'nullable', 'uuid'],
            'lokasi_teks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $this->validateCodes($data + ['kategori' => $aduan->kategori]);
        $this->service->kemasKini($aduan, $data, $request->user());

        return response()->json(['data' => $this->detail($aduan->refresh(), $request->user()), 'message' => __('adu::adu.mesej.dikemas_kini')]);
    }

    public function kronologi(Aduan $aduan): JsonResponse
    {
        return response()->json(['data' => $aduan->kronologi()->with('pegawai')->get()->map(fn ($k) => $this->present->kronologi($k))->values()]);
    }

    /** Masked reporter block (SRS-ADU-20). */
    public function pengadu(Request $request, Aduan $aduan): JsonResponse
    {
        return response()->json(['data' => $this->privasi->ringkasan($aduan, $request->user())]);
    }

    /** SRS-ADU-21: explicit, purpose-stated reveal; audited. */
    public function dedahPengadu(Request $request, Aduan $aduan): JsonResponse
    {
        $data = $request->validate([
            'tujuan' => ['required', 'string', 'min:5', 'max:500'],
            'medan' => ['nullable', 'array'],
            'medan.*' => [Rule::in(['nama', 'no_telefon', 'emel', 'alamat'])],
        ]);

        return response()->json(['data' => $this->privasi->dedah($aduan, $request->user(), $data['tujuan'], $data['medan'] ?? [], $request->ip())]);
    }

    /** Documents via MOD-DMS (inherits complaint access). */
    public function dokumen(Request $request, Aduan $aduan): JsonResponse
    {
        return response()->json(['data' => app(DocumentStore::class)->forOwner(AduanService::SUBJECT, $aduan->id, $request->user())]);
    }

    public function muatNaik(Request $request, Aduan $aduan): JsonResponse
    {
        $this->bolehUrus($request->user(), $aduan);
        $request->validate([
            'fail' => ['required', 'array', 'min:1', 'max:10'],
            'fail.*' => ['file', 'max:'.self::SAIZ_MAKS_KB],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'sumber' => ['nullable', Rule::in(['dalaman', 'lapangan'])],
        ]);
        $out = [];
        $errors = [];
        foreach ($request->file('fail', []) as $file) {
            try {
                $out[] = $this->service->lampir($aduan, $file, $request->user(), (string) $request->input('sumber', 'dalaman'), $request->filled('lat') ? (float) $request->input('lat') : null, $request->filled('lng') ? (float) $request->input('lng') : null)->id_dokumen;
            } catch (DocumentRejected $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($out === [] && $errors !== []) {
            abort(422, implode(' ', $errors));
        }

        return response()->json(['data' => ['dokumen' => $out, 'ditolak' => $errors], 'message' => __('adu::adu.mesej.dimuat_naik', ['n' => count($out)])], 201);
    }

    /** Officers eligible for (re)assignment — in scope and able to act. */
    public function pegawaiLayak(Request $request, Aduan $aduan): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $users = User::query()->where('is_active', true)
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'ilike', "%{$q}%")->orWhere('email', 'ilike', "%{$q}%")))
            ->orderBy('name')->limit(200)->get();
        $eligible = $users->filter(fn (User $u) => app(Penugasan::class)->layak($u, $aduan))->take(30);

        return response()->json(['data' => $eligible->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->name, 'jawatan' => $u->jawatan, 'bahagian' => $u->bahagian])->values()]);
    }

    /** Lot picker for the intake form (SRS-ADU-15) — scoped by LotRegistry to the user's access. */
    public function cariLot(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:50'], 'kod_daerah' => ['nullable', 'string', 'size:4']]);
        $rows = $this->lots->search((string) $request->query('q'), $request->user(), 15, array_filter(['kod_daerah' => $request->query('kod_daerah')]));

        return response()->json(['data' => array_map(fn ($l) => [
            'id' => $l['id'], 'no_lot' => $l['no_lot'], 'kod_mukim' => $l['kod_mukim'], 'mukim' => $this->ref->namaMukim($l['kod_mukim']),
            'kod_daerah' => $l['kod_daerah'], 'daerah' => $this->ref->namaDaerah($l['kod_daerah']),
        ], $rows)]);
    }

    /** SRS-ADU-09 helper for the intake form: category suggestion while typing. */
    public function cadangKategori(Request $request, Triaj $triaj): JsonResponse
    {
        $request->validate(['keterangan' => ['required', 'string', 'max:5000']]);
        [$kategori, $keutamaan, $kata] = $triaj->cadangKategori((string) $request->input('keterangan'));

        return response()->json(['data' => ['kategori' => $kategori, 'kategori_nama' => $this->ref->namaKategori($kategori), 'keutamaan' => $keutamaan, 'kata_kunci' => $kata]]);
    }

    /** @return array<string, mixed> */
    public function detail(Aduan $aduan, User $user): array
    {
        $aduan = Aduan::query()->denganKoordinat()->with(['pegawai', 'penyelia', 'slaSemasa', 'lawatan.pegawai', 'resolusi.penyelesai', 'resolusi.pelulus', 'rujukan.pegawai', 'eskalasi', 'dokumen'])->whereKey($aduan->id)->firstOrFail();
        $lot = $aduan->lot_id ? $this->lots->find($aduan->lot_id, $user) : null;
        $pendua = $aduan->id_aduan_pendua_dicadang ? Aduan::query()->find($aduan->id_aduan_pendua_dicadang, ['id', 'no_rujukan_aduan', 'status']) : null;
        $induk = $aduan->id_aduan_induk ? Aduan::query()->find($aduan->id_aduan_induk, ['id', 'no_rujukan_aduan', 'status']) : null;

        return $this->present->aduan($aduan, $lot ? [$lot['id'] => $lot] : []) + [
            'lot' => $lot ? ['id' => $lot['id'], 'no_lot' => $lot['no_lot'], 'mukim' => $this->ref->namaMukim($lot['kod_mukim']), 'pautan' => $this->lots->profileLink($lot['id'])] : null,
            'kaedah_padanan_lot' => $aduan->kaedah_padanan_lot,
            'jawapan_automatik' => $aduan->jawapan_automatik,
            'agensi_dicadang' => $aduan->agensi_dicadang ?? [],
            'jawapan_dihantar' => $aduan->jawapan_dihantar,
            'pendua_dicadang' => $pendua ? ['id' => $pendua->id, 'no_rujukan_aduan' => $pendua->no_rujukan_aduan, 'status' => $pendua->status] : null,
            'induk' => $induk ? ['id' => $induk->id, 'no_rujukan_aduan' => $induk->no_rujukan_aduan] : null,
            'penyelia' => $aduan->penyelia ? ['id' => $aduan->penyelia->id, 'nama' => $aduan->penyelia->name] : null,
            'tindakan_susulan' => $aduan->tindakan_susulan,
            'bahagian_dirujuk' => $aduan->bahagian_dirujuk,
            'id_instans_aliran' => $aduan->id_instans_aliran,
            'lawatan' => $aduan->lawatan->map(fn ($v) => ['id' => $v->id, 'tarikh_jadual' => $v->tarikh_jadual->toDateString(), 'tarikh_lawatan' => $v->tarikh_lawatan?->toIso8601String(), 'status' => $v->status, 'penemuan' => $v->penemuan, 'pegawai' => $v->pegawai?->name])->values(),
            'resolusi' => $aduan->resolusi->map(fn ($r) => ['id' => $r->id, 'kategori_resolusi' => $r->kategori_resolusi, 'kategori_nama' => Lalai::KATEGORI_RESOLUSI[$r->kategori_resolusi] ?? $r->kategori_resolusi, 'keterangan' => $r->keterangan_resolusi, 'justifikasi' => $r->justifikasi, 'status' => $r->status, 'penyelesai' => $r->penyelesai?->name, 'pelulus' => $r->pelulus?->name, 'ulasan_pelulus' => $r->ulasan_pelulus, 'tarikh_tutup' => $r->tarikh_tutup?->toIso8601String(), 'status_makluman_pengadu' => $r->status_makluman_pengadu, 'dicipta' => $r->created_at->toIso8601String()])->values(),
            'rujukan' => $aduan->rujukan->map(fn ($r) => ['id' => $r->id, 'jenis_rujukan' => $r->jenis_rujukan, 'jenis_nama' => Lalai::JENIS_RUJUKAN[$r->jenis_rujukan] ?? $r->jenis_rujukan, 'nama_agensi' => $r->nama_agensi, 'keterangan' => $r->keterangan, 'status' => $r->status, 'pegawai' => $r->pegawai?->name])->values(),
            'eskalasi' => $aduan->eskalasi->map(fn ($e) => ['id' => $e->id, 'peringkat' => $e->peringkat, 'jenis' => $e->jenis, 'sebab' => $e->sebab_eskalasi, 'tarikh' => $e->tarikh_eskalasi->toIso8601String()])->values(),
            'jejak_sla' => $aduan->jejakSla()->get()->map(fn ($j) => app(SlaAduan::class)->baki($j))->values(),
            'pengadu' => $this->privasi->ringkasan($aduan, $user),
            'tindakan_dibenarkan' => [
                'urus' => $this->akses->bolehUrus($user, $aduan),
                'tugaskan' => $user->can('adu.penugasan.kemas_kini'),
                'eksport' => $user->can('adu.aduan.eksport'),
            ],
        ];
    }

    private function bolehUrus(User $user, Aduan $aduan): void
    {
        if (! $this->akses->bolehUrus($user, $aduan)) {
            app(AuditLogger::class)->record('kemas_kini', 'adu', 'aduan', $aduan->id, 'gagal', 'bukan_pegawai_ditugaskan');
            abort(403, __('adu::adu.ralat.bukan_pegawai'));
        }
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'kategori' => ['nullable', 'string', 'max:20'],
            'subkategori' => ['nullable', 'string', 'max:30'],
            'keterangan' => ['required', 'string', 'min:10', 'max:5000'],
            'lokasi_teks' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:0.5,7.5', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:99,120', 'required_with:lat'],
            'lot_id' => ['nullable', 'uuid'],
            'kod_daerah' => ['nullable', 'string', 'size:4'],
            'kod_mukim' => ['nullable', 'string', 'size:6'],
            'tahap_keutamaan' => ['nullable', Rule::in(array_keys(Lalai::KEUTAMAAN))],
            'tarikh_aduan' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateCodes(array $data): void
    {
        $errors = [];
        if (! empty($data['kategori']) && ! $this->ref->kategoriSah($data['kategori'])) {
            $errors['kategori'] = [__('adu::adu.ralat.kategori')];
        }
        if (! empty($data['subkategori']) && ! isset($this->ref->subkategori()[$data['kategori'] ?? ''][$data['subkategori']])) {
            $errors['subkategori'] = [__('adu::adu.ralat.subkategori')];
        }
        if (! empty($data['lot_id']) && ! $this->lots->exists($data['lot_id'], request()->user())) {
            $errors['lot_id'] = [__('adu::adu.ralat.lot')];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
