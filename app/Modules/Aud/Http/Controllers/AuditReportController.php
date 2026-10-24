<?php

namespace App\Modules\Aud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Services\AuditReports;
use App\Modules\Aud\Services\TrailAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** SRS-AUD-15 automatic audit reports. */
class AuditReportController extends Controller
{
    public function show(Request $request, string $jenis, AuditReports $reports, TrailAuditLogger $logger): JsonResponse
    {
        $type = $jenis;
        $request->merge(['jenis' => $type]);
        $validated = $request->validate([
            'jenis' => ['required', Rule::in(AuditReports::TYPES)],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'hingga' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);

        $today = Carbon::now((string) config('aud.zon_waktu_tempatan'));
        $from = $validated['dari'] ?? $today->copy()->subDays(30)->format('Y-m-d');
        $to = $validated['hingga'] ?? $today->format('Y-m-d');

        $report = $reports->generate($type, $from, $to);

        $logger->record('lihat_laporan', 'aud', null, null, 'berjaya', null, [], [
            'jenis' => $type, 'dari' => $from, 'hingga' => $to, 'jumlah' => $report['jumlah'],
        ]);

        return response()->json(['data' => $report]);
    }
}
