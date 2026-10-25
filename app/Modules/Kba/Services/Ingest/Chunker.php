<?php

namespace App\Modules\Kba\Services\Ingest;

/**
 * Splits Markdown into citable segments by heading / section (SRS-KBA-04):
 * every segment keeps its section number, title and heading path so a
 * citation can name "SRS SISDATA v3 · §5.2 Keperluan merentas modul" and open
 * the source at that place. Long sections are cut on paragraph / table-row
 * boundaries; a table split across segments repeats its header row so each
 * piece stands on its own.
 */
class Chunker
{
    public function __construct(private int $max = 1400, private int $min = 200) {}

    /**
     * @return list<array{nombor_seksyen: string|null, tajuk_seksyen: string|null, laluan_seksyen: string|null, kandungan_teks: string}>
     */
    public function chunk(string $markdown): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $h1Count = 0;
        $fence = false;
        foreach ($lines as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line)) {
                $fence = ! $fence;
            } elseif (! $fence && preg_match('/^#\s+\S/', $line)) {
                $h1Count++;
            }
        }

        $out = [];
        /** @var array<int, string> $stack level => title */
        $stack = [];
        $body = [];
        $fence = false;
        $flush = function () use (&$out, &$stack, &$body, $h1Count) {
            $text = trim(implode("\n", $body));
            $body = [];
            if ($text === '' || mb_strlen(preg_replace('/[\s|:\-#*_>]+/u', '', $text) ?? '') < 3) {
                return;
            }
            ksort($stack);
            $path = [];
            foreach ($stack as $level => $title) {
                if ($level === 1 && $h1Count === 1) {
                    continue; // the document title itself
                }
                $path[] = $title;
            }
            $title = $path === [] ? null : $path[count($path) - 1];
            $number = null;
            foreach (array_reverse($path) as $t) {
                if (($number = $this->number($t)) !== null) {
                    break;
                }
            }
            foreach ($this->split($text) as $piece) {
                $out[] = [
                    'nombor_seksyen' => $number,
                    'tajuk_seksyen' => $title !== null ? mb_substr($title, 0, 300) : null,
                    'laluan_seksyen' => $path === [] ? null : mb_substr(implode(' › ', $path), 0, 600),
                    'kandungan_teks' => $piece,
                ];
            }
        };

        foreach ($lines as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line)) {
                $fence = ! $fence;
                $body[] = $line;

                continue;
            }
            if (! $fence && preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/u', $line, $m)) {
                $flush();
                $level = strlen($m[1]);
                foreach (array_keys($stack) as $l) {
                    if ($l >= $level) {
                        unset($stack[$l]);
                    }
                }
                $stack[$level] = $this->cleanTitle($m[2]);

                continue;
            }
            $body[] = $line;
        }
        $flush();

        return $out;
    }

    /** "5.2 Keperluan merentas modul" → "5.2"; "Modul K1 · Pangkalan…" → "K1"; "SRS-KBA-05 …" → "SRS-KBA-05". */
    private function number(string $title): ?string
    {
        if (preg_match('/^((?:\d+\.)*\d+)\.?(?:\s|$)/u', $title, $m)) {
            return $m[1];
        }
        if (preg_match('/^(?:Modul|Module|Lampiran|Appendix|Bab|Chapter|Fasa|Halaman|Page)\s+([A-Z]?\d+[A-Za-z]?)/u', $title, $m)) {
            return $m[1];
        }
        if (preg_match('/^([A-Z]{2,5}-[A-Z]{2,5}-\d{2,3})\b/u', $title, $m)) {
            return $m[1];
        }

        return null;
    }

    private function cleanTitle(string $title): string
    {
        $title = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $title) ?? $title;

        return trim(str_replace(['**', '__', '`'], '', $title));
    }

    /** @return list<string> */
    private function split(string $text): array
    {
        if (mb_strlen($text) <= $this->max) {
            return [$text];
        }

        $pieces = [];
        $current = '';
        foreach ($this->blocks($text) as $block) {
            foreach ($this->fit($block) as $part) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($part) + 2 > $this->max) {
                    $pieces[] = $current;
                    $current = '';
                }
                $current = $current === '' ? $part : $current."\n\n".$part;
            }
        }
        if ($current !== '') {
            $last = count($pieces) - 1;
            if ($last >= 0 && mb_strlen($current) < $this->min && mb_strlen($pieces[$last]) + mb_strlen($current) <= (int) ($this->max * 1.3)) {
                $pieces[$last] .= "\n\n".$current;
            } else {
                $pieces[] = $current;
            }
        }

        return $pieces;
    }

    /** @return list<string> paragraphs and whole tables */
    private function blocks(string $text): array
    {
        $blocks = [];
        $buf = [];
        $table = false;
        foreach (explode("\n", $text) as $line) {
            $isRow = str_starts_with(ltrim($line), '|');
            if (trim($line) === '' || $isRow !== $table) {
                if ($buf !== []) {
                    $blocks[] = implode("\n", $buf);
                }
                $buf = [];
                $table = $isRow;
                if (trim($line) === '') {
                    continue;
                }
            }
            $buf[] = $line;
        }
        if ($buf !== []) {
            $blocks[] = implode("\n", $buf);
        }

        return $blocks;
    }

    /** @return list<string> a block cut to ≤ max (tables by rows with their header repeated, prose by sentences) */
    private function fit(string $block): array
    {
        if (mb_strlen($block) <= $this->max) {
            return [$block];
        }
        $lines = explode("\n", $block);
        if (str_starts_with(ltrim($lines[0]), '|')) {
            $header = [];
            if (isset($lines[1]) && preg_match('/^\s*\|[\s:|-]+\|\s*$/', $lines[1])) {
                $header = [$lines[0], $lines[1]];
                $lines = array_slice($lines, 2);
            }
            $out = [];
            $cur = $header;
            foreach ($lines as $row) {
                if (count($cur) > count($header) && mb_strlen(implode("\n", $cur)) + mb_strlen($row) + 1 > $this->max) {
                    $out[] = implode("\n", $cur);
                    $cur = $header;
                }
                $cur[] = mb_strlen($row) > $this->max ? mb_substr($row, 0, $this->max) : $row;
            }
            if (count($cur) > count($header)) {
                $out[] = implode("\n", $cur);
            }

            return $out;
        }

        $sentences = preg_split('/(?<=[.!?;:])\s+/u', $block) ?: [$block];
        $out = [];
        $cur = '';
        foreach ($sentences as $s) {
            while (mb_strlen($s) > $this->max) {
                $out[] = mb_substr($s, 0, $this->max);
                $s = mb_substr($s, $this->max);
            }
            if ($cur !== '' && mb_strlen($cur) + mb_strlen($s) + 1 > $this->max) {
                $out[] = $cur;
                $cur = '';
            }
            $cur = $cur === '' ? $s : $cur.' '.$s;
        }
        if ($cur !== '') {
            $out[] = $cur;
        }

        return $out;
    }
}
