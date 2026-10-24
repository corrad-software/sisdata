<?php

namespace App\Modules\Pro\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tabular export in Excel, CSV, PDF and JSON (SRS-PRO-18) — used for lot
 * lists, import reports, the data-quality report and the data dictionary.
 * Callers decide WHICH columns (role + classification); this only renders.
 */
class Pengeksport
{
    public const FORMATS = ['csv', 'xlsx', 'pdf', 'json'];

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, string>  $columns  key => Malay header
     */
    public function download(iterable $rows, array $columns, string $format, string $basename, string $title): Response
    {
        $format = strtolower($format);
        if (! in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages(['format' => [__('pro::pro.eksport.format_tidak_sah')]]);
        }
        $file = $basename.'-'.now()->format('Ymd-His').'.'.$format;

        return match ($format) {
            'json' => response()->json([
                'tajuk' => $title,
                'dijana_pada' => now()->toIso8601String(),
                'lajur' => $columns,
                'data' => array_map(fn ($r) => array_intersect_key($r, $columns), is_array($rows) ? $rows : iterator_to_array($rows, false)),
            ], 200, ['Content-Disposition' => 'attachment; filename="'.$file.'"']),
            'csv' => $this->csv($rows, $columns, $file),
            'xlsx' => $this->xlsx($rows, $columns, $file, $title),
            'pdf' => $this->pdf($rows, $columns, $file, $title),
        };
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, string>  $columns
     */
    private function csv(iterable $rows, array $columns, string $file): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
            fputcsv($out, array_values($columns), escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($k) => $this->cell($row[$k] ?? null), array_keys($columns)), escape: '');
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, string>  $columns
     */
    private function xlsx(iterable $rows, array $columns, string $file, string $title): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title) ?? 'Data', 0, 31));
        $sheet->fromArray(array_values($columns), null, 'A1');
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_map(fn ($k) => $this->cell($row[$k] ?? null), array_keys($columns)), null, 'A'.$r++, true);
        }

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, string>  $columns
     */
    private function pdf(iterable $rows, array $columns, string $file, string $title): Response
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:8px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:3px;text-align:left;vertical-align:top}th{background:#eee}h1{font-size:13px}</style></head><body>';
        $html .= '<h1>'.$e($title).'</h1><p>'.$e(__('pro::pro.eksport.dijana_pada', ['masa' => now()->format('d/m/Y H:i')])).'</p><table><thead><tr>';
        foreach ($columns as $label) {
            $html .= '<th>'.$e($label).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach (array_keys($columns) as $key) {
                $html .= '<td>'.$e($this->cell($row[$key] ?? null)).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        return Pdf::loadHTML($html)->setPaper('a4', count($columns) > 6 ? 'landscape' : 'portrait')->download($file);
    }

    private function cell(mixed $value): string|int|float|null
    {
        return match (true) {
            is_bool($value) => $value ? 'Ya' : 'Tidak',
            is_array($value) => implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : (string) json_encode($v), $value)),
            is_int($value), is_float($value), $value === null => $value,
            default => (string) $value,
        };
    }
}
