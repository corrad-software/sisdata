<?php

namespace App\Modules\Gis\Support;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV / Excel download of a result table (SRS-GIS-20 lot lists, QA and import reports). */
final class EksportJadual
{
    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, string>  $columns  key => Malay header
     */
    public static function download(iterable $rows, array $columns, string $format, string $basename): StreamedResponse
    {
        $format = strtolower($format);
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['format' => [__('gis::gis.eksport.format_tidak_sah')]]);
        }
        $file = $basename.'-'.now()->format('Ymd-His').'.'.$format;
        $cell = fn ($v) => is_bool($v) ? ($v ? 'Ya' : 'Tidak') : (is_scalar($v) || $v === null ? $v : json_encode($v, JSON_UNESCAPED_UNICODE));

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows, $columns, $cell) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_values($columns), escape: '');
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($k) => $cell($row[$k] ?? null), array_keys($columns)), escape: '');
                }
                fclose($out);
            }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($rows, $columns, $cell) {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->fromArray(array_values($columns), null, 'A1');
            $r = 2;
            foreach ($rows as $row) {
                $sheet->fromArray(array_map(fn ($k) => $cell($row[$k] ?? null), array_keys($columns)), null, 'A'.$r++);
            }
            (new Xlsx($book))->save('php://output');
        }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
