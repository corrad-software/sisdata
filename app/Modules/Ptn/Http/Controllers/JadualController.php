<?php

namespace App\Modules\Ptn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Ptn\Http\Present;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Services\Penjadualan;
use App\Modules\Ptn\Services\Penugasan;
use App\Modules\Ptn\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** SRS-PTN-01/02: monitoring plan — list, generate, create, assign, cancel. */
class JadualController extends Controller
{
    public function __construct(private Present $present, private LotRegistry $lots) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'tahun' => ['nullable', 'integer'], 'kod_daerah' => ['nullable', 'string'], 'kod_mukim' => ['nullable', 'string'],
            'status' => ['nullable', 'string'], 'keutamaan' => ['nullable', 'string'], 'id_pegawai' => ['nullable', 'integer'],
            'lot_id' => ['nullable', 'uuid'], 'tertunggak' => ['nullable', 'boolean'], 'tanpa_pegawai' => ['nullable', 'boolean'],
            'dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date'], 'per_page' => ['nullable', 'integer', 'max:200'],
        ]);
        $q = JadualPemantauan::query()->with('pegawai')
            ->when($f['tahun'] ?? null, fn ($q, $v) => $q->where('tahun', $v))
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v))
            ->when($f['kod_mukim'] ?? null, fn ($q, $v) => $q->where('kod_mukim', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status_pelaksanaan', $v))
            ->when($f['keutamaan'] ?? null, fn ($q, $v) => $q->where('keutamaan_risiko', $v))
            ->when($f['id_pegawai'] ?? null, fn ($q, $v) => $q->where('id_pegawai_bertugas', $v))
            ->when($f['lot_id'] ?? null, fn ($q, $v) => $q->where('lot_id', $v))
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->where('tarikh_sasaran', '>=', $v))
            ->when($f['hingga'] ?? null, fn ($q, $v) => $q->where('tarikh_sasaran', '<=', $v))
            ->when(! empty($f['tanpa_pegawai']), fn ($q) => $q->whereNull('id_pegawai_bertugas'))
            ->when(! empty($f['tertunggak']), fn ($q) => $q->whereIn('status_pelaksanaan', JadualPemantauan::TERBUKA)->where('tarikh_sasaran', '<', now()->toDateString()))
            ->orderBy('tarikh_sasaran')->orderBy('id');
        $page = $q->paginate((int) ($f['per_page'] ?? 25));
        $lots = $this->lots->summaries($page->getCollection()->pluck('lot_id')->unique()->values()->all(), $request->user());

        return response()->json([
            'data' => $page->getCollection()->map(fn ($j) => $this->present->jadual($j, $lots))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function show(Request $request, string $jadual): JsonResponse
    {
        $j = JadualPemantauan::query()->with(['pegawai', 'templat'])->findOrFail($jadual);
        $lots = $this->lots->summaries([$j->lot_id], $request->user());

        return response()->json(['data' => $this->present->jadual($j, $lots) + [
            'templat' => $j->templat ? ['id' => $j->templat->id, 'nama' => $j->templat->nama, 'versi' => $j->templat->versi] : null,
            'lawatan' => $j->lawatan()->get(['id', 'no_rujukan', 'tarikh_lawatan', 'status_lawatan'])->map(fn ($l) => ['id' => $l->id, 'no_rujukan' => $l->no_rujukan, 'tarikh_lawatan' => $l->tarikh_lawatan->toDateString(), 'status_lawatan' => $l->status_lawatan]),
        ]]);
    }

    public function jana(Request $request, Penjadualan $penjadualan): JsonResponse
    {
        $d = $request->validate(['tahun' => ['required', 'integer', 'min:2020', 'max:2100'], 'kod_daerah' => ['nullable', 'string', 'size:4'], 'semula' => ['nullable', 'boolean']]);
        $r = $penjadualan->janaTahunan((int) $d['tahun'], $request->user(), $d['kod_daerah'] ?? null, (bool) ($d['semula'] ?? false));

        return response()->json(['data' => $r, 'message' => __('ptn::ptn.jadual_dijana', ['n' => $r['dijana'], 'lot' => $r['lot']])], 201);
    }

    public function store(Request $request, Penjadualan $penjadualan): JsonResponse
    {
        $d = $request->validate([
            'lot_id' => ['required', 'uuid'], 'tarikh_sasaran' => ['required', 'date'], 'objektif' => ['required', 'string', 'max:2000'],
            'jenis_pemeriksaan' => ['required', Rule::in(array_keys(Lalai::JENIS_PEMERIKSAAN))], 'keutamaan_risiko' => ['nullable', Rule::in(array_keys(Lalai::TAHAP_RISIKO))],
            'id_templat' => ['nullable', 'integer', 'exists:templat_senarai_semak,id'],
        ]);
        $lot = $this->lots->find($d['lot_id'], $request->user());
        if ($lot === null) {
            throw ValidationException::withMessages(['lot_id' => __('ptn::ptn.lot_tiada')]);
        }
        $p = $penjadualan->keutamaan([$lot['id']])[$lot['id']];
        $j = new JadualPemantauan;
        $j->forceFill([
            'lot_id' => $lot['id'], 'kod_daerah' => $lot['kod_daerah'], 'kod_mukim' => $lot['kod_mukim'], 'klasifikasi' => $lot['klasifikasi'],
            'tahun' => (int) substr($d['tarikh_sasaran'], 0, 4), 'kekerapan' => 1, 'bil_dalam_tahun' => 1, 'tarikh_sasaran' => $d['tarikh_sasaran'],
            'objektif' => $d['objektif'], 'jenis_pemeriksaan' => $d['jenis_pemeriksaan'],
            'id_templat' => $d['id_templat'] ?? $penjadualan->templatUntuk($lot['kategori_guna_tanah'], $d['jenis_pemeriksaan'])?->id,
            'keutamaan_risiko' => $d['keutamaan_risiko'] ?? $p['tahap'], 'skor_keutamaan' => $p['skor'], 'faktor_keutamaan' => $p['faktor'],
            'status_pelaksanaan' => 'dirancang', 'dijana_automatik' => false, 'id_pencipta' => $request->user()->id,
        ])->save();

        return response()->json(['data' => $this->present->jadual($j->refresh(), [$lot['id'] => $lot])], 201);
    }

    public function update(Request $request, string $jadual): JsonResponse
    {
        $j = JadualPemantauan::query()->findOrFail($jadual);
        $d = $request->validate(['tarikh_sasaran' => ['sometimes', 'date'], 'objektif' => ['sometimes', 'string', 'max:2000'], 'keutamaan_risiko' => ['sometimes', Rule::in(array_keys(Lalai::TAHAP_RISIKO))], 'id_templat' => ['sometimes', 'nullable', 'integer', 'exists:templat_senarai_semak,id']]);
        if (! $j->terbuka()) {
            throw ValidationException::withMessages(['jadual' => __('ptn::ptn.jadual_tutup')]);
        }
        $j->fill($d);
        $j->versi = $j->versi + 1;
        $j->save();

        return response()->json(['data' => $this->present->jadual($j->load('pegawai'))]);
    }

    public function batal(Request $request, string $jadual): JsonResponse
    {
        $j = JadualPemantauan::query()->findOrFail($jadual);
        $request->validate(['sebab' => ['required', 'string', 'max:500']]);
        if (! $j->terbuka()) {
            throw ValidationException::withMessages(['jadual' => __('ptn::ptn.jadual_tutup')]);
        }
        $j->forceFill(['status_pelaksanaan' => 'dibatalkan', 'objektif' => $j->objektif.' [Dibatalkan: '.$request->string('sebab').']', 'versi' => $j->versi + 1])->save();

        return response()->json(['data' => $this->present->jadual($j)]);
    }

    public function tugaskan(Request $request, string $jadual, Penugasan $penugasan): JsonResponse
    {
        $j = JadualPemantauan::query()->findOrFail($jadual);
        $d = $request->validate(['id_pegawai' => ['required', 'integer']]);
        $pegawai = User::query()->findOrFail($d['id_pegawai']);
        $penugasan->tugaskan($j, $pegawai, $request->user());

        return response()->json(['data' => $this->present->jadual($j->load('pegawai'))]);
    }

    public function agih(Request $request, Penugasan $penugasan): JsonResponse
    {
        $d = $request->validate(['kod_daerah' => ['nullable', 'string'], 'tahun' => ['nullable', 'integer'], 'hingga' => ['nullable', 'date']]);
        $n = $penugasan->agih($d, $request->user());

        return response()->json(['data' => ['ditugaskan' => $n], 'message' => __('ptn::ptn.agih_selesai', ['n' => $n])]);
    }

    public function pegawaiLayak(Request $request, Penugasan $penugasan): JsonResponse
    {
        $kod = $request->query('kod_daerah');

        return response()->json(['data' => array_map(fn (User $u) => ['id' => $u->id, 'nama' => $u->name], $penugasan->pegawaiLayak(is_string($kod) && $kod !== '' ? $kod : null))]);
    }

    /** Lot picker for planning / manual issues (LotRegistry::search, scoped). */
    public function cariLot(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('ptn.jadual.cipta') || $request->user()->can('ptn.isu.cipta'), 403);
        $q = trim((string) $request->query('q', ''));

        return response()->json(['data' => $q === '' ? [] : $this->lots->search($q, $request->user(), 15)]);
    }
}
