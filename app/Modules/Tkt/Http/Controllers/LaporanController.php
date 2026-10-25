<?php

namespace App\Modules\Tkt\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Tkt\Models\AktivitiSokongan;
use App\Modules\Tkt\Models\Eskalasi;
use App\Modules\Tkt\Models\LaporanBulanan;
use App\Modules\Tkt\Services\Analitik;
use App\Modules\Tkt\Services\KalendarSokongan;
use App\Modules\Tkt\Services\LaporanBulananService;
use App\Modules\Tkt\Support\Eksport;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** SRS-TKT-17 dashboard, SRS-TKT-19 / §18.6 monthly report (PDF / Excel), §18.5 escalation report, support activities. */
class LaporanController extends Controller
{
    public function __construct(private Analitik $analitik, private AuditLogger $audit) {}

    public function papanPemuka(Request $request): JsonResponse
    {
        $f = $request->validate(['dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date'], 'modul' => ['nullable', 'string', 'max:20']]);

        return response()->json(['data' => $this->analitik->papanPemuka($f)]);
    }

    public function bulanan(Request $request): JsonResponse
    {
        $bulan = $this->bulan($request);
        $report = $this->analitik->laporanBulanan($bulan);
        $saved = LaporanBulanan::query()->where('bulan', $bulan)->first();

        return response()->json(['data' => $report + [
            'bulan' => $bulan,
            'senarai_bulan' => $this->analitik->senaraiBulan(),
            'arkib' => $saved === null ? null : ['dijana_pada' => $saved->dijana_pada->toIso8601String(), 'id_dokumen_pdf' => $saved->id_dokumen_pdf, 'id_dokumen_xlsx' => $saved->id_dokumen_xlsx, 'ralat' => $saved->ralat],
        ]]);
    }

    public function eksport(Request $request, LaporanBulananService $service): Response
    {
        $bulan = $this->bulan($request);
        $format = (string) ($request->validate(['format' => ['nullable', Rule::in(['pdf', 'xlsx', 'csv'])]])['format'] ?? 'pdf');
        $report = $this->analitik->laporanBulanan($bulan);
        $this->audit->record('eksport', 'tkt', 'tkt_laporan_bulanan', null, meta: ['bulan' => $bulan, 'format' => $format]);

        return Eksport::download($format, 'laporan-sokongan-'.$bulan, $service->tajuk($bulan), $report['seksyen'], (string) __('tkt::tkt.laporan.dijana', ['masa' => now()->setTimezone(KalendarSokongan::TZ)->format('d/m/Y H:i')]));
    }

    /** Re-generate and file the monthly report now (the scheduler does it on the 1st). */
    public function jana(Request $request, LaporanBulananService $service): JsonResponse
    {
        $bulan = $this->bulan($request);
        $row = $service->jana($bulan, $request->user());
        $this->audit->record('jana', 'tkt', 'tkt_laporan_bulanan', $row->id, meta: ['bulan' => $bulan]);

        return response()->json(['data' => ['bulan' => $row->bulan, 'dijana_pada' => $row->dijana_pada->toIso8601String(), 'id_dokumen_pdf' => $row->id_dokumen_pdf, 'id_dokumen_xlsx' => $row->id_dokumen_xlsx, 'ralat' => $row->ralat], 'message' => __('tkt::tkt.mesej.laporan_dijana', ['bulan' => $bulan])]);
    }

    public function arkib(): JsonResponse
    {
        return response()->json(['data' => LaporanBulanan::query()->orderByDesc('bulan')->limit(36)->get()->map(fn (LaporanBulanan $l) => [
            'bulan' => $l->bulan, 'dijana_pada' => $l->dijana_pada->toIso8601String(), 'ringkasan' => $l->ringkasan,
            'ada_pdf' => $l->id_dokumen_pdf !== null, 'ada_xlsx' => $l->id_dokumen_xlsx !== null, 'ralat' => $l->ralat,
        ])->values()]);
    }

    public function muatTurunArkib(Request $request, string $bulan, DocumentStore $documents): JsonResponse
    {
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'pdf';
        $row = LaporanBulanan::query()->where('bulan', $bulan)->firstOrFail();
        $id = $format === 'xlsx' ? $row->id_dokumen_xlsx : $row->id_dokumen_pdf;
        $url = $id !== null ? $documents->temporaryUrl($id, $request->user()) : null;
        abort_if($url === null, 404);

        return response()->json(['data' => ['url' => $url]]);
    }

    /** §18.5: escalations in a period — ticket, level, reason, recipient. */
    public function eskalasi(Request $request): JsonResponse
    {
        [$from, $to] = $this->analitik->tempoh($request->validate(['dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date']]));
        $rows = Eskalasi::query()->with(['penerima', 'tiket'])->whereBetween('cap_masa', [$from->utc(), $to->utc()])->orderByDesc('cap_masa')->limit(500)->get()
            ->map(fn (Eskalasi $e) => [
                'cap_masa' => $e->cap_masa->toIso8601String(), 'id_tiket' => $e->id_tiket, 'nombor_rujukan' => $e->tiket?->nombor_rujukan,
                'keterukan' => $e->tiket?->keterukan, 'peringkat' => $e->peringkat, 'sebab' => __('tkt::tkt.sebab_eskalasi.'.$e->sebab),
                'sasaran' => __('tkt::tkt.sasaran.'.$e->sasaran), 'penerima' => $e->penerima->name ?? $e->jenis_penerima,
            ])->values();

        return response()->json(['data' => $rows]);
    }

    public function aktiviti(Request $request): JsonResponse
    {
        $f = $request->validate(['jenis' => ['nullable', Rule::in(array_keys(Lalai::JENIS_AKTIVITI))], 'bulan' => ['nullable', 'date_format:Y-m']]);

        return response()->json(['data' => AktivitiSokongan::query()
            ->when($f['jenis'] ?? null, fn ($q, $j) => $q->where('jenis', $j))
            ->when($f['bulan'] ?? null, fn ($q, $b) => $q->whereBetween('tarikh', [$b.'-01', date('Y-m-t', (int) strtotime($b.'-01'))]))
            ->orderByDesc('tarikh')->orderByDesc('id')->limit(200)->get()
            ->map(fn (AktivitiSokongan $a) => [
                'id' => $a->id, 'jenis' => $a->jenis, 'jenis_nama' => Lalai::JENIS_AKTIVITI[$a->jenis] ?? $a->jenis, 'tarikh' => $a->tarikh->toDateString(),
                'tajuk' => $a->tajuk, 'keterangan' => $a->keterangan, 'tempoh_minit' => $a->tempoh_minit, 'punca_akar' => $a->punca_akar, 'rujukan' => $a->rujukan, 'sintetik' => $a->sintetik,
            ])->values()]);
    }

    public function simpanAktiviti(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(Lalai::JENIS_AKTIVITI))],
            'tarikh' => ['required', 'date'],
            'tajuk' => ['required', 'string', 'max:200'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
            'tempoh_minit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'punca_akar' => ['nullable', 'string', 'max:1000'],
            'rujukan' => ['nullable', 'string', 'max:120'],
        ]);
        $row = AktivitiSokongan::query()->create($data + ['id_pengguna' => $request->user()->id]);

        return response()->json(['data' => ['id' => $row->id], 'message' => __('tkt::tkt.mesej.aktiviti')], 201);
    }

    private function bulan(Request $request): string
    {
        $data = $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        return $data['bulan'] ?? now()->setTimezone(KalendarSokongan::TZ)->format('Y-m');
    }
}
