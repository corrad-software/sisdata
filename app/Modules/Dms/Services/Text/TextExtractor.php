<?php

namespace App\Modules\Dms\Services\Text;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use ZipArchive;

/**
 * SRS-DMS-12: text for the full-text index.
 *  - PDF: pdftotext (poppler); if a PDF has (almost) no text layer it is a
 *    scan → pages rendered with pdftoppm and OCR'd with Tesseract.
 *  - JPG/PNG/TIFF: Tesseract OCR (languages "msa+eng" by default).
 *  - DOCX/XLSX/PPTX: text runs read straight from the OOXML parts.
 *  - KML/GeoJSON: names, descriptions and property values.
 *  - DWG/SHP: binary, metadata-only.
 */
class TextExtractor
{
    public function __construct(
        private string $pdftotext,
        private string $pdftoppm,
        private string $tesseract,
        private int $ocrMaxPages,
        private int $maxChars,
    ) {}

    /**
     * @return array{text: string, method: string}|null null when the type carries no extractable text
     */
    public function extract(string $path, string $extension, string $ocrLanguages): ?array
    {
        $result = match ($extension) {
            'pdf' => $this->pdf($path, $ocrLanguages),
            'jpg', 'jpeg', 'png', 'tif', 'tiff' => ['text' => $this->ocrImage($path, $ocrLanguages), 'method' => 'ocr'],
            'docx' => ['text' => $this->ooxml($path, '#^word/(document|header\d*|footer\d*|footnotes|endnotes)\.xml$#', 'w:t'), 'method' => 'teks'],
            'xlsx' => ['text' => $this->ooxml($path, '#^xl/(sharedStrings\.xml|worksheets/sheet\d+\.xml)$#', 't'), 'method' => 'teks'],
            'pptx' => ['text' => $this->ooxml($path, '#^ppt/(slides/slide\d+|notesSlides/notesSlide\d+)\.xml$#', 'a:t'), 'method' => 'teks'],
            'kml' => ['text' => $this->xmlText((string) file_get_contents($path, length: 20 * 1048576)), 'method' => 'teks'],
            'kmz' => ['text' => $this->kmz($path), 'method' => 'teks'],
            'geojson' => ['text' => $this->geojson($path), 'method' => 'teks'],
            default => null,
        };

        if ($result === null) {
            return null;
        }

        return ['text' => $this->clean($result['text']), 'method' => $result['method']];
    }

    /** @param 'pdftotext'|'pdftoppm'|'tesseract' $tool */
    public function available(string $tool): bool
    {
        $bin = match ($tool) {
            'pdftotext' => $this->pdftotext,
            'pdftoppm' => $this->pdftoppm,
            default => $this->tesseract,
        };

        return is_executable($this->binary($bin));
    }

    /** @return array{text: string, method: string} */
    private function pdf(string $path, string $languages): array
    {
        $text = '';
        $run = Process::timeout(120)->run([$this->pdftotext, '-q', '-enc', 'UTF-8', $path, '-']);
        if ($run->successful()) {
            $text = $run->output();
        }

        // Scanned PDF: fewer than ~20 letters/page of text layer → OCR.
        $pages = $this->pdfPageCount($path);
        $letters = preg_match_all('/\pL/u', $text);
        if ($letters >= 20 * max(1, min($pages, 5))) {
            return ['text' => $text, 'method' => 'teks'];
        }

        $dir = sys_get_temp_dir().'/dms-ocr-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($dir);
        try {
            $render = Process::timeout(300)->run([$this->pdftoppm, '-r', '200', '-gray', '-png', '-f', '1', '-l', (string) $this->ocrMaxPages, $path, $dir.'/p']);
            if (! $render->successful()) {
                return ['text' => $text, 'method' => 'teks'];
            }
            $images = glob($dir.'/p*.png') ?: [];
            sort($images, SORT_NATURAL);
            $ocr = [];
            foreach ($images as $image) {
                $ocr[] = $this->ocrImage($image, $languages);
            }

            return ['text' => trim($text."\n".implode("\n", $ocr)), 'method' => 'ocr'];
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function pdfPageCount(string $path): int
    {
        $info = Process::timeout(30)->run([dirname($this->binary($this->pdftotext)).'/pdfinfo', $path]);
        if ($info->successful() && preg_match('/^Pages:\s+(\d+)/m', $info->output(), $m)) {
            return (int) $m[1];
        }

        return 1;
    }

    private function ocrImage(string $path, string $languages): string
    {
        $run = Process::timeout(300)->run([$this->tesseract, $path, 'stdout', '-l', $languages, '--psm', '3']);
        if ($run->successful()) {
            return $run->output();
        }
        // A missing language pack shouldn't lose the whole document.
        $fallback = Process::timeout(300)->run([$this->tesseract, $path, 'stdout', '-l', 'eng']);

        return $fallback->successful() ? $fallback->output() : '';
    }

    private function ooxml(string $path, string $partPattern, string $tag): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return '';
        }
        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match($partPattern, $name)) {
                $parts[$name] = (string) $zip->getFromIndex($i, 32 * 1048576);
            }
        }
        $zip->close();
        ksort($parts, SORT_NATURAL);

        $out = [];
        $quoted = preg_quote($tag, '#');
        foreach ($parts as $xml) {
            // Paragraph / row / cell ends become whitespace so words don't run together.
            $xml = preg_replace('#</(w:p|a:p|row|si|c)>#', "$0\n", $xml) ?? $xml;
            if (preg_match_all('#<'.$quoted.'(?:\s[^>]*)?>(.*?)</'.$quoted.'>#s', $xml, $m)) {
                $out[] = implode(' ', array_map(fn ($t) => html_entity_decode($t, ENT_XML1 | ENT_QUOTES, 'UTF-8'), $m[1]));
            }
        }

        return implode("\n", $out);
    }

    private function kmz(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return '';
        }
        $text = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), '.kml')) {
                $text[] = $this->xmlText((string) $zip->getFromIndex($i, 20 * 1048576));
            }
        }
        $zip->close();

        return implode("\n", $text);
    }

    private function xmlText(string $xml): string
    {
        $xml = preg_replace('#<coordinates>.*?</coordinates>#s', ' ', $xml) ?? $xml;
        $xml = preg_replace('#<!\[CDATA\[(.*?)\]\]>#s', '$1', $xml) ?? $xml;

        return html_entity_decode(strip_tags(str_replace('>', '> ', $xml)), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function geojson(string $path): string
    {
        if (filesize($path) > 50 * 1048576) {
            return '';
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return '';
        }
        $values = [];
        $features = $data['features'] ?? [$data];
        foreach (is_array($features) ? $features : [] as $feature) {
            foreach ((array) ($feature['properties'] ?? []) as $key => $value) {
                if (is_scalar($value)) {
                    $values[] = $key.' '.$value;
                }
            }
        }

        return implode("\n", $values);
    }

    private function clean(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[^\P{C}\n\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return mb_substr(trim($text), 0, $this->maxChars);
    }

    private function binary(string $name): string
    {
        if (str_contains($name, '/')) {
            return $name;
        }
        $found = trim((string) shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));

        return $found !== '' ? $found : $name;
    }
}
