<?php

namespace App\Modules\Hkm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Hkm\Http\Present;
use App\Modules\Hkm\Models\DaftarPetiBesi;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PengeluaranDokumen;
use App\Modules\Hkm\Models\PetiBesi;
use App\Modules\Hkm\Services\VaultService;
use App\Modules\Hkm\Support\Eksport;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Hkm\Support\LotLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** SRS-HKM-03/04/05 — vaults, vault register, printable inventory. */
class PetiBesiController extends Controller
{
    public function __construct(private VaultService $vault, private Present $present, private AuditLogger $audit, private LotLookup $lots) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => PetiBesi::query()->with('penjaga')->withCount('daftar')->orderBy('kod_peti_besi')->get()
            ->map(fn (PetiBesi $p) => $this->present->vault($p))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $vault = PetiBesi::query()->create($request->validate($this->rules(true), [], $this->attributes()));

        return response()->json(['data' => $this->present->vault($vault->load('penjaga')), 'message' => __('hkm::hkm.mesej.peti_besi_didaftar')], 201);
    }

    public function update(Request $request, PetiBesi $petiBesi): JsonResponse
    {
        $petiBesi->fill($request->validate($this->rules(false, $petiBesi->id), [], $this->attributes()))->save();

        return response()->json(['data' => $this->present->vault($petiBesi->load('penjaga')), 'message' => __('hkm::hkm.mesej.dikemas_kini')]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $create, ?int $id = null): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'kod_peti_besi' => [$req, 'string', 'max:30', 'regex:/^[A-Z0-9\-\/]+$/', Rule::unique('peti_besi', 'kod_peti_besi')->ignore($id)],
            'nama' => [$req, 'string', 'max:150'],
            'lokasi_bangunan' => [$req, 'string', 'max:150'],
            'tingkat' => ['nullable', 'string', 'max:30'],
            'bilik' => ['nullable', 'string', 'max:60'],
            'id_pegawai_penjaga' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'kapasiti' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status_peti_besi' => ['sometimes', Rule::in(array_keys(Kod::STATUS_PETI_BESI))],
            'tarikh_pemeriksaan_akhir' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        return ['kod_peti_besi' => 'kod peti besi', 'lokasi_bangunan' => 'bangunan', 'id_pegawai_penjaga' => 'pegawai penjaga'];
    }

    /** @return Builder<DaftarPetiBesi> */
    private function entryQuery(Request $request): Builder
    {
        $request->validate([
            'peti_besi_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(Kod::STATUS_DOKUMEN))],
            'tiada_dalam_simpanan' => ['nullable', 'boolean'],
        ]);

        return DaftarPetiBesi::query()
            ->whereHas('hakMilik') // record scope of the title (daerah/mukim/klasifikasi)
            ->with(['petiBesi', 'hakMilik', 'pengeluaranTerbuka.pegawaiKeluar'])
            ->when($request->query('peti_besi_id'), fn ($q, $id) => $q->where('peti_besi_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status_dokumen_asal', $s))
            ->when($request->boolean('tiada_dalam_simpanan'), fn ($q) => $q->where('status_dokumen_asal', '!=', 'dalam_simpanan'))
            ->when($request->query('q'), function ($q, $term) {
                $like = '%'.mb_strtolower((string) $term).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(coalesce(no_fail, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(lokasi_rak, \'\')) like ?', [$like])
                    ->orWhereHas('hakMilik', fn ($h) => $h->whereRaw('lower(no_hak_milik) like ?', [$like])));
            });
    }

    public function entries(Request $request): JsonResponse
    {
        $page = $this->entryQuery($request)->orderBy('peti_besi_id')->orderBy('lokasi_rak')->orderBy('no_fail')->orderBy('id')
            ->paginate(min(100, max(5, (int) $request->query('per_halaman', 25))));

        return response()->json([
            'data' => $page->getCollection()->map(fn (DaftarPetiBesi $d) => $this->present->entry($d))->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function showEntry(DaftarPetiBesi $daftar): JsonResponse
    {
        abort_unless(HakMilik::query()->whereKey($daftar->hak_milik_id)->exists(), 404);
        $daftar->load(['petiBesi', 'hakMilik', 'pengeluaranTerbuka.pegawaiKeluar']);

        return response()->json(['data' => $this->present->entry($daftar) + [
            'sejarah_pengeluaran' => $daftar->pengeluaran()->with(['pegawaiKeluar', 'pegawaiTerimaPulang'])->orderByDesc('tarikh_pengeluaran')->get()
                ->map(fn (PengeluaranDokumen $e) => $this->present->issue($e))->values(),
        ]]);
    }

    public function deposit(Request $request, HakMilik $hakMilik): JsonResponse
    {
        $entry = $this->vault->deposit($hakMilik, $request->all(), $request->user());

        return response()->json(['data' => $this->present->entry($entry->load(['petiBesi', 'hakMilik'])), 'message' => __('hkm::hkm.mesej.dokumen_disimpan')], 201);
    }

    public function updateEntry(Request $request, DaftarPetiBesi $daftar): JsonResponse
    {
        abort_unless(HakMilik::query()->whereKey($daftar->hak_milik_id)->exists(), 404);
        $entry = $this->vault->update($daftar, $request->all());

        return response()->json(['data' => $this->present->entry($entry->load(['petiBesi', 'hakMilik'])), 'message' => __('hkm::hkm.mesej.dikemas_kini')]);
    }

    /** SRS-HKM-04 (acceptance #2): printable inventory per vault, rack and file number. Audited as "cetak". */
    public function inventory(Request $request): Response
    {
        $request->validate(['format' => ['nullable', Rule::in(['pdf', 'xlsx', 'csv'])]]);
        $format = (string) $request->query('format', 'pdf');
        $rows = $this->entryQuery($request)->orderBy('peti_besi_id')->orderBy('lokasi_rak')->orderBy('no_fail')->orderBy('id')->limit(20000)->get();
        $lots = $this->lots->summaries($rows->pluck('lot_id')->all());

        $sections = [];
        foreach ($rows->groupBy('peti_besi_id') as $group) {
            /** @var DaftarPetiBesi $first */
            $first = $group->first();
            $sections[] = [
                'tajuk' => $first->petiBesi->kod_peti_besi.' — '.$first->petiBesi->nama.' ('.$first->petiBesi->lokasi().') · '.$group->count().' dokumen',
                'lajur' => ['Bil.', 'Rak', 'No. fail', 'Kotak/pengikat', 'No. hak milik', 'No. lot', 'Jenis dokumen', 'Status', 'Tarikh masuk', 'Disemak (✓)'],
                'baris' => $group->values()->map(fn (DaftarPetiBesi $d, int $i) => [
                    (string) ($i + 1), $d->lokasi_rak, $d->no_fail, $d->no_kotak_pengikat, $d->hakMilik->no_hak_milik, $lots[$d->lot_id]['no_lot'] ?? null,
                    Kod::JENIS_DOKUMEN_ASAL[$d->jenis_dokumen_asal] ?? $d->jenis_dokumen_asal, Kod::STATUS_DOKUMEN[$d->status_dokumen_asal] ?? $d->status_dokumen_asal,
                    $d->tarikh_masuk_simpanan->format('d/m/Y'), '',
                ])->all(),
            ];
        }
        if ($sections === []) {
            $sections[] = ['tajuk' => __('hkm::hkm.eksport.tiada'), 'lajur' => ['Bil.', 'No. hak milik'], 'baris' => []];
        }

        $this->audit->record('cetak', 'hkm', 'daftar_peti_besi', $request->query('peti_besi_id'), meta: ['klasifikasi' => 'sulit', 'format' => $format, 'bilangan' => $rows->count(), 'penapis' => $request->only(['peti_besi_id', 'status', 'q'])]);

        return Eksport::download($format, 'inventori-peti-besi-'.now()->format('Ymd-His'), __('hkm::hkm.eksport.tajuk_inventori'), $sections,
            __('hkm::hkm.eksport.kaki', ['nama' => $request->user()->name, 'masa' => now()->format('d/m/Y H:i'), 'kriteria' => $rows->count().' dokumen']));
    }
}
