<?php

namespace App\Modules\Ptn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ptn\Services\Analitik;
use App\Modules\Ptn\Support\Eksport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** SRS-PTN-22/23 + the module's report list; export audited (SRS-GEN-02). */
class LaporanController extends Controller
{
    public function __construct(private Analitik $analitik) {}

    /** @return array<string, mixed> */
    private function penapis(Request $request): array
    {
        return $request->validate([
            'kod_daerah' => ['nullable', 'string'], 'kod_mukim' => ['nullable', 'string'], 'dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date'],
            'tahun' => ['nullable', 'integer'], 'kategori_isu' => ['nullable', 'string'], 'status_isu' => ['nullable', 'string'], 'tahap_keterukan' => ['nullable', 'string'],
            'kod_warna' => ['nullable', 'in:hijau,kuning,merah'], 'status' => ['nullable', 'string'],
        ]);
    }

    /** @return array<string, mixed> */
    private function jana(Request $request, string $laporan): array
    {
        abort_unless(array_key_exists($laporan, Analitik::LAPORAN), 404);

        return $this->analitik->{$laporan}($request->user(), $this->penapis($request));
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => array_map(fn ($k, $v) => ['kod' => $k, 'nama' => $v], array_keys(Analitik::LAPORAN), Analitik::LAPORAN)]);
    }

    public function show(Request $request, string $laporan): JsonResponse
    {
        return response()->json(['data' => $this->jana($request, $laporan)]);
    }

    public function eksport(Request $request, string $laporan, AuditLogger $audit): Response
    {
        $data = $this->jana($request, $laporan);
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'csv';
        $audit->record('eksport', 'ptn', 'laporan', $laporan, meta: ['format' => $format, 'penapis' => $this->penapis($request)]);

        return Eksport::download($format, 'ptn-'.$laporan.'-'.now()->format('Ymd'), 'MOD-PTN · '.Analitik::LAPORAN[$laporan], $this->analitik->seksyen($laporan, $data), 'Dijana '.now()->format('d/m/Y H:i').' oleh '.$request->user()->name.' · SISDATA MAIS');
    }
}
