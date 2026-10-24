<?php

namespace Tests\Modules\Dms\Support;

use App\Modules\Dms\Services\Scanning\FakeVirusScanner;
use FPDF;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/** Real (tiny) files of every type, built on the fly. */
class TestFiles
{
    public static function pdf(string $text = 'Geran tanah wakaf Mukim Klang', string $name = 'geran.pdf'): UploadedFile
    {
        $pdf = new FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 14);
        $pdf->MultiCell(0, 8, $text);

        return self::fromString($pdf->Output('S'), $name);
    }

    /** An image-only PDF (a "scan"): no text layer, so OCR is needed. */
    public static function scannedPdf(string $text, string $name = 'imbasan.pdf'): ?UploadedFile
    {
        $png = self::textImagePath($text);
        if ($png === null) {
            return null;
        }
        $pdf = new FPDF;
        $pdf->AddPage();
        $pdf->Image($png, 10, 10, 190, 0, 'PNG');
        @unlink($png);

        return self::fromString($pdf->Output('S'), $name);
    }

    public static function png(string $name = 'tapak.png', int $w = 120, int $h = 80): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 120, 200));
        ob_start();
        imagepng($img);

        return self::fromString((string) ob_get_clean(), $name);
    }

    public static function jpg(string $name = 'tapak.jpg'): UploadedFile
    {
        $img = imagecreatetruecolor(200, 150);
        imagefill($img, 0, 0, imagecolorallocate($img, 240, 240, 240));
        ob_start();
        imagejpeg($img);

        return self::fromString((string) ob_get_clean(), $name);
    }

    /** PNG with large rendered text (for OCR), or null if no TTF font is available. */
    public static function textImagePath(string $text): ?string
    {
        $font = collect(['/System/Library/Fonts/Supplemental/Arial.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/Library/Fonts/Arial.ttf'])->first(fn ($f) => is_file($f));
        if ($font === null) {
            return null;
        }
        $img = imagecreatetruecolor(1600, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagettftext($img, 56, 0, 40, 180, imagecolorallocate($img, 0, 0, 0), $font, $text);
        $path = tempnam(sys_get_temp_dir(), 'ocr').'.png';
        imagepng($img, $path);

        return $path;
    }

    /** @param array<string, string> $extraParts */
    public static function docx(string $text = 'Perjanjian sewaan tanah', string $name = 'perjanjian.docx', array $extraParts = [], bool $macro = false): UploadedFile
    {
        $types = $macro
            ? '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.ms-word.document.macroEnabled.main+xml"/></Types>'
            : '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';

        return self::zip($name, [
            '[Content_Types].xml' => '<?xml version="1.0"?>'.$types,
            '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p></w:body></w:document>',
            ...($macro ? ['word/vbaProject.bin' => 'macro'] : []),
            ...$extraParts,
        ]);
    }

    public static function kml(string $name = 'sempadan.kml'): UploadedFile
    {
        return self::fromString('<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Placemark><name>Lot 1234 Mukim Kapar</name><Point><coordinates>101.4,3.1</coordinates></Point></Placemark></kml>', $name);
    }

    public static function geojson(string $name = 'lot.geojson'): UploadedFile
    {
        return self::fromString(json_encode(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'properties' => ['no_lot' => 'PT 5678'], 'geometry' => ['type' => 'Point', 'coordinates' => [101.4, 3.1]]]]]), $name);
    }

    public static function kmz(string $name = 'sempadan.kmz'): UploadedFile
    {
        return self::zip($name, ['doc.kml' => (string) self::kml()->getContent()]);
    }

    /** Shapefile bundle (.zip) with a valid .shp header. */
    public static function shapefileZip(string $name = 'lot.zip', bool $complete = true, array $extra = []): UploadedFile
    {
        $files = ['lot.shp' => self::shpHeader(), 'lot.dbf' => "\x03dbf", 'lot.prj' => 'GEOGCS["WGS 84"]', ...$extra];
        if ($complete) {
            $files['lot.shx'] = self::shpHeader();
        }

        return self::zip($name, $files);
    }

    public static function shp(string $name = 'lot.shp'): UploadedFile
    {
        return self::fromString(self::shpHeader(), $name);
    }

    public static function dwg(string $name = 'pelan.dwg'): UploadedFile
    {
        return self::fromString('AC1032'.str_repeat("\x00", 200), $name);
    }

    public static function exe(string $name = 'setup.exe'): UploadedFile
    {
        return self::fromString('MZ'.str_repeat("\x90", 200).'This program cannot be run in DOS mode', $name);
    }

    public static function eicar(string $name = 'eicar.pdf'): UploadedFile
    {
        // Valid PDF signature so it reaches the antivirus step.
        return self::fromString("%PDF-1.4\n".FakeVirusScanner::EICAR."\n%%EOF", $name);
    }

    public static function sized(int $bytes, string $name = 'besar.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'dmsbig');
        $f = fopen($path, 'wb');
        fwrite($f, "%PDF-1.4\n");
        fseek($f, $bytes - 6);
        fwrite($f, "%%EOF\n");
        fclose($f);

        return new UploadedFile($path, $name, null, null, true);
    }

    /** @param array<string, string> $entries */
    public static function zip(string $name, array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'dmszip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $entry => $content) {
            $zip->addFromString($entry, $content);
        }
        $zip->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    public static function zipString(array $entries): string
    {
        $file = self::zip('x.zip', $entries);

        return (string) file_get_contents($file->getRealPath());
    }

    public static function fromString(string $content, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'dmsf');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private static function shpHeader(): string
    {
        // file code 9994 (BE), 5 unused, file length (BE, 16-bit words), version 1000 (LE), shape type 1 (LE), bbox.
        return pack('N', 9994).str_repeat("\x00", 20).pack('N', 50).pack('V', 1000).pack('V', 1).str_repeat("\x00", 64);
    }
}
