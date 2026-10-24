<?php

namespace App\Modules\Kes\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tabular downloads for KES reports (SRS-KES-24) — same format as MOD-ADU /
 * MOD-HKM: CSV, Excel or PDF. The footer carries the user and timestamp
 * (dynamic watermark on prints of Rahsia material). Sections are
 * `['tajuk' => ..., 'lajur' => [headers], 'baris' => [[cells]]]`.
 */
class Eksport
{
    /**
     * @param  list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>  $sections
     */
    public static function download(string $format, string $filename, string $title, array $sections, string $footer, bool $landscape = true): Response
    {
        [$body, $type, $ext] = match ($format) {
            'pdf' => [self::pdf($title, $sections, $footer, $landscape), 'application/pdf', 'pdf'],
            'xlsx' => [self::xlsx($title, $sections, $footer), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
            default => [self::csv($sections), 'text/csv; charset=UTF-8', 'csv'],
        };

        return new Response($body, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$ext.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @param list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}> $sections */
    public static function csv(array $sections): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($sections as $section) {
            fputcsv($out, $section['lajur'], escape: '\\');
            foreach ($section['baris'] as $row) {
                // Neutralise spreadsheet formula injection.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row), escape: '\\');
            }
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /** @param list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}> $sections */
    public static function xlsx(string $title, array $sections, string $footer): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle($title)->setCreator('SISDATA');
        $book->removeSheetByIndex(0);

        foreach ($sections as $i => $section) {
            $sheet = $book->createSheet($i);
            $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $section['tajuk']) ?: 'Helaian', 0, 31));
            $sheet->setCellValue('A1', $title);
            $sheet->setCellValue('A2', $footer);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            foreach ($section['lajur'] as $c => $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 1).'4', $header);
            }
            $last = Coordinate::stringFromColumnIndex(max(1, count($section['lajur'])));
            $sheet->getStyle("A4:{$last}4")->getFont()->setBold(true);
            foreach ($section['baris'] as $r => $row) {
                foreach ($row as $c => $value) {
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).($r + 5), $value === null ? '' : (string) $value, DataType::TYPE_STRING);
                }
            }
            foreach (range(1, max(1, count($section['lajur']))) as $c) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
            }
            $sheet->freezePane('A5');
        }
        $book->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}> $sections */
    public static function html(string $title, array $sections, string $footer): string
    {
        $html = '<!doctype html><html lang="ms"><head><meta charset="utf-8"><title>'.e($title).'</title><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#18181b}'
            .'h1{font-size:13px;margin:0 0 2px}h2{font-size:10px;margin:14px 0 4px}p{margin:0 0 6px;color:#52525b}'
            .'table{width:100%;border-collapse:collapse}th,td{border:1px solid #d4d4d8;padding:2px 3px;text-align:left;vertical-align:top}'
            .'th{background:#f4f4f5}.tt{margin-top:18px}.tt td{border:none;padding:14px 8px 0 0;width:33%}'
            .'.wm{position:fixed;top:40%;left:5%;width:90%;text-align:center;font-size:26px;color:#dc2626;opacity:0.12;transform:rotate(-20deg)}'
            .'</style></head><body>';
        $html .= '<div class="wm">RAHSIA · '.e($footer).'</div><h1>'.e($title).'</h1><p>RAHSIA — '.e($footer).'</p>';
        foreach ($sections as $section) {
            $html .= '<h2>'.e($section['tajuk']).'</h2><table><thead><tr>';
            foreach ($section['lajur'] as $header) {
                $html .= '<th>'.e($header).'</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($section['baris'] as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>'.e($value === null ? '' : (string) $value).'</td>';
                }
                $html .= '</tr>';
            }
            if ($section['baris'] === []) {
                $html .= '<tr><td colspan="'.count($section['lajur']).'">Tiada rekod.</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        $html .= '<table class="tt"><tr><td>Disemak oleh: ____________________</td><td>Tarikh: ____________</td><td>Tandatangan: ____________________</td></tr></table>';

        return $html.'</body></html>';
    }

    /** @param list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}> $sections */
    public static function pdf(string $title, array $sections, string $footer, bool $landscape = true): string
    {
        return Pdf::loadHTML(self::html($title, $sections, $footer))->setPaper('a4', $landscape ? 'landscape' : 'portrait')->output();
    }
}
