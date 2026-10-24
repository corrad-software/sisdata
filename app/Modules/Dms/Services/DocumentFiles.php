<?php

namespace App\Modules\Dms\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenVersi;
use App\Modules\Dms\Services\Storage\DocumentVault;
use App\Modules\Dms\Services\Upload\FileTypes;
use App\Modules\Dms\Services\Watermark\Watermarker;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-DMS-02/22/24/26: short-lived signed links and the responses behind
 * them. Content is always decrypted on the fly from the vault (the bucket is
 * never exposed). Every download / preview / print is audited.
 */
class DocumentFiles
{
    public const MUAT_TURUN = 'muat_turun';

    public const PRATONTON = 'pratonton';

    public const CETAK = 'cetak';

    public const KINDS = [self::MUAT_TURUN, self::PRATONTON, self::CETAK];

    public function __construct(
        private DocumentVault $vault,
        private Watermarker $watermarker,
        private AuditLogger $audit,
        private DmsSettings $settings,
    ) {}

    /** Relative signed URL bound to the user; the route re-checks access when it is used. */
    public function signedUrl(Dokumen $doc, User $user, string $kind, ?int $version = null, ?int $minutes = null): string
    {
        return URL::temporarySignedRoute('dms.fail', now()->addMinutes($minutes ?? $this->settings->linkMinutes()), array_filter([
            'dokumen' => $doc->id,
            'jenis' => $kind,
            'versi' => $version,
            'u' => $user->id,
        ], fn ($v) => $v !== null), absolute: false);
    }

    public function serve(Dokumen $doc, DokumenVersi $version, User $user, string $kind): Response
    {
        if ($version->kunci_storan === null || $version->kunci_data_bersulit === null) {
            throw new HttpException(410, __('dms::mesej.dilupus'));
        }

        $meta = ['versi' => $version->no_versi, 'nama_fail' => $version->nama_fail, 'klasifikasi' => $doc->klasifikasi_keselamatan];

        if ($kind === self::MUAT_TURUN) {
            $this->audit->record('muat_turun', 'dms', 'dokumen', $doc->id, meta: $meta);

            return $this->stream($version, 'attachment');
        }

        $extension = strtolower(pathinfo($version->nama_fail, PATHINFO_EXTENSION));
        $mode = FileTypes::previewable($extension ?: $doc->sambungan);
        if (! in_array($mode, ['pdf', 'imej'], true)) {
            throw new HttpException(415, __('dms::mesej.pratonton_tidak_disokong'));
        }

        $watermark = $doc->isTerhad();
        $this->audit->record($kind === self::CETAK ? 'cetak' : 'pratonton', 'dms', 'dokumen', $doc->id, meta: [...$meta, 'tanda_air' => $watermark]);

        if (! $watermark) {
            return $this->stream($version, 'inline');
        }

        $plain = $this->vault->toTempFile($version->kunci_storan, $version->kunci_data_bersulit);
        try {
            $text = $this->watermarker->text($doc->klasifikasi_keselamatan, $user->id, $user->name, now());
            if ($mode === 'pdf') {
                $content = $this->watermarker->pdf($plain, $text);
                $mime = 'application/pdf';
                $name = $version->nama_fail;
            } else {
                ['content' => $content, 'mime' => $mime] = $this->watermarker->image($plain, $text);
                $name = pathinfo($version->nama_fail, PATHINFO_FILENAME).'.jpg';
            }
        } finally {
            @unlink($plain);
        }

        return new Response($content, 200, $this->headers($mime, $name, 'inline') + ['Content-Length' => (string) strlen($content)]);
    }

    private function stream(DokumenVersi $version, string $disposition): StreamedResponse
    {
        $key = (string) $version->kunci_storan;
        $wrapped = (string) $version->kunci_data_bersulit;

        return new StreamedResponse(function () use ($key, $wrapped) {
            $out = fopen('php://output', 'wb');
            $this->vault->decryptTo($key, $wrapped, $out);
            fclose($out);
        }, 200, $this->headers($version->jenis_mime, $version->nama_fail, $disposition) + ['Content-Length' => (string) $version->saiz_bait]);
    }

    /** @return array<string, string> */
    private function headers(string $mime, string $name, string $disposition): array
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'dokumen';

        return [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $name, $fallback),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}
