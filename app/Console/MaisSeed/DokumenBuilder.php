<?php

namespace App\Console\MaisSeed;

use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pro\Models\LotTanah;
use FPDF;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/**
 * MOD-DMS through DocumentStore (whitelist, magic number, antivirus,
 * classification, audit): small generated PDFs attached to the lot — and a
 * tenancy agreement to its contract — every page watermarked
 * "CONTOH / SINTETIK". No real document is reproduced.
 */
class DokumenBuilder
{
    private bool $broken = false;

    public function __construct(private DocumentStore $store) {}

    /**
     * @param  array<string, mixed>  $h
     * @param  list<Kontrak>  $kontrak
     */
    public function bina(LotTanah $lot, array $h, Rawak $r, Konteks $k, ?string $noHakMilik, array $kontrak): void
    {
        if ($this->broken) {
            return;
        }
        $docs = [
            ['pelan', 'dalaman', 'Pelan Lokasi (contoh)', ['Pelan lokasi lot '.$lot->no_lot.' (mukim '.$lot->kod_mukim.').', 'Kedudukan berdasarkan titik awam / OpenStreetMap; sempadan ANGGARAN.']],
        ];
        if ($noHakMilik !== null) {
            $docs[] = ['geran', 'sulit', 'Salinan Hak Milik (contoh)', ['No. hak milik: '.$noHakMilik.' (SINTETIK)', 'Pemilik berdaftar: Majlis Agama Islam Selangor', 'Lot: '.$lot->no_lot.', mukim '.$lot->kod_mukim]];
        }
        if ($lot->bahagian_pemilik === 'wakaf') {
            $docs[] = ['surat_rasmi', 'sulit', 'Surat Ikatan Wakaf (contoh)', ['Hartanah: '.$h['nama'], 'Pemegang amanah: Majlis Agama Islam Selangor', 'Butiran pewakaf tidak disimpan (PDPA). Dokumen ini CONTOH sahaja.']];
        }
        if ($r->chance(50)) {
            $docs[] = ['laporan', 'dalaman', 'Laporan Pemantauan Tapak (contoh)', ['Ringkasan keadaan tapak bagi '.$h['nama'].'.', 'Penemuan dan gambar ialah data SINTETIK.']];
        }
        foreach ($docs as [$cat, $cls, $title, $lines]) {
            $this->simpan('lot_tanah', $lot->id, $cat, $cls, $title, $h, $lines, $k);
        }
        foreach (array_slice($kontrak, 0, 2) as $c) {
            $this->simpan('kontrak_pajakan', $c->id, 'perjanjian', 'sulit', 'Perjanjian Sewa (contoh)', $h, [
                'No. kontrak: '.$c->no_kontrak, 'Penyewa: '.$c->penyewa, 'Tempoh: '.$c->tarikh_mula?->format('d/m/Y').' hingga '.$c->tarikh_tamat?->format('d/m/Y'),
                'Kadar: RM '.number_format((float) $c->kadar, 2).' sebulan', 'Semua butiran ialah SINTETIK.',
            ], $k);
        }
    }

    /**
     * @param  array<string, mixed>  $h
     * @param  list<string>  $lines
     */
    private function simpan(string $ownerType, string|int $ownerId, string $cat, string $cls, string $title, array $h, array $lines, Konteks $k): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mais').'.pdf';
        try {
            file_put_contents($path, $this->pdf($title, $h, $lines));
            $name = Str::slug($title).'-'.Str::lower($h['kunci']).'.pdf';
            $this->store->store(new UploadedFile($path, $name, 'application/pdf', null, true), $ownerType, $ownerId, $cat, $k->pegawai, $cls, []);
            $k->tambah('dokumen_dms');
        } catch (DocumentRejected $e) {
            $k->amaran($h['kunci'].': dokumen "'.$title.'" ditolak DMS ('.$e->getMessage().')');
        } catch (Throwable $e) {
            $this->broken = true; // storage / antivirus down: skip the rest, report once
            $k->amaran('DMS tidak tersedia — dokumen contoh dilangkau ('.$e->getMessage().')');
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  array<string, mixed>  $h
     * @param  list<string>  $lines
     */
    private function pdf(string $title, array $h, array $lines): string
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetTitle($title.' - CONTOH SINTETIK');
        $pdf->SetCreator('SISDATA sisdata:mais-seed');
        $pdf->AddPage();
        // Watermark.
        $pdf->SetFont('Helvetica', 'B', 54);
        $pdf->SetTextColor(235, 200, 200);
        $pdf->Text(22, 150, 'CONTOH / SINTETIK');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', 'B', 15);
        $pdf->Cell(0, 10, $this->latin('MAJLIS AGAMA ISLAM SELANGOR (MAIS)'), 0, 1, 'C');
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell(0, 9, $this->latin(strtoupper($title)), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 6, $this->latin('Dokumen CONTOH untuk sistem SISDATA - bukan dokumen sebenar, tiada nilai undang-undang.'), 0, 1, 'C');
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', '', 11);
        foreach (['Hartanah: '.$h['nama'], 'Alamat: '.($h['alamat'] ?? '-'), ...$lines] as $line) {
            $pdf->MultiCell(0, 7, $this->latin($line));
        }
        $pdf->Ln(8);
        $pdf->SetFont('Helvetica', 'I', 9);
        $pdf->MultiCell(0, 5, $this->latin('Nama dan alamat hartanah daripada sumber awam (lihat database/data/mais/MANIFEST.md). Semua nombor, tarikh, nilai dan pihak dalam dokumen ini dijana (SINTETIK). Dijana '.now()->format('d/m/Y').'.'));

        return $pdf->Output('S');
    }

    private function latin(string $text): string
    {
        return (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    }
}
