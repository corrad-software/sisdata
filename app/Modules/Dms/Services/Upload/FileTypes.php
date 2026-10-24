<?php

namespace App\Modules\Dms\Services\Upload;

/**
 * Known file types (SRS-DMS-03/04/09). The whitelist is admin-configurable
 * but can only enable types listed here, because each needs a content check.
 */
final class FileTypes
{
    /**
     * extension => [whitelist code, size group, canonical MIME, accepted finfo MIMEs]
     *
     * SHP decision: a lone .shp is accepted (header file code 9994), and a
     * Shapefile bundle arrives as .zip — accepted only when "shp" is
     * whitelisted and the archive holds exactly a shapefile set
     * (.shp/.shx/.dbf + .prj/.cpg/... sidecars), nothing else.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: list<string>}>
     */
    public const TYPES = [
        'pdf' => ['pdf', 'pejabat', 'application/pdf', ['application/pdf']],
        'jpg' => ['jpg', 'pejabat', 'image/jpeg', ['image/jpeg']],
        'jpeg' => ['jpeg', 'pejabat', 'image/jpeg', ['image/jpeg']],
        'png' => ['png', 'pejabat', 'image/png', ['image/png']],
        'tif' => ['tiff', 'pejabat', 'image/tiff', ['image/tiff']],
        'tiff' => ['tiff', 'pejabat', 'image/tiff', ['image/tiff']],
        'docx' => ['docx', 'pejabat', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', self::ZIP_MIMES],
        'xlsx' => ['xlsx', 'pejabat', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', self::ZIP_MIMES],
        'pptx' => ['pptx', 'pejabat', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', self::ZIP_MIMES],
        'dwg' => ['dwg', 'spatial', 'image/vnd.dwg', ['image/vnd.dwg', 'image/x-dwg', 'application/acad', 'application/x-acad', 'application/dwg', 'application/x-dwg', 'application/octet-stream']],
        'shp' => ['shp', 'spatial', 'application/x-esri-shape', ['application/octet-stream', 'application/x-esri-shape', 'application/vnd.shp']],
        'zip' => ['shp', 'spatial', 'application/zip', ['application/zip', 'application/x-zip-compressed']],
        'kml' => ['kml', 'spatial', 'application/vnd.google-earth.kml+xml', ['text/xml', 'application/xml', 'text/plain', 'application/vnd.google-earth.kml+xml', 'text/html']],
        'kmz' => ['kmz', 'spatial', 'application/vnd.google-earth.kmz', ['application/zip', 'application/vnd.google-earth.kmz', 'application/octet-stream']],
        'geojson' => ['geojson', 'spatial', 'application/geo+json', ['application/json', 'text/plain', 'application/geo+json', 'text/x-c', 'text/x-java']],
    ];

    private const ZIP_MIMES = [
        'application/zip',
        'application/octet-stream',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /**
     * SRS-DMS-09 absolute block list — not configurable, checked before the whitelist.
     */
    public const BLOCKED = [
        // executables & installers
        'exe', 'com', 'bat', 'cmd', 'sh', 'msi', 'msp', 'mst', 'scr', 'pif', 'cpl', 'dll', 'sys', 'drv', 'ocx',
        'app', 'apk', 'ipa', 'dmg', 'pkg', 'deb', 'rpm', 'bin', 'run', 'elf', 'out', 'gadget', 'jar', 'class',
        // scripts
        'js', 'jse', 'mjs', 'vbs', 'vbe', 'wsf', 'wsh', 'ws', 'ps1', 'psm1', 'psd1', 'hta', 'lnk', 'inf', 'reg',
        'php', 'phtml', 'phar', 'py', 'pyc', 'pl', 'rb', 'cgi', 'asp', 'aspx', 'jsp', 'bash', 'zsh', 'csh', 'ksh', 'command', 'applescript', 'scpt',
        // macro-enabled office
        'docm', 'dotm', 'xlsm', 'xltm', 'xlam', 'xlsb', 'pptm', 'potm', 'ppam', 'ppsm', 'sldm',
        // disk images
        'iso', 'img', 'vhd', 'vhdx', 'vmdk',
    ];

    /** Archive extensions that count as one nesting level. */
    public const ARCHIVES = ['zip', 'kmz', 'rar', '7z', 'gz', 'tgz', 'tar', 'bz2', 'xz', 'cab', 'arj', 'lz', 'lzh', 'zst', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'jar'];

    /** @return array{0: string, 1: string, 2: string, 3: list<string>}|null */
    public static function for(string $extension): ?array
    {
        return self::TYPES[$extension] ?? null;
    }

    public static function group(string $extension): string
    {
        return self::TYPES[$extension][1] ?? 'pejabat';
    }

    /**
     * Extensions the frontend can put in <input accept>.
     *
     * @param  list<string>  $whitelist
     * @return list<string>
     */
    public static function extensionsFor(array $whitelist): array
    {
        return array_keys(array_filter(self::TYPES, fn (array $t) => in_array($t[0], $whitelist, true)));
    }

    public static function previewable(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'pdf',
            'jpg', 'jpeg', 'png' => 'imej',
            // No LibreOffice / TIFF decoder on the server: show the extracted (or OCR) text instead.
            'docx', 'xlsx', 'pptx', 'kml', 'geojson', 'tif', 'tiff' => 'teks',
            default => 'tiada',
        };
    }
}
