<?php

namespace App\Modules\Ptn\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSV / PDF downloads for MOD-PTN reports. Sections are
 * `['tajuk' => ..., 'lajur' => [headers], 'baris' => [[cells]]]`.
 */
class Eksport
{
    /** @param list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}> $sections */
    public static function download(string $format, string $filename, string $title, array $sections, string $footer): Response
    {
        [$body, $type, $ext] = $format === 'pdf'
            ? [Pdf::loadHTML(self::html($title, $sections, $footer))->setPaper('a4', 'landscape')->output(), 'application/pdf', 'pdf']
            : [self::csv($sections), 'text/csv; charset=UTF-8', 'csv'];

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
            fputcsv($out, [$section['tajuk']], escape: '\\');
            fputcsv($out, $section['lajur'], escape: '\\');
            foreach ($section['baris'] as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row), escape: '\\');
            }
            fputcsv($out, [], escape: '\\');
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /** @param list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}> $sections */
    public static function html(string $title, array $sections, string $footer): string
    {
        $html = '<!doctype html><html lang="ms"><head><meta charset="utf-8"><title>'.e($title).'</title><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#18181b}h1{font-size:13px;margin:0 0 2px}h2{font-size:10px;margin:14px 0 4px}'
            .'p{margin:0 0 6px;color:#52525b}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d4d4d8;padding:2px 3px;text-align:left;vertical-align:top}th{background:#f4f4f5}'
            .'</style></head><body><h1>'.e($title).'</h1><p>'.e($footer).'</p>';
        foreach ($sections as $section) {
            $html .= '<h2>'.e($section['tajuk']).'</h2><table><thead><tr>';
            foreach ($section['lajur'] as $h) {
                $html .= '<th>'.e($h).'</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($section['baris'] as $row) {
                $html .= '<tr>'.implode('', array_map(fn ($v) => '<td>'.e($v === null ? '' : (string) $v).'</td>', $row)).'</tr>';
            }
            if ($section['baris'] === []) {
                $html .= '<tr><td colspan="'.max(1, count($section['lajur'])).'">Tiada rekod.</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html.'</body></html>';
    }
}
