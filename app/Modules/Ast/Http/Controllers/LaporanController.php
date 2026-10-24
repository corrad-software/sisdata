<?php

namespace App\Modules\Ast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ast\Services\LaporanService;
use App\Modules\Aud\Contracts\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** SRS-AST-24..26 reports (viewer's scope), with CSV export (audited). */
class LaporanController extends Controller
{
    private const PENAPIS = ['kod_daerah', 'kod_mukim', 'id_kategori', 'status_kondisi', 'jenis_aset', 'tag', 'tahun', 'bilangan_tahun', 'hari', 'dari', 'hingga', 'id_aset', 'termasuk_arkib'];

    public function __construct(private LaporanService $laporan) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => array_map(fn ($k, $v) => ['kod' => $k, 'nama' => $v], array_keys(LaporanService::LAPORAN), LaporanService::LAPORAN)]);
    }

    public function show(Request $request, string $laporan): JsonResponse
    {
        abort_unless(array_key_exists($laporan, LaporanService::LAPORAN), 404);

        return response()->json(['data' => $this->laporan->jana($laporan, $request->user(), $request->only(self::PENAPIS))]);
    }

    public function eksport(Request $request, string $laporan, AuditLogger $audit): StreamedResponse
    {
        abort_unless(array_key_exists($laporan, LaporanService::LAPORAN), 404);
        $f = $request->only(self::PENAPIS);
        $r = $this->laporan->jana($laporan, $request->user(), $f);
        $audit->record('eksport', 'ast', 'laporan', $laporan, meta: ['penapis' => $f, 'bilangan' => count($r['baris']), 'format' => 'csv']);
        $name = 'laporan-aset-'.$laporan.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($r) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$r['tajuk']], escape: '');
            foreach ($r['ringkasan'] ?? [] as $s) {
                fputcsv($out, [$s['label'], (string) $s['nilai']], escape: '');
            }
            fputcsv($out, [], escape: '');
            fputcsv($out, array_column($r['lajur'], 'label'), escape: '');
            foreach ($r['baris'] as $row) {
                fputcsv($out, array_map(fn ($c) => self::sel($row[$c['kod']] ?? null), $r['lajur']), escape: '');
            }
            foreach ($r['kumpulan'] ?? [] as $nama => $kumpulan) {
                fputcsv($out, [], escape: '');
                fputcsv($out, ['Pecahan: '.$nama, 'Bilangan', 'Nilai (RM)'], escape: '');
                foreach ($kumpulan as $k) {
                    fputcsv($out, [$k['label'], $k['bilangan'], $k['nilai']], escape: '');
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** CSV-injection safe cell. */
    private static function sel(mixed $v): string
    {
        $s = $v === null ? '' : (string) $v;

        return $s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($s) ? "'".$s : $s;
    }
}
