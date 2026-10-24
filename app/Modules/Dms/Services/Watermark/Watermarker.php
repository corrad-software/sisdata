<?php

namespace App\Modules\Dms\Services\Watermark;

use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use setasign\Fpdi\PdfParser\PdfParserException;
use Throwable;

/**
 * SRS-DMS-26: dynamic watermark (user id, name, date + time, classification)
 * stamped on every preview / print of Sulit and Rahsia documents.
 *
 *  - Images (JPG/PNG): GD — repeated diagonal semi-transparent text + footer band.
 *  - PDF: FPDI (setasign/fpdi 2.6, PHP ≤ 8.6) imports each page and overlays
 *    the watermark as vector text. PDFs FPDI's free parser can't read
 *    (compressed xref streams, PDF ≥ 1.5) are rasterised with pdftoppm and
 *    rebuilt from watermarked page images — the watermark then can't be
 *    stripped from the file either.
 */
class Watermarker
{
    public function __construct(private string $pdftoppm, private int $maxPages) {}

    public function text(string $classification, int $userId, string $userName, \DateTimeInterface $at): string
    {
        return sprintf('%s - ID %d %s - %s', mb_strtoupper($classification), $userId, $userName, $at->format('d/m/Y H:i:s'));
    }

    /** @return array{content: string, mime: string} */
    public function image(string $path, string $text): array
    {
        $previous = ini_get('memory_limit');
        ini_set('memory_limit', '1024M');
        try {
            $image = @imagecreatefromstring((string) file_get_contents($path));
            if (! $image instanceof GdImage) {
                throw new RuntimeException('Unsupported image.');
            }
            $this->stampImage($image, $text);
            ob_start();
            imagejpeg($image, null, 85);
            $content = (string) ob_get_clean();
            imagedestroy($image);

            return ['content' => $content, 'mime' => 'image/jpeg'];
        } finally {
            ini_set('memory_limit', (string) $previous);
        }
    }

    public function pdf(string $path, string $text): string
    {
        try {
            return $this->pdfVector($path, $text);
        } catch (PdfParserException) {
            return $this->pdfRaster($path, $text);
        }
    }

    private function pdfVector(string $path, string $text): string
    {
        $pdf = new WatermarkPdf;
        $pdf->SetAutoPageBreak(false);
        $pages = $pdf->setSourceFile($path);
        $label = $this->latin1($text);

        for ($i = 1; $i <= $pages; $i++) {
            $template = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($template);
            $w = (float) $size['width'];
            $h = (float) $size['height'];
            $pdf->AddPage($w > $h ? 'L' : 'P', [$w, $h]);
            $pdf->useTemplate($template);

            $pdf->SetFont('Helvetica', 'B', max(10, min($w, $h) / 14));
            $pdf->SetTextColor(200, 30, 30);
            $pdf->setAlpha(0.18);
            $step = $h / 3;
            for ($row = 0; $row < 3; $row++) {
                $pdf->rotatedText($w * 0.08, $step * $row + $step * 0.85, $label, 30);
            }
            $pdf->setAlpha(0.85);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(150, 0, 0);
            $pdf->Text(4, $h - 3, $label);
            $pdf->setAlpha(1);
        }

        return $pdf->Output('S');
    }

    private function pdfRaster(string $path, string $text): string
    {
        $dir = sys_get_temp_dir().'/dms-wm-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($dir);
        try {
            $run = Process::timeout(300)->run([$this->pdftoppm, '-r', '110', '-jpeg', '-f', '1', '-l', (string) $this->maxPages, $path, $dir.'/p']);
            $images = glob($dir.'/p*.jpg') ?: [];
            if (! $run->successful() || $images === []) {
                throw new RuntimeException('PDF cannot be rendered for watermarking.');
            }
            sort($images, SORT_NATURAL);

            $pdf = new WatermarkPdf;
            $pdf->SetAutoPageBreak(false);
            foreach ($images as $file) {
                $stamped = $this->image($file, $text);
                file_put_contents($file, $stamped['content']);
                [$px, $py] = getimagesize($file) ?: [850, 1100];
                $w = $px / 110 * 25.4;
                $h = $py / 110 * 25.4;
                $pdf->AddPage($w > $h ? 'L' : 'P', [$w, $h]);
                $pdf->Image($file, 0, 0, $w, $h, 'JPG');
            }

            return $pdf->Output('S');
        } catch (Throwable $e) {
            throw new RuntimeException('Watermarking failed: '.$e->getMessage(), 0, $e);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function stampImage(GdImage $image, string $text): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        imagealphablending($image, true);
        // GD's built-in fonts are single-byte: fold to ASCII.
        $text = (string) preg_replace('/[^\x20-\x7E]/', '?', (string) (@iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text));

        // Render with GD's built-in font (no font file dependency), then scale + rotate.
        $font = 5;
        $tw = imagefontwidth($font) * strlen($text) + 8;
        $th = imagefontheight($font) + 6;
        $stamp = imagecreatetruecolor($tw, $th);
        imagesavealpha($stamp, true);
        imagealphablending($stamp, false);
        imagefill($stamp, 0, 0, imagecolorallocatealpha($stamp, 0, 0, 0, 127));
        imagestring($stamp, $font, 4, 3, $text, imagecolorallocatealpha($stamp, 200, 30, 30, 0));

        $scale = max(1.0, ($width * 0.55) / $tw);
        $scaled = imagescale($stamp, (int) ($tw * $scale), (int) ($th * $scale));
        imagedestroy($stamp);
        if (! $scaled instanceof GdImage) {
            return;
        }
        imagesavealpha($scaled, true);
        $rotated = imagerotate($scaled, 30, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagedestroy($scaled);
        if (! $rotated instanceof GdImage) {
            return;
        }
        imagesavealpha($rotated, true);

        $rw = imagesx($rotated);
        $rh = imagesy($rotated);
        for ($y = -$rh / 2; $y < $height; $y += $rh * 0.9) {
            for ($x = -$rw / 3; $x < $width; $x += $rw * 0.9) {
                $this->copyWithOpacity($image, $rotated, (int) $x, (int) $y, $rw, $rh, 22);
            }
        }
        imagedestroy($rotated);

        // Footer band, fully legible.
        $band = imagefontheight(3) + 8;
        imagefilledrectangle($image, 0, $height - $band, $width, $height, imagecolorallocatealpha($image, 255, 255, 255, 30));
        imagestring($image, 3, 6, $height - $band + 4, $text, imagecolorallocate($image, 150, 0, 0));
    }

    /** imagecopymerge ignores the source alpha channel; blend through a temporary copy instead. */
    private function copyWithOpacity(GdImage $dst, GdImage $src, int $x, int $y, int $w, int $h, int $percent): void
    {
        $tmp = imagecreatetruecolor($w, $h);
        imagecopy($tmp, $dst, 0, 0, $x, $y, $w, $h);
        imagecopy($tmp, $src, 0, 0, 0, 0, $w, $h);
        imagecopymerge($dst, $tmp, $x, $y, 0, 0, $w, $h, $percent);
        imagedestroy($tmp);
    }

    private function latin1(string $text): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);

        return $converted === false ? (string) preg_replace('/[^\x20-\x7E]/', '?', $text) : $converted;
    }
}
