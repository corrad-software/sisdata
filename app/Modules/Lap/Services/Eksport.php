<?php

namespace App\Modules\Lap\Services;

use App\Models\User;
use App\Modules\Lap\Support\Wang;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Report export as PDF, Excel or CSV (SRS-LAP-04). All three are built from
 * the SAME cell values (text() below), so figures are identical across
 * formats (acceptance criterion 2). Every file carries the report header of
 * SRS-LAP-11 (data timestamp, period, filters), the reader's identity as a
 * watermark, and a SULIT / RAHSIA marking when the data contains such rows.
 */
class Eksport
{
    public const FORMAT = ['pdf', 'xlsx', 'csv'];

    public const MIME = [
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv; charset=UTF-8',
    ];

    public function watermark(User $user): string
    {
        return __('lap::lap.eksport.tanda_air', ['nama' => $user->name, 'emel' => $user->email, 'masa' => Carbon::now()->format('d/m/Y H:i')]);
    }

    /**
     * Header lines (SRS-LAP-11).
     *
     * @param  array<string, mixed>  $result  Enjin::run output
     * @return list<string>
     */
    public function meta(User $user, array $result): array
    {
        $m = $result['meta'];
        $t = $m['tempoh'] ?? null;

        return array_values(array_filter([
            __('lap::lap.eksport.dijana_oleh', ['nama' => $user->name, 'masa' => Carbon::parse($m['dijana_pada'])->format('d/m/Y H:i:s')]),
            __('lap::lap.eksport.data_dikemas_kini', ['masa' => $m['data_dikemas_kini'] ? Carbon::parse($m['data_dikemas_kini'])->format('d/m/Y H:i:s') : '-']),
            __('lap::lap.eksport.tempoh', ['tempoh' => $t ? Carbon::parse($t['dari'])->format('d/m/Y').' – '.Carbon::parse($t['hingga'])->format('d/m/Y') : __('lap::lap.eksport.semua_tempoh')]),
            __('lap::lap.eksport.penapis', ['penapis' => $m['penapis_teks'] === [] ? __('lap::lap.eksport.tiada_penapis') : implode('; ', $m['penapis_teks'])]),
            __('lap::lap.eksport.skop', ['skop' => $this->scopeText($m['skop'])]),
            __('lap::lap.eksport.bilangan', ['n' => $result['bilangan_rekod']]),
        ]));
    }

    /** @param array<string, list<string>|null> $scope */
    private function scopeText(array $scope): string
    {
        $parts = [];
        foreach ($scope as $type => $values) {
            if ($values !== null) {
                $parts[] = __('lap::lap.skop.'.$type).': '.($values === [] ? '-' : implode(', ', $values));
            }
        }

        return $parts === [] ? __('lap::lap.skop.penuh') : implode('; ', $parts);
    }

    /**
     * One table section from an engine result.
     *
     * @return array{tajuk: string, lajur: list<string>, jenis: list<string>, baris: list<list<string|null>>, jumlah: list<string|null>|null}
     */
    public function section(string $title, array $result): array
    {
        $cols = $result['lajur'];
        $rows = [];
        foreach ($result['baris'] as $r) {
            $line = [];
            foreach ($cols as $c) {
                $v = $r[$c['kunci']] ?? null;
                $label = $r['_label'][$c['kunci']] ?? null;
                $line[] = $this->text($c['jenis'], $label !== null && $label !== '' ? $label : $v);
            }
            $rows[] = $line;
        }
        $total = null;
        if (! empty($result['jumlah_keseluruhan'])) {
            $total = [];
            foreach ($cols as $i => $c) {
                $total[] = array_key_exists($c['kunci'], $result['jumlah_keseluruhan'])
                    ? $this->text($c['jenis'], $result['jumlah_keseluruhan'][$c['kunci']])
                    : ($i === 0 ? __('lap::lap.eksport.jumlah') : null);
            }
        }

        return ['tajuk' => $title, 'lajur' => array_column($cols, 'label'), 'jenis' => array_column($cols, 'jenis'), 'baris' => $rows, 'jumlah' => $total];
    }

    /** Canonical cell text — the same in every format. */
    public function text(string $type, mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return match ($type) {
            'wang' => Wang::nilai($v),
            'peratus' => Wang::nilai($v),
            'bool' => $v ? __('lap::lap.ya') : __('lap::lap.tidak'),
            'tarikh' => Carbon::parse((string) $v)->format('d/m/Y'),
            default => (string) $v,
        };
    }

    /**
     * @param  list<string>  $meta
     * @param  list<array{tajuk: string, lajur: list<string>, jenis: list<string>, baris: list<list<string|null>>, jumlah: list<string|null>|null}>  $sections
     */
    public function build(string $format, string $title, array $meta, array $sections, string $watermark, string $level): string
    {
        $marking = in_array($level, ['sulit', 'rahsia'], true) ? mb_strtoupper(__('lap::lap.klasifikasi.'.$level)) : null;

        return match ($format) {
            'pdf' => $this->pdf($title, $meta, $sections, $watermark, $marking),
            'xlsx' => $this->xlsx($title, $meta, $sections, $watermark, $marking),
            default => $this->csv($title, $meta, $sections, $watermark, $marking),
        };
    }

    /** @param list<array<string, mixed>> $sections */
    public function html(string $title, array $meta, array $sections, string $watermark, ?string $marking): string
    {
        $numeric = fn (string $t) => in_array($t, ['wang', 'nombor', 'bilangan', 'peratus'], true);
        $html = '<!doctype html><html lang="ms"><head><meta charset="utf-8"><title>'.e($title).'</title><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:8.5px;color:#18181b}h1{font-size:13px;margin:0 0 4px}h2{font-size:10px;margin:12px 0 4px}'
            .'p{margin:0 0 2px;color:#52525b}table{width:100%;border-collapse:collapse;margin-bottom:6px}th,td{border:1px solid #d4d4d8;padding:3px;text-align:left}th{background:#f4f4f5}'
            .'td.n{text-align:right}tr.j td{font-weight:bold;background:#fafafa}.cad{margin-top:8px;font-size:7.5px;color:#71717a}'
            .'.tanda{text-align:center;font-weight:bold;color:#b91c1c;font-size:10px;letter-spacing:2px}'
            .'.wm{position:fixed;top:40%;left:5%;width:90%;text-align:center;font-size:20px;color:#e4e4e7;transform:rotate(-25deg);z-index:-1}'
            .'.kaki{position:fixed;bottom:-10px;left:0;right:0;font-size:7px;color:#71717a}</style></head><body>';
        $html .= '<div class="wm">'.e($watermark).'</div><div class="kaki">'.e($watermark).($marking ? ' · '.e($marking) : '').'</div>';
        if ($marking) {
            $html .= '<p class="tanda">'.e($marking).'</p>';
        }
        $html .= '<h1>'.e($title).'</h1>';
        foreach ($meta as $line) {
            $html .= '<p>'.e($line).'</p>';
        }
        foreach ($sections as $s) {
            $html .= '<h2>'.e($s['tajuk']).'</h2>';
            if (! empty($s['catatan'])) {
                $html .= '<p>'.e($s['catatan']).'</p>';
            }
            $html .= '<table><thead><tr>';
            foreach ($s['lajur'] as $h) {
                $html .= '<th>'.e($h).'</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($s['baris'] as $row) {
                $html .= '<tr>';
                foreach ($row as $i => $v) {
                    $n = $numeric($s['jenis'][$i] ?? '');
                    $html .= '<td'.($n ? ' class="n"' : '').'>'.e($n && $v !== null && ($s['jenis'][$i] ?? '') === 'wang' ? Wang::papar($v) : (string) $v).'</td>';
                }
                $html .= '</tr>';
            }
            if ($s['jumlah']) {
                $html .= '<tr class="j">';
                foreach ($s['jumlah'] as $i => $v) {
                    $n = $numeric($s['jenis'][$i] ?? '');
                    $html .= '<td'.($n ? ' class="n"' : '').'>'.e($n && $v !== null && ($s['jenis'][$i] ?? '') === 'wang' ? Wang::papar($v) : (string) $v).'</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }
        if ($marking) {
            $html .= '<p class="tanda">'.e($marking).'</p>';
        }

        return $html.'</body></html>';
    }

    private function pdf(string $title, array $meta, array $sections, string $watermark, ?string $marking): string
    {
        $wide = max(array_map(fn ($s) => count($s['lajur']), $sections ?: [['lajur' => []]])) > 6;

        return Pdf::loadHTML($this->html($title, $meta, $sections, $watermark, $marking))->setPaper('a4', $wide ? 'landscape' : 'portrait')->output();
    }

    private function xlsx(string $title, array $meta, array $sections, string $watermark, ?string $marking): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle($title)->setCreator('SISDATA')->setDescription($watermark)->setKeywords((string) $marking);
        $book->removeSheetByIndex(0);
        foreach ($sections as $n => $s) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', ($n + 1).' '.$s['tajuk']), 0, 31));
            $sheet->getHeaderFooter()->setOddHeader('&C'.($marking ? $marking.' — ' : '').$watermark)->setOddFooter('&L'.$watermark.'&R&P / &N');
            $r = 1;
            if ($marking) {
                $sheet->setCellValueExplicit('A'.$r++, $marking, DataType::TYPE_STRING);
            }
            $sheet->setCellValueExplicit('A'.$r, $title, DataType::TYPE_STRING);
            $sheet->getStyle('A'.$r++)->getFont()->setBold(true)->setSize(13);
            foreach ([...$meta, $watermark] as $line) {
                $sheet->setCellValueExplicit('A'.$r++, $line, DataType::TYPE_STRING);
            }
            $r++;
            $sheet->setCellValueExplicit('A'.$r, $s['tajuk'], DataType::TYPE_STRING);
            $sheet->getStyle('A'.$r++)->getFont()->setBold(true);
            foreach ($s['lajur'] as $c => $h) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).$r, $h, DataType::TYPE_STRING);
            }
            $sheet->getStyle('A'.$r.':'.Coordinate::stringFromColumnIndex(max(1, count($s['lajur']))).$r)->getFont()->setBold(true);
            $r++;
            foreach ([...$s['baris'], ...($s['jumlah'] ? [$s['jumlah']] : [])] as $row) {
                foreach ($row as $c => $v) {
                    $cell = Coordinate::stringFromColumnIndex($c + 1).$r;
                    $type = $s['jenis'][$c] ?? 'teks';
                    if ($v !== null && in_array($type, ['wang', 'nombor', 'bilangan', 'peratus'], true) && is_numeric($v)) {
                        $sheet->setCellValueExplicit($cell, $v, DataType::TYPE_NUMERIC);
                        if ($type === 'wang') {
                            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                        }
                    } else {
                        // Text cells stay strings: no formula injection.
                        $sheet->setCellValueExplicit($cell, (string) $v, DataType::TYPE_STRING);
                    }
                }
                $r++;
            }
            foreach (range(1, max(1, count($s['lajur']))) as $c) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
            }
        }
        if ($book->getSheetCount() === 0) {
            $book->createSheet()->setCellValueExplicit('A1', $title, DataType::TYPE_STRING);
        }
        $book->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }

    private function csv(string $title, array $meta, array $sections, string $watermark, ?string $marking): string
    {
        $h = fopen('php://temp', 'r+');
        $put = function (array $row) use ($h) {
            fputcsv($h, array_map(fn ($v) => $this->safe($v), $row), ',', '"', '');
        };
        if ($marking) {
            $put([$marking]);
        }
        $put([$title]);
        foreach ([...$meta, $watermark] as $line) {
            $put([$line]);
        }
        foreach ($sections as $s) {
            $put([]);
            $put([$s['tajuk']]);
            $put($s['lajur']);
            foreach ($s['baris'] as $row) {
                $put($row);
            }
            if ($s['jumlah']) {
                $put($s['jumlah']);
            }
        }
        rewind($h);
        $out = (string) stream_get_contents($h);
        fclose($h);

        return "\xEF\xBB\xBF".$out;
    }

    /** CSV injection guard: a text cell starting with = + - @ is prefixed with '. Plain numbers pass. */
    private function safe(mixed $v): string
    {
        $v = (string) $v;
        if ($v !== '' && preg_match('/^[=+\-@\t\r]/', $v) && ! preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return "'".$v;
        }

        return $v;
    }
}
