<?php

namespace App\Modules\Dms\Services\Upload;

use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Services\DmsSettings;
use ZipArchive;

/**
 * Pre-storage checks, in the order of SRS Modul 11 process step 2:
 * name → absolute block list (09) → whitelist (03) → content signature +
 * MIME (04) → size per category (05). Antivirus (07) runs after this, in
 * DocumentIngestor. Every failure throws DocumentRejected with a Malay
 * message naming the file and the reason.
 */
class FileInspector
{
    private const ZIP_MAX_EXPANDED = 4 * 1024 ** 3;

    private const ZIP_MAX_RATIO = 250;

    public function __construct(private DmsSettings $settings) {}

    /**
     * @return array{extension: string, mime: string, group: string}
     *
     * @throws DocumentRejected
     */
    public function inspect(string $path, string $originalName, int $size, DokumenKategori $kategori): array
    {
        $name = $this->displayName($originalName);
        $extension = $this->extension($originalName);

        if ($extension === '') {
            throw $this->reject('tiada_sambungan', ['nama' => $name]);
        }
        if (in_array($extension, FileTypes::BLOCKED, true)) {
            throw $this->reject('disekat', ['nama' => $name, 'jenis' => '.'.$extension]);
        }

        $type = FileTypes::for($extension);
        $whitelist = $this->settings->whitelist();
        if ($type === null || ! in_array($type[0], $whitelist, true)) {
            throw $this->reject('tidak_dibenarkan', ['nama' => $name, 'jenis' => '.'.$extension, 'senarai' => $this->listLabel($whitelist)]);
        }
        $allowed = $kategori->jenis_fail_dibenarkan;
        if (is_array($allowed) && $allowed !== [] && ! in_array($type[0], array_map('strtolower', $allowed), true) && ! in_array($extension, array_map('strtolower', $allowed), true)) {
            throw $this->reject('tidak_dibenarkan_kategori', [
                'nama' => $name, 'jenis' => '.'.$extension, 'kategori' => $kategori->nama,
                'senarai' => $this->listLabel(array_values(array_intersect(array_map('strtolower', $allowed), $whitelist))),
            ]);
        }

        if ($size <= 0) {
            throw $this->reject('fail_kosong', ['nama' => $name]);
        }

        $head = $this->head($path, 8192);
        if ($head === null) {
            throw $this->reject('fail_rosak', ['nama' => $name]);
        }
        if ($this->looksExecutable($head)) {
            throw $this->reject('kandungan_boleh_laksana', ['nama' => $name]);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! $this->signatureMatches($extension, $head, $path, $size, $name) || ! in_array($mime, $type[3], true)) {
            throw $this->reject('tidak_sepadan', ['nama' => $name, 'jenis' => $extension]);
        }

        $limitMb = $kategori->had_saiz_mb ?: $this->settings->sizeLimitMb($type[1]);
        if ($size > $limitMb * 1024 * 1024) {
            throw $this->reject('terlalu_besar', [
                'nama' => $name, 'saiz' => number_format($size / 1048576, 1), 'had' => $limitMb, 'kategori' => $kategori->nama,
            ]);
        }

        return ['extension' => $extension, 'mime' => $type[2], 'group' => $type[1]];
    }

    public function extension(string $originalName): string
    {
        $base = basename(str_replace('\\', '/', $originalName));
        $dot = strrpos($base, '.');
        if ($dot === false || $dot === 0 || $dot === strlen($base) - 1) {
            return '';
        }

        return strtolower(substr($base, $dot + 1));
    }

    public function displayName(string $originalName): string
    {
        $base = basename(str_replace('\\', '/', $originalName));
        $base = preg_replace('/[\x00-\x1F\x7F]/u', '', $base) ?? '';

        return mb_substr($base, 0, 200);
    }

    private function signatureMatches(string $ext, string $head, string $path, int $size, string $name): bool
    {
        return match ($ext) {
            'pdf' => str_starts_with(ltrim(substr($head, 0, 1024)), '%PDF-'),
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'png' => str_starts_with($head, "\x89PNG\r\n\x1A\n"),
            'tif', 'tiff' => str_starts_with($head, "II*\x00") || str_starts_with($head, "MM\x00*") || str_starts_with($head, "II+\x00") || str_starts_with($head, "MM\x00+"),
            'dwg' => (bool) preg_match('/^AC1\d{3}/', $head),
            'shp' => $this->isShp($head),
            'docx' => $this->isOoxml($path, 'word/document.xml', $name),
            'xlsx' => $this->isOoxml($path, 'xl/workbook.xml', $name),
            'pptx' => $this->isOoxml($path, 'ppt/presentation.xml', $name),
            'kmz' => $this->isKmz($path, $name),
            'zip' => $this->isShapefileBundle($path, $name),
            'kml' => $this->isKml($head),
            'geojson' => $this->isGeoJson($path, $head, $size),
            default => false,
        };
    }

    private function isShp(string $head): bool
    {
        // ESRI shapefile main file: big-endian file code 9994 at byte 0, version 1000 little-endian at byte 28.
        return strlen($head) >= 100
            && substr($head, 0, 4) === "\x00\x00\x27\x0A"
            && unpack('V', substr($head, 28, 4))[1] === 1000;
    }

    private function isOoxml(string $path, string $mainPart, string $name): bool
    {
        $zip = $this->openZip($path);
        if ($zip === null) {
            return false;
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName($mainPart) === false) {
                return false;
            }
            $types = (string) $zip->getFromName('[Content_Types].xml');
            if (stripos($types, 'macroEnabled') !== false || stripos($types, 'vbaProject') !== false) {
                throw $this->reject('bermakro', ['nama' => $name]);
            }
            $this->inspectEntries($zip, $name, allowNested: true, checkMacro: true);

            return true;
        } finally {
            $zip->close();
        }
    }

    private function isKmz(string $path, string $name): bool
    {
        $zip = $this->openZip($path);
        if ($zip === null) {
            return false;
        }

        try {
            $entries = $this->inspectEntries($zip, $name, allowNested: true);
            foreach ($entries as $entry) {
                if (str_ends_with(strtolower($entry), '.kml')) {
                    return $this->isKml((string) $zip->getFromName($entry, 8192));
                }
            }

            return false;
        } finally {
            $zip->close();
        }
    }

    private function isShapefileBundle(string $path, string $name): bool
    {
        $zip = $this->openZip($path);
        if ($zip === null) {
            return false;
        }

        try {
            $entries = $this->inspectEntries($zip, $name, allowNested: false);
            $sidecars = ['shp', 'shx', 'dbf', 'prj', 'cpg', 'sbn', 'sbx', 'xml', 'qix', 'fix', 'qmd', 'atx', 'ain', 'aih', 'ixs', 'mxs'];
            $seen = [];
            foreach ($entries as $entry) {
                $ext = $this->extension($entry);
                if (! in_array($ext, $sidecars, true)) {
                    throw $this->reject('shp_tidak_lengkap', ['nama' => $name]);
                }
                $seen[$ext] = true;
                if ($ext === 'shp' && ! $this->isShp((string) $zip->getFromName($entry, 100))) {
                    return false;
                }
            }
            if (! isset($seen['shp'], $seen['shx'], $seen['dbf'])) {
                throw $this->reject('shp_tidak_lengkap', ['nama' => $name]);
            }

            return true;
        } finally {
            $zip->close();
        }
    }

    private function isKml(string $head): bool
    {
        $text = ltrim($this->stripBom($head));

        return (str_starts_with($text, '<?xml') || str_starts_with($text, '<kml'))
            && preg_match('/<kml[\s>]/i', $text) === 1;
    }

    private function isGeoJson(string $path, string $head, int $size): bool
    {
        $text = ltrim($this->stripBom($head));
        if (! str_starts_with($text, '{')) {
            return false;
        }
        if (preg_match('/"type"\s*:\s*"(FeatureCollection|Feature|Point|MultiPoint|LineString|MultiLineString|Polygon|MultiPolygon|GeometryCollection)"/', (string) $this->head($path, 1048576)) !== 1) {
            return false;
        }

        // json_validate doesn't build the structure, but still reads the whole string; cap it.
        return $size > 100 * 1048576 || json_validate((string) file_get_contents($path));
    }

    /**
     * Walk a zip's entries: blocked types, zip bombs, nesting depth.
     *
     * @return list<string> file entries (directories and OS junk excluded)
     */
    private function inspectEntries(ZipArchive $zip, string $name, bool $allowNested, bool $checkMacro = false, int $depth = 1): array
    {
        $files = [];
        $expanded = 0;
        $compressed = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $entry = $stat['name'];
            if (str_ends_with($entry, '/') || str_starts_with($entry, '__MACOSX/') || basename($entry) === '.DS_Store') {
                continue;
            }

            $expanded += $stat['size'];
            $compressed += $stat['comp_size'];

            $ext = $this->extension($entry);
            if ($checkMacro && strtolower(basename($entry)) === 'vbaproject.bin') {
                throw $this->reject('bermakro', ['nama' => $name]);
            }
            if ($ext === '' && ! str_contains(basename($entry), '.') && ! $checkMacro) {
                // Extension-less entries inside KMZ / shapefile bundles are not expected.
                throw $this->reject('tiada_sambungan', ['nama' => $name]);
            }
            // OOXML legitimately carries .bin parts (printer settings, OLE objects); macros are caught above.
            if (in_array($ext, FileTypes::BLOCKED, true) && ! ($checkMacro && $ext === 'bin')) {
                throw $this->reject('disekat', ['nama' => $name, 'jenis' => '.'.$ext]);
            }

            if (in_array($ext, FileTypes::ARCHIVES, true)) {
                if (! $allowNested || $depth >= 2) {
                    throw $this->reject('arkib_bersarang', ['nama' => $name]);
                }
                $this->inspectInnerArchive($zip, $entry, $name, $depth);
            }

            $files[] = $entry;
        }

        if ($expanded > self::ZIP_MAX_EXPANDED || ($compressed > 0 && $expanded / $compressed > self::ZIP_MAX_RATIO)) {
            throw $this->reject('arkib_bom', ['nama' => $name]);
        }

        return $files;
    }

    /** One level of nesting is allowed (e.g. a spreadsheet embedded in a DOCX); its contents may not nest further. */
    private function inspectInnerArchive(ZipArchive $zip, string $entry, string $name, int $depth): void
    {
        $content = $zip->getFromName($entry, 64 * 1048576);
        if (! is_string($content) || ! str_starts_with($content, "PK\x03\x04")) {
            // rar/7z/gz… can't be inspected here, so their depth can't be proven.
            throw $this->reject('arkib_bersarang', ['nama' => $name]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'dmsz');
        file_put_contents($tmp, $content);
        try {
            $inner = $this->openZip($tmp);
            if ($inner === null) {
                throw $this->reject('fail_rosak', ['nama' => $name]);
            }
            try {
                $this->inspectEntries($inner, $name, allowNested: true, checkMacro: true, depth: $depth + 1);
            } finally {
                $inner->close();
            }
        } finally {
            @unlink($tmp);
        }
    }

    private function openZip(string $path): ?ZipArchive
    {
        $zip = new ZipArchive;

        return $zip->open($path, ZipArchive::RDONLY) === true ? $zip : null;
    }

    private function looksExecutable(string $head): bool
    {
        return str_starts_with($head, 'MZ')
            || str_starts_with($head, "\x7FELF")
            || in_array(substr($head, 0, 4), ["\xFE\xED\xFA\xCE", "\xFE\xED\xFA\xCF", "\xCE\xFA\xED\xFE", "\xCF\xFA\xED\xFE", "\xCA\xFE\xBA\xBE"], true)
            || str_starts_with($head, '#!');
    }

    private function head(string $path, int $bytes): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $data = fread($handle, $bytes);
        fclose($handle);

        return $data === false ? null : $data;
    }

    private function stripBom(string $text): string
    {
        return str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
    }

    /** @param list<string> $types */
    private function listLabel(array $types): string
    {
        return implode(', ', array_map('strtoupper', $types));
    }

    /** @param array<string, scalar> $replace */
    private function reject(string $reason, array $replace): DocumentRejected
    {
        return new DocumentRejected(__('dms::mesej.ditolak.'.$reason, $replace), $reason);
    }
}
