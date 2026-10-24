<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pjk\Integration\Kpi\KadarKutipan;
use App\Modules\Pjk\Integration\Kpi\KadarPenghunian;
use App\Modules\Pjk\Integration\Kpi\KontrakHampirTamat;
use App\Modules\Pjk\Integration\Kpi\Tunggakan;
use App\Modules\Pjk\Services\Laporan;
use App\Modules\Pjk\Support\Dokumen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** SRS-PJK-26/27: reports (JSON, PDF, Excel) and the module dashboard. */
class LaporanController
{
    public function __construct(private Laporan $laporan, private AuditLogger $audit) {}

    public function show(Request $request, string $jenis): JsonResponse
    {
        abort_unless(isset(Laporan::JENIS[$jenis]), 404);

        return response()->json(['data' => $this->laporan->build($jenis, $request->query())]);
    }

    public function export(Request $request, string $jenis): Response
    {
        abort_unless(isset(Laporan::JENIS[$jenis]), 404);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'pdf';
        $r = $this->laporan->build($jenis, $request->query());
        $this->audit->record('eksport', 'pjk', 'laporan', $jenis, meta: ['format' => $format, 'penapis' => $request->query(), 'bil_baris' => count($r['baris']), 'klasifikasi' => 'sulit']);
        $name = 'laporan-pjk-'.$jenis.'-'.now()->format('Ymd').'.'.$format;
        if ($format === 'xlsx') {
            return Dokumen::download(Dokumen::xlsx($jenis, $r['kepala'], $r['baris']), $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        return Dokumen::download(Dokumen::pdf($r['tajuk'], [['kepala' => $r['kepala'], 'jadual' => array_map(fn ($row) => array_map(fn ($c) => (string) ($c ?? ''), $row), $r['baris'])]], null, true), $name, 'application/pdf');
    }

    /** Module dashboard: the same KPIs registered with MOD-DSH + occupancy summary (SRS-PJK-26). */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filters = array_filter(['kod_daerah' => (string) $request->query('kod_daerah', ''), 'bahagian' => (string) $request->query('bahagian', '')]);
        $kpi = [];
        foreach ([KadarKutipan::class, Tunggakan::class, KadarPenghunian::class, KontrakHampirTamat::class] as $class) {
            $p = app($class);
            $kpi[] = ['kod' => $p->key(), 'label' => $p->label(), 'domain' => $p->domain()] + $p->compute($user, $filters);
        }

        return response()->json(['data' => ['kpi' => $kpi, 'penghunian' => $this->laporan->penghunian($request->query())]]);
    }
}
