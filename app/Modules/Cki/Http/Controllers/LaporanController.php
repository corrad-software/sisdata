<?php

namespace App\Modules\Cki\Http\Controllers;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Cki\Integration\Kpi\BayarTepatMasa;
use App\Modules\Cki\Integration\Kpi\CukaiTertunggak;
use App\Modules\Cki\Integration\Kpi\JumlahCukaiTahunan;
use App\Modules\Cki\Integration\Kpi\Penalti;
use App\Modules\Cki\Services\ImportService;
use App\Modules\Cki\Services\Laporan;
use App\Modules\Cki\Support\Dokumen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** SRS-CKI-21/22/23: dashboard and reports (JSON / PDF / Excel); Excel bill import. */
class LaporanController
{
    public function __construct(private Laporan $laporan, private AuditLogger $audit) {}

    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $f = array_filter($request->only(['kod_daerah', 'kod_mukim', 'kod_pbt', 'kod_agensi', 'jenis_cukai', 'tahun', 'dari', 'hingga']), fn ($v) => $v !== null && $v !== '');
        $kpi = [];
        foreach ([JumlahCukaiTahunan::class, CukaiTertunggak::class, BayarTepatMasa::class, Penalti::class] as $class) {
            $p = app($class);
            $kpi[] = ['kod' => $p->key(), 'label' => $p->label()] + $p->compute($user, array_intersect_key($f, ['kod_daerah' => 1, 'tahun' => 1]));
        }

        return response()->json(['data' => ['kpi' => $kpi] + $this->laporan->dashboard($user, $f)]);
    }

    public function show(Request $request, string $jenis): JsonResponse
    {
        abort_unless(isset(Laporan::JENIS[$jenis]), 404);

        return response()->json(['data' => $this->laporan->build($jenis, $request->user(), $request->query())]);
    }

    public function export(Request $request, string $jenis): Response
    {
        abort_unless(isset(Laporan::JENIS[$jenis]), 404);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'pdf';
        $r = $this->laporan->build($jenis, $request->user(), $request->query());
        $this->audit->record('eksport', 'cki', 'laporan', $jenis, meta: ['format' => $format, 'penapis' => $request->query(), 'bil_baris' => count($r['baris']), 'klasifikasi' => 'dalaman']);
        $name = 'laporan-cki-'.$jenis.'-'.now()->format('Ymd').'.'.$format;
        if ($format === 'xlsx') {
            return Dokumen::download(Dokumen::xlsx($r['tajuk'], $r['kepala'], $r['baris']), $name, Dokumen::XLSX);
        }

        return Dokumen::download(Dokumen::pdf($r['tajuk'], [['kepala' => $r['kepala'], 'jadual' => array_map(fn ($row) => array_map(fn ($c) => (string) ($c ?? ''), $row), $r['baris'])]], true), $name, 'application/pdf');
    }

    public function import(Request $request, ImportService $import): JsonResponse
    {
        $request->validate(['fail' => 'required|file|max:10240|mimes:xlsx,xls,csv,txt', 'dry_run' => 'nullable|boolean']);

        return response()->json(['data' => $import->import($request->file('fail'), $request->user(), $request->boolean('dry_run'))]);
    }

    public function template(): Response
    {
        return Dokumen::download(Dokumen::xlsx('Templat bil cukai', ImportService::KOLUM, [
            ['tanah', 'CT10080000123', '2026', 'TA-2026-00000123', '245.00', '0.00', '2026-01-02', '2026-05-31', 'belum_bayar', '', ''],
        ]), 'templat-import-bil-cukai.xlsx', Dokumen::XLSX);
    }
}
