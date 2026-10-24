<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard export (SRS-DSH-14) as PDF or Excel: every KPI shown with its
 * value, target, traffic light and breakdown, plus the filter parameters and
 * the data timestamp (SRS-DSH-12). Each page / sheet carries the exporting
 * user's identity as a watermark (security controls of Modul 1).
 */
class Eksport
{
    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  array<string, mixed>  $filters
     */
    public function download(string $format, User $user, string $aras, array $kpis, array $filters, ?string $dataAt = null): Response
    {
        $title = __('dsh::dsh.eksport.tajuk', ['aras' => __('dsh::dsh.aras.'.$aras)]);
        $meta = $this->meta($user, $filters, $kpis, $dataAt);
        $sections = $this->sections($kpis);
        $stamp = Carbon::now()->format('Ymd_His');

        [$body, $type, $ext] = $format === 'pdf'
            ? [$this->pdf($title, $meta, $sections, $this->watermark($user)), 'application/pdf', 'pdf']
            : [$this->xlsx($title, $meta, $sections, $this->watermark($user)), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'];

        return new Response($body, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="dashboard_'.$aras.'_'.$stamp.'.'.$ext.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function watermark(User $user): string
    {
        return __('dsh::dsh.eksport.tanda_air', ['nama' => $user->name, 'emel' => $user->email, 'masa' => Carbon::now()->format('d/m/Y H:i')]);
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @return list<string>
     */
    public function meta(User $user, array $filters, array $kpis, ?string $dataAt): array
    {
        $times = array_filter(array_map(fn ($k) => $k['dikira_pada'] ?? null, $kpis));
        $dataAt ??= $times === [] ? null : min($times);
        $f = [];
        foreach ($filters as $k => $v) {
            $f[] = __('dsh::dsh.penapis.'.$k).': '.$v;
        }

        return [
            __('dsh::dsh.eksport.dijana_oleh', ['nama' => $user->name, 'masa' => Carbon::now()->format('d/m/Y H:i:s')]),
            __('dsh::dsh.eksport.penapis', ['penapis' => $f === [] ? __('dsh::dsh.eksport.tiada_penapis') : implode('; ', $f)]),
            __('dsh::dsh.eksport.data_pada', ['masa' => $dataAt ? Carbon::parse($dataAt)->format('d/m/Y H:i:s') : '-']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @return list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>
     */
    public function sections(array $kpis): array
    {
        $summary = [
            'tajuk' => __('dsh::dsh.eksport.ringkasan'),
            'lajur' => [__('dsh::dsh.eksport.indikator'), __('dsh::dsh.eksport.domain'), __('dsh::dsh.eksport.nilai'), __('dsh::dsh.eksport.unit'), __('dsh::dsh.eksport.sasaran'), __('dsh::dsh.eksport.status'), __('dsh::dsh.eksport.dikira_pada')],
            'baris' => [],
        ];
        $sections = [];
        foreach ($kpis as $k) {
            $summary['baris'][] = [
                (string) $k['label'], __('dsh::dsh.domain.'.$k['domain']), $k['ralat'] ? __('dsh::dsh.ralat_kpi') : $k['nilai'], (string) $k['unit'],
                $k['sasaran'], $k['lampu'] ? __('dsh::dsh.lampu.'.$k['lampu']) : '-', $k['dikira_pada'] ? Carbon::parse($k['dikira_pada'])->format('d/m/Y H:i') : '-',
            ];
            if (! empty($k['pecahan'])) {
                $sections[] = [
                    'tajuk' => (string) $k['label'],
                    'lajur' => [__('dsh::dsh.eksport.pecahan'), __('dsh::dsh.eksport.nilai')],
                    'baris' => array_map(fn ($p) => [(string) ($p['label'] ?? ''), $p['nilai'] ?? null], (array) $k['pecahan']),
                ];
            }
        }

        return [$summary, ...$sections];
    }

    /** @param list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}> $sections */
    public function html(string $title, array $meta, array $sections, string $watermark): string
    {
        $html = '<!doctype html><html lang="ms"><head><meta charset="utf-8"><title>'.e($title).'</title><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#18181b}h1{font-size:14px;margin:0 0 4px}h2{font-size:10px;margin:12px 0 4px}'
            .'p{margin:0 0 2px;color:#52525b}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d4d4d8;padding:3px;text-align:left}th{background:#f4f4f5}'
            .'.wm{position:fixed;top:40%;left:5%;width:90%;text-align:center;font-size:22px;color:#e4e4e7;transform:rotate(-25deg);z-index:-1}'
            .'.kaki{position:fixed;bottom:-10px;left:0;right:0;font-size:7px;color:#71717a}</style></head><body>';
        $html .= '<div class="wm">'.e($watermark).'</div><div class="kaki">'.e($watermark).'</div>';
        $html .= '<h1>'.e($title).'</h1>';
        foreach ($meta as $line) {
            $html .= '<p>'.e($line).'</p>';
        }
        foreach ($sections as $s) {
            $html .= '<h2>'.e($s['tajuk']).'</h2><table><thead><tr>';
            foreach ($s['lajur'] as $h) {
                $html .= '<th>'.e($h).'</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($s['baris'] as $row) {
                $html .= '<tr>';
                foreach ($row as $v) {
                    $html .= '<td>'.e($v === null ? '' : (is_float($v) ? number_format($v, 2, '.', ',') : (string) $v)).'</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html.'</body></html>';
    }

    /** @param list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}> $sections */
    public function pdf(string $title, array $meta, array $sections, string $watermark): string
    {
        return Pdf::loadHTML($this->html($title, $meta, $sections, $watermark))->setPaper('a4', 'portrait')->output();
    }

    /** @param list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}> $sections */
    public function xlsx(string $title, array $meta, array $sections, string $watermark): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle($title)->setCreator('SISDATA')->setDescription($watermark);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(__('dsh::dsh.eksport.ringkasan'), 0, 31));
        $sheet->getHeaderFooter()->setOddHeader('&C'.$watermark)->setOddFooter('&L'.$watermark.'&R&P / &N');
        $r = 1;
        $sheet->setCellValue('A'.$r++, $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        foreach ([...$meta, $watermark] as $line) {
            $sheet->setCellValue('A'.$r++, $line);
        }
        foreach ($sections as $s) {
            $r++;
            $sheet->setCellValue('A'.$r, $s['tajuk']);
            $sheet->getStyle('A'.$r)->getFont()->setBold(true);
            $r++;
            foreach ($s['lajur'] as $c => $h) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 1).$r, $h);
            }
            $sheet->getStyle('A'.$r.':'.Coordinate::stringFromColumnIndex(max(1, count($s['lajur']))).$r)->getFont()->setBold(true);
            $r++;
            foreach ($s['baris'] as $row) {
                foreach ($row as $c => $v) {
                    $cell = Coordinate::stringFromColumnIndex($c + 1).$r;
                    if (is_int($v) || is_float($v)) {
                        $sheet->setCellValueExplicit($cell, $v, DataType::TYPE_NUMERIC);
                    } else {
                        // Text cells are written as strings: no formula injection.
                        $sheet->setCellValueExplicit($cell, $v === null ? '' : (string) $v, DataType::TYPE_STRING);
                    }
                }
                $r++;
            }
        }
        foreach (range(1, 7) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }
}
