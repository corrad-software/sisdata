<?php

namespace App\Modules\Aud\Services;

/** Coarse device class from the User-Agent (SRS-AUD-04 "maklumat peranti"). */
class DeviceDetector
{
    public const DESKTOP = 'desktop';

    public const MUDAH_ALIH = 'mudah_alih';

    public const TABLET = 'tablet';

    public const BOT = 'bot';

    public const PELAYAN = 'pelayan';

    public const TIDAK_DIKETAHUI = 'tidak_diketahui';

    public static function detect(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        return match (true) {
            $ua === '' => self::TIDAK_DIKETAHUI,
            (bool) preg_match('/bot|crawl|spider|curl|wget|python-requests|guzzle|symfony/', $ua) => self::BOT,
            (bool) preg_match('/ipad|tablet|kindle|silk|android(?!.*mobile)/', $ua) => self::TABLET,
            (bool) preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/', $ua) => self::MUDAH_ALIH,
            default => self::DESKTOP,
        };
    }
}
