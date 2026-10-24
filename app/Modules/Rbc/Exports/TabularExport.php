<?php

namespace App\Modules\Rbc\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns report tables into Excel (phpoffice/phpspreadsheet) or PDF
 * (barryvdh/laravel-dompdf) downloads (SRS-RBC-10, SRS-RBC-26). Each sheet /
 * section is `['tajuk' => ..., 'lajur' => [headers], 'baris' => [[cells]]]`.
 */
class TabularExport
{
    /**
     * @param  list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>  $sections
     */
    public static function xlsxBinary(string $title, array $sections, string $generatedBy): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle($title)->setCreator('SISDATA')->setDescription($generatedBy);
        $book->removeSheetByIndex(0);

        foreach ($sections as $i => $section) {
            $sheet = $book->createSheet($i);
            $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $section['tajuk']) ?: 'Helaian', 0, 31));

            $sheet->setCellValue('A1', $title.' — '.$section['tajuk']);
            $sheet->setCellValue('A2', $generatedBy);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

            foreach ($section['lajur'] as $c => $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 1).'4', $header);
            }
            $last = Coordinate::stringFromColumnIndex(max(1, count($section['lajur'])));
            $sheet->getStyle("A4:{$last}4")->getFont()->setBold(true);

            foreach ($section['baris'] as $r => $row) {
                foreach ($row as $c => $value) {
                    $sheet->setCellValueExplicit(
                        Coordinate::stringFromColumnIndex($c + 1).($r + 5),
                        $value === null ? '' : (string) $value,
                        DataType::TYPE_STRING,
                    );
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

    /**
     * @param  list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>  $sections
     */
    public static function html(string $title, array $sections, string $generatedBy): string
    {
        $html = '<!doctype html><html lang="ms"><head><meta charset="utf-8"><title>'.e($title).'</title><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#18181b}'
            .'h1{font-size:13px;margin:0 0 2px}h2{font-size:10px;margin:14px 0 4px}p{margin:0 0 6px;color:#52525b}'
            .'table{width:100%;border-collapse:collapse}th,td{border:1px solid #d4d4d8;padding:2px 3px;text-align:left;vertical-align:top}'
            .'th{background:#f4f4f5}td.c{text-align:center}'
            .'</style></head><body>';
        $html .= '<h1>'.e($title).'</h1><p>'.e($generatedBy).'</p>';

        foreach ($sections as $section) {
            $html .= '<h2>'.e($section['tajuk']).'</h2><table><thead><tr>';
            foreach ($section['lajur'] as $header) {
                $html .= '<th>'.e($header).'</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($section['baris'] as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $text = $value === null ? '' : (string) $value;
                    $html .= mb_strlen($text) <= 2 ? '<td class="c">'.e($text).'</td>' : '<td>'.e($text).'</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html.'</body></html>';
    }

    /**
     * @param  list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>  $sections
     */
    public static function pdfBinary(string $title, array $sections, string $generatedBy): string
    {
        return Pdf::loadHTML(self::html($title, $sections, $generatedBy))
            ->setPaper('a4', 'landscape')
            ->output();
    }

    /**
     * @param  list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>  $sections
     */
    public static function download(string $format, string $filename, string $title, array $sections, string $generatedBy): Response
    {
        [$body, $type, $ext] = $format === 'pdf'
            ? [self::pdfBinary($title, $sections, $generatedBy), 'application/pdf', 'pdf']
            : [self::xlsxBinary($title, $sections, $generatedBy), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'];

        return new Response($body, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$ext.'"',
            'X-Checksum-SHA256' => hash('sha256', $body),
            'Cache-Control' => 'no-store',
        ]);
    }
}
