<?php

namespace App\Modules\Tsu\Http\Controllers;

use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Tsu\Services\LaporanService;
use App\Modules\Tsu\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** SRS-TSU-26/27: management and works reports (+ CSV export, audited). */
class LaporanController extends TsuController
{
    public function __construct(private LaporanService $service) {}

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'kod_daerah' => ['nullable', 'string', 'size:4'], 'kod_mukim' => ['nullable', 'string', 'size:6'], 'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'jenis_urusan' => ['nullable', Rule::in(array_keys(Lalai::JENIS_URUSAN))], 'status' => ['nullable', 'string', 'max:30'],
            'jurukur_id' => ['nullable', 'integer'], 'ukur' => ['nullable', 'boolean'],
        ]);
    }

    public function ringkasan(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->ringkasan($this->user($request), $this->filters($request))]);
    }

    public function kerja(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->kerja($this->user($request), $this->filters($request))]);
    }

    public function eksportKerja(Request $request, AuditLogger $audit): StreamedResponse
    {
        $f = $this->filters($request);
        $rows = $this->service->kerja($this->user($request), $f);
        $audit->record('eksport', 'tsu', 'urusan_tanah', null, meta: ['laporan' => 'kerja', 'penapis' => $f, 'bilangan' => count($rows)]);
        $cols = ['no_rujukan_mais' => 'No. rujukan', 'jenis_nama' => 'Jenis urusan', 'kod_daerah' => 'Daerah', 'kod_mukim' => 'Mukim', 'status_nama' => 'Status', 'peringkat_semasa' => 'Peringkat semasa', 'tarikh_mula' => 'Tarikh mula', 'tarikh_siap' => 'Tarikh siap', 'tempoh_hari' => 'Tempoh (hari)', 'jurukur' => 'Jurukur', 'jumlah_kos' => 'Jumlah kos (RM)', 'status_ptd' => 'Status PTD'];

        return response()->streamDownload(function () use ($rows, $cols) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($cols), escape: '\\');
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($k) => is_bool($r[$k]) ? ($r[$k] ? 'Ya' : 'Tidak') : $r[$k], array_keys($cols)), escape: '\\');
            }
            fclose($out);
        }, 'laporan-urusan-tanah-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
