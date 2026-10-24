<?php

namespace App\Modules\Aud\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use ZipArchive;

/**
 * SRS-AUD-12/13: exports a search result as CSV, Excel, JSON or PDF inside a
 * ZIP "pakej eksport" together with metadata.json (criteria, record count,
 * exporter, timestamps, SHA-256 of the data file). The SHA-256 of the whole
 * package is returned in a header, stored in audit_eksport and recorded in
 * the audit trail (SRS-AUD-14).
 */
class AuditExporter
{
    public const FORMATS = ['csv', 'xlsx', 'json', 'pdf'];

    public const PDF_MAX_ROWS = 2000;

    private const HEADINGS = [
        'no_urutan' => 'No. Urutan',
        'tarikh_masa_tempatan' => 'Tarikh & Masa (MYT)',
        'tarikh_masa_utc' => 'Tarikh & Masa (UTC)',
        'pengecam_pengguna' => 'Pengguna',
        'id_pengguna' => 'ID Pengguna',
        'peranan_semasa' => 'Peranan',
        'modul' => 'Modul',
        'nama_entiti' => 'Entiti',
        'id_rekod' => 'ID Rekod',
        'jenis_tindakan' => 'Tindakan',
        'status_transaksi' => 'Status',
        'alamat_ip' => 'Alamat IP',
        'saluran_capaian' => 'Saluran',
        'jenis_peranti' => 'Peranti',
        'lokasi_anggaran' => 'Lokasi Anggaran',
        'sebab_perubahan' => 'Sebab',
        'id_korelasi' => 'ID Korelasi',
        'perubahan' => 'Perubahan Medan',
        'cincangan_rekod' => 'Cincangan Rekod',
    ];

    public function __construct(private AuditSearch $search, private TrailAuditLogger $logger) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{path: string, filename: string, checksum: string, id: int, count: int}
     */
    public function export(string $format, array $criteria, User $exporter): array
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw new RuntimeException('Format tidak disokong');
        }

        $limit = $format === 'pdf' ? self::PDF_MAX_ROWS : (int) config('aud.had_eksport', 50000);
        $rows = $this->search->present(
            $this->search->query($criteria)->orderBy('no_urutan')->limit($limit)->get()
        );

        $now = Carbon::now('UTC');
        $local = $now->copy()->setTimezone((string) config('aud.zon_waktu_tempatan'));
        $stamp = $local->format('Ymd-His');
        $dataName = "audit-{$stamp}.{$format}";

        $dir = storage_path('app/aud/eksport');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $dataPath = $dir.'/'.Str::uuid().'-'.$dataName;

        $metadata = [
            'sistem' => 'SISDATA — MAIS',
            'jenis' => 'Pakej eksport audit trail',
            'format' => $format,
            'kriteria_carian' => $criteria,
            'bilangan_rekod' => $rows->count(),
            'dipotong_pada_had' => $rows->count() >= $limit ? $limit : null,
            'pengeksport' => ['id' => $exporter->getKey(), 'nama' => $exporter->name, 'emel' => $exporter->email],
            'tarikh_masa_utc' => $now->toIso8601ZuluString(),
            'tarikh_masa_tempatan' => $local->format('Y-m-d H:i:s').' (UTC+8)',
        ];

        match ($format) {
            'csv' => $this->writeCsv($dataPath, $rows),
            'xlsx' => $this->writeXlsx($dataPath, $rows, $metadata),
            'json' => file_put_contents($dataPath, json_encode(['metadata' => $metadata, 'rekod' => $rows->values()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'pdf' => $this->writePdf($dataPath, $rows, $metadata),
        };

        $metadata['fail_data'] = $dataName;
        $metadata['sha256_fail_data'] = hash_file('sha256', $dataPath);

        $zipName = "pakej-audit-{$stamp}.zip";
        $zipPath = $dir.'/'.Str::uuid().'-'.$zipName;
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($dataPath, $dataName);
        $zip->addFromString('metadata.json', (string) json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();
        @unlink($dataPath);

        $checksum = (string) hash_file('sha256', $zipPath);

        $id = (int) DB::table('audit_eksport')->insertGetId([
            'id_pengguna' => $exporter->getKey(),
            'pengecam_pengguna' => $exporter->email,
            'kriteria_carian' => json_encode($criteria, JSON_UNESCAPED_UNICODE),
            'format' => $format,
            'bilangan_rekod' => $rows->count(),
            'checksum' => $checksum,
            'checksum_data' => $metadata['sha256_fail_data'],
            'nama_fail' => $zipName,
            'saiz_bait' => (int) filesize($zipPath),
            'tarikh_masa_utc' => $now->format('Y-m-d H:i:s.uP'),
            'tarikh_masa_tempatan' => $local->format('Y-m-d H:i:s.u'),
        ]);

        $this->logger->record('eksport', 'aud', 'audit_rekod', null, 'berjaya', null, [], [
            'id_eksport' => $id,
            'format' => $format,
            'bilangan_rekod' => $rows->count(),
            'checksum' => $checksum,
            'kriteria' => $criteria,
        ]);

        return ['path' => $zipPath, 'filename' => $zipName, 'checksum' => $checksum, 'id' => $id, 'count' => $rows->count()];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function flat(array $row): array
    {
        $out = [];
        foreach (array_keys(self::HEADINGS) as $key) {
            $value = $row[$key] ?? null;
            $out[$key] = match ($key) {
                'peranan_semasa' => implode(', ', (array) $value),
                'perubahan' => implode('; ', array_map(
                    fn (array $c) => $c['nama_medan'].': '.($c['nilai_asal'] ?? '∅').' → '.($c['nilai_baharu'] ?? '∅'),
                    (array) $value,
                )),
                default => $value === null ? '' : (string) $value,
            };
        }

        return $out;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function writeCsv(string $path, Collection $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RuntimeException('Tidak dapat menulis fail eksport');
        }
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Malay text correctly
        fputcsv($handle, array_values(self::HEADINGS), escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(self::csvSafe(...), array_values($this->flat($row))), escape: '');
        }
        fclose($handle);
    }

    /** Neutralise spreadsheet formula injection (=, +, -, @ at the start of a cell). */
    private static function csvSafe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $metadata
     */
    private function writeXlsx(string $path, Collection $rows, array $metadata): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Audit');
        $sheet->fromArray(array_values(self::HEADINGS), null, 'A1');
        $sheet->getStyle('1:1')->getFont()->setBold(true);

        $line = 2;
        foreach ($rows as $row) {
            $col = 1;
            foreach ($this->flat($row) as $value) {
                // Explicit string type: no formula evaluation of user-supplied text.
                $sheet->getCell([$col++, $line])->setValueExplicit($value, DataType::TYPE_STRING);
            }
            $line++;
        }

        $meta = $book->createSheet();
        $meta->setTitle('Metadata');
        $i = 1;
        foreach ($metadata as $key => $value) {
            $meta->setCellValue([1, $i], $key);
            $meta->getCell([2, $i])->setValueExplicit(is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE), DataType::TYPE_STRING);
            $i++;
        }

        (new Xlsx($book))->save($path);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $metadata
     */
    private function writePdf(string $path, Collection $rows, array $metadata): void
    {
        $e = fn (?string $v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $cols = ['no_urutan', 'tarikh_masa_tempatan', 'pengecam_pengguna', 'peranan_semasa', 'modul', 'nama_entiti', 'id_rekod', 'jenis_tindakan', 'status_transaksi', 'alamat_ip', 'perubahan'];

        $html = '<html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;font-size:7.5px}
            h1{font-size:13px;margin:0 0 4px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #999;padding:2px;vertical-align:top;word-wrap:break-word}
            th{background:#eee}
            </style></head><body>';
        $html .= '<h1>Laporan Audit Trail — SISDATA</h1>';
        $html .= '<p>Dijana: '.$e((string) $metadata['tarikh_masa_tempatan']).' oleh '.$e((string) $metadata['pengeksport']['nama']).
            ' · Bilangan rekod: '.$e((string) $metadata['bilangan_rekod']).
            ' · Kriteria: '.$e((string) json_encode($metadata['kriteria_carian'], JSON_UNESCAPED_UNICODE)).'</p>';
        $html .= '<table><thead><tr>';
        foreach ($cols as $c) {
            $html .= '<th>'.$e(self::HEADINGS[$c]).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $flat = $this->flat($row);
            $html .= '<tr>';
            foreach ($cols as $c) {
                $html .= '<td>'.$e($flat[$c]).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        Pdf::loadHTML($html)->setPaper('a4', 'landscape')->save($path);
    }
}
