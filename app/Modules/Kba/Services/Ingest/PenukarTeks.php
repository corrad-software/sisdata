<?php

namespace App\Modules\Kba\Services\Ingest;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * Turns an uploaded manual / SOP (Markdown, plain text, PDF, DOCX) into
 * Markdown with headings, so the Chunker can cite sections. Uploaded files
 * themselves are kept by MOD-DMS (DocumentStore); this runs on the upload
 * before DMS encrypts it, and on DMS's own extracted text for documents
 * other modules store.
 */
class PenukarTeks
{
    public const FORMAT = ['md' => 'markdown', 'markdown' => 'markdown', 'txt' => 'teks', 'pdf' => 'pdf', 'docx' => 'docx'];

    public function __construct(private string $pdftotext = 'pdftotext') {}

    public function fromFile(string $path, string $extension): string
    {
        return match (strtolower($extension)) {
            'md', 'markdown' => (string) file_get_contents($path),
            'txt' => $this->plainToMarkdown((string) file_get_contents($path)),
            'pdf' => $this->pdf($path),
            'docx' => $this->docx($path),
            default => throw new RuntimeException('Format tidak disokong: '.$extension),
        };
    }

    /**
     * Plain extracted text (PDF / OCR / DMS) → Markdown: numbered or upper-case
     * short lines become headings; otherwise pages become "Halaman N".
     */
    public function plainToMarkdown(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $pages = str_contains($text, "\f") ? explode("\f", $text) : [$text];
        $headings = 0;
        $converted = [];
        foreach ($pages as $page) {
            $lines = [];
            foreach (explode("\n", $page) as $line) {
                $trim = trim($line);
                $len = mb_strlen($trim);
                if ($len >= 3 && $len <= 90 && ! preg_match('/[.,;]$/u', $trim)) {
                    if (preg_match('/^((?:\d+\.)*\d+)\.?\s+\p{Lu}/u', $trim, $m)) {
                        $lines[] = str_repeat('#', min(6, substr_count($m[1], '.') + 2)).' '.$trim;
                        $headings++;

                        continue;
                    }
                    if ($len >= 4 && preg_match('/^[\p{Lu}\d\s&\/,()\-–:]+$/u', $trim) && preg_match('/\p{Lu}{3}/u', $trim)) {
                        $lines[] = '## '.$trim;
                        $headings++;

                        continue;
                    }
                }
                $lines[] = $line;
            }
            $converted[] = implode("\n", $lines);
        }

        if ($headings >= 2 || count($pages) === 1) {
            return implode("\n\n", $converted);
        }

        $out = [];
        foreach ($pages as $i => $page) {
            if (trim($page) !== '') {
                $out[] = '## Halaman '.($i + 1)."\n\n".trim($page);
            }
        }

        return implode("\n\n", $out);
    }

    private function pdf(string $path): string
    {
        $run = Process::timeout(120)->run([$this->pdftotext, '-q', '-enc', 'UTF-8', $path, '-']);
        if (! $run->successful()) {
            throw new RuntimeException('Teks PDF tidak dapat diekstrak.');
        }

        return $this->plainToMarkdown($run->output());
    }

    private function docx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Fail DOCX tidak sah.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml) || $xml === '') {
            throw new RuntimeException('Fail DOCX tiada kandungan.');
        }

        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET); // no external entities / network
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $out = [];
        $body = $xp->query('/w:document/w:body/*');
        foreach ($body === false ? [] : $body as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if ($node->localName === 'p') {
                $text = $this->runs($xp, $node);
                if ($text === '') {
                    continue;
                }
                $style = (string) ($xp->evaluate('string(w:pPr/w:pStyle/@w:val)', $node));
                if (preg_match('/^(?:Heading|Tajuk|Kepala)\s*(\d)$/i', $style, $m)) {
                    $out[] = str_repeat('#', min(6, (int) $m[1] + 1)).' '.$text;
                } elseif (strcasecmp($style, 'Title') === 0) {
                    $out[] = '# '.$text;
                } else {
                    $out[] = $text;
                }
            } elseif ($node->localName === 'tbl') {
                $rows = $xp->query('w:tr', $node);
                $first = true;
                foreach ($rows === false ? [] : $rows as $tr) {
                    $cells = [];
                    $tcs = $xp->query('w:tc', $tr);
                    foreach ($tcs === false ? [] : $tcs as $tc) {
                        $cells[] = str_replace('|', '/', $tc instanceof DOMElement ? $this->runs($xp, $tc) : '');
                    }
                    $out[] = '| '.implode(' | ', $cells).' |';
                    if ($first) {
                        $out[] = '|'.str_repeat(' --- |', max(1, count($cells)));
                        $first = false;
                    }
                }
            }
        }

        return implode("\n\n", $out);
    }

    private function runs(DOMXPath $xp, DOMElement $node): string
    {
        $parts = [];
        $texts = $xp->query('.//w:t', $node);
        foreach ($texts === false ? [] : $texts as $t) {
            $parts[] = $t->textContent;
        }

        return trim(preg_replace('/\s+/u', ' ', implode('', $parts)) ?? '');
    }
}
