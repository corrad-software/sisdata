<?php

namespace App\Modules\Hkm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Hkm\Http\Present;
use App\Modules\Hkm\Models\DaftarPetiBesi;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PengeluaranDokumen;
use App\Modules\Hkm\Services\VaultService;
use App\Modules\Hkm\Support\Eksport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** SRS-HKM-06/07 — issue/return of original documents, overdue action list. */
class PengeluaranController extends Controller
{
    public function __construct(private VaultService $vault, private Present $present, private AuditLogger $audit) {}

    /** History and the overdue action list (?lewat=1). Optional export (?format=). */
    public function index(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        abort_unless($user->can('hkm.peti_besi.lihat') || $user->can('hkm.pemantauan.lihat'), 403);

        $request->validate([
            'status' => ['nullable', Rule::in([PengeluaranDokumen::DIKELUARKAN, PengeluaranDokumen::DIPULANGKAN])],
            'lewat' => ['nullable', 'boolean'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'hingga' => ['nullable', 'date_format:Y-m-d'],
            'pegawai' => ['nullable', 'integer'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])],
        ]);

        $query = PengeluaranDokumen::query()
            ->whereHas('daftar', fn ($d) => $d->whereHas('hakMilik'))
            ->with(['daftar.petiBesi', 'daftar.hakMilik', 'pegawaiKeluar', 'pegawaiTerimaPulang'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status_pengeluaran', $s))
            ->when($request->boolean('lewat'), fn ($q) => $q->where('status_pengeluaran', PengeluaranDokumen::DIKELUARKAN)->whereDate('tarikh_jangka_pulang', '<', today()))
            ->when($request->query('dari'), fn ($q, $d) => $q->whereDate('tarikh_pengeluaran', '>=', $d))
            ->when($request->query('hingga'), fn ($q, $d) => $q->whereDate('tarikh_pengeluaran', '<=', $d))
            ->when($request->query('pegawai'), fn ($q, $p) => $q->where('id_pegawai_keluar', $p))
            ->when($request->query('tujuan'), fn ($q, $t) => $q->whereRaw('lower(tujuan_pengeluaran) like ?', ['%'.mb_strtolower((string) $t).'%']))
            ->orderBy($request->boolean('lewat') ? 'tarikh_jangka_pulang' : 'tarikh_pengeluaran', $request->boolean('lewat') ? 'asc' : 'desc');

        $row = fn (PengeluaranDokumen $e) => $this->present->issue($e) + [
            'kod_peti_besi' => $e->daftar->petiBesi->kod_peti_besi,
            'no_fail' => $e->daftar->no_fail,
            'lokasi_rak' => $e->daftar->lokasi_rak,
            'hak_milik_id' => $e->daftar->hak_milik_id,
            'no_hak_milik' => $e->daftar->hakMilik->no_hak_milik,
        ];

        if ($request->filled('format')) {
            $rows = $query->limit(20000)->get();
            $this->audit->record('eksport', 'hkm', 'pengeluaran_dokumen', null, meta: ['klasifikasi' => 'sulit', 'format' => $request->query('format'), 'bilangan' => $rows->count()]);

            return Eksport::download((string) $request->query('format'), 'pengeluaran-dokumen-asal-'.now()->format('Ymd'), __('hkm::hkm.eksport.tajuk_pengeluaran'), [[
                'tajuk' => $request->boolean('lewat') ? __('hkm::hkm.eksport.lewat') : __('hkm::hkm.eksport.sejarah'),
                'lajur' => ['Peti besi', 'No. fail', 'No. hak milik', 'Dikeluarkan', 'Oleh', 'Penerima', 'Tujuan', 'Jangka pulang', 'Pulang', 'Keadaan', 'Lewat (hari)'],
                'baris' => $rows->map(fn (PengeluaranDokumen $e) => [
                    $e->daftar->petiBesi->kod_peti_besi, $e->daftar->no_fail, $e->daftar->hakMilik->no_hak_milik, $e->tarikh_pengeluaran->format('d/m/Y'),
                    $e->pegawaiKeluar?->name, $e->penerima_nama, $e->tujuan_pengeluaran, $e->tarikh_jangka_pulang->format('d/m/Y'),
                    $e->tarikh_pulang_sebenar?->format('d/m/Y'), $e->keadaan_dokumen_pulang, $e->lewat() ? (string) $e->tarikh_jangka_pulang->diffInDays(today()) : '',
                ])->all(),
            ]], __('hkm::hkm.eksport.kaki', ['nama' => $user->name, 'masa' => now()->format('d/m/Y H:i'), 'kriteria' => $rows->count().' rekod']));
        }

        $page = $query->paginate(min(100, max(5, (int) $request->query('per_halaman', 25))));

        return response()->json([
            'data' => $page->getCollection()->map($row)->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function issue(Request $request, DaftarPetiBesi $daftar): JsonResponse
    {
        abort_unless(HakMilik::query()->whereKey($daftar->hak_milik_id)->exists(), 404);
        $issue = $this->vault->issue($daftar, $request->all(), $request->user());

        return response()->json(['data' => $this->present->issue($issue->load('pegawaiKeluar')), 'message' => __('hkm::hkm.mesej.dikeluarkan')], 201);
    }

    public function return(Request $request, PengeluaranDokumen $pengeluaran): JsonResponse
    {
        abort_unless(HakMilik::query()->whereKey($pengeluaran->daftar->hak_milik_id)->exists(), 404);
        $issue = $this->vault->return($pengeluaran, $request->all(), $request->user());

        return response()->json(['data' => $this->present->issue($issue->load(['pegawaiKeluar', 'pegawaiTerimaPulang'])), 'message' => __('hkm::hkm.mesej.dipulangkan')]);
    }
}
