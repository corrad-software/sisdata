<?php

namespace App\Modules\Per\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Services\Reports;
use App\Modules\Per\Support\ChannelCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** /api/v1/per/laporan — SRS-PER-27/28 reports and statistics. */
class LaporanController extends Controller
{
    public function __construct(private Reports $reports, private AuditLogger $audit) {}

    public function summary(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->summary($this->filters($request))]);
    }

    public function special(Request $request, string $jenis): JsonResponse
    {
        abort_unless(in_array($jenis, Reports::SPECIAL, true), 404);

        return response()->json(['data' => $this->reports->special($jenis, $this->filters($request))]);
    }

    /** GET /laporan/eksport — CSV of the filtered cases (explicitly audited). */
    public function export(Request $request): StreamedResponse
    {
        $f = $this->filters($request);
        $rows = $this->reports->query($f)->with(['saluran', 'peringkat'])->orderBy('tarikh_mula_kes')->limit(10000)->get();

        $this->audit->record('eksport', 'per', 'pemerolehan', null, meta: ['penapis' => $f, 'bilangan' => $rows->count(), 'format' => 'csv', 'klasifikasi' => 'sulit']);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No. Kes', 'Tajuk', 'Saluran', 'Daerah', 'Status', 'Peringkat Semasa', 'Tarikh Mula', 'Tarikh Selesai', 'Nilai Pemerolehan (RM)', 'Sumber Dana', 'Tempoh (hari)', 'Sasaran (hari)'], escape: '\\');
            foreach ($rows as $c) {
                $r = $this->reports->row($c);
                fputcsv($out, [
                    $c->no_kes, $c->tajuk, $r['nama_saluran'], $r['nama_daerah'], __('per::per.status.'.$c->status_pemerolehan), $r['peringkat_semasa'],
                    $r['tarikh_mula_kes'], $c->tarikh_selesai?->toDateString(), $c->nilai_pemerolehan, $c->sumber_dana, $c->tempoh_keseluruhan_hari, $c->tempoh_sasaran_hari,
                ], escape: '\\');
            }
            fclose($out);
        }, 'laporan-pemerolehan-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'saluran' => ['nullable', Rule::in(ChannelCatalog::codes())],
            'daerah' => ['nullable', 'regex:/^\d{4}$/'],
            'status' => ['nullable', Rule::in(Pemerolehan::STATUS)],
            'bahagian' => ['nullable', Rule::in(array_keys(ChannelCatalog::BAHAGIAN))],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'hingga' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);
    }
}
