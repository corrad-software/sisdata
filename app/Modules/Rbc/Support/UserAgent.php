<?php

namespace App\Modules\Rbc\Support;

/**
 * Minimal User-Agent classification for the login log (SRS-RBC-21) and the
 * session/device fingerprint (SRS-RBC-20). Deliberately coarse: the
 * fingerprint must survive browser auto-updates, so it uses browser family
 * + platform + device type, never version numbers.
 */
final class UserAgent
{
    public function __construct(
        public readonly string $pelayar,
        public readonly string $platform,
        public readonly string $peranti,
    ) {}

    public static function parse(?string $ua): self
    {
        $ua = (string) $ua;

        $pelayar = match (true) {
            $ua === '' => 'Tidak diketahui',
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            str_contains($ua, 'curl/') => 'curl',
            str_contains($ua, 'Symfony') => 'Symfony',
            default => 'Lain-lain',
        };

        $platform = match (true) {
            $ua === '' => 'Tidak diketahui',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Lain-lain',
        };

        $peranti = match (true) {
            str_contains($ua, 'iPad') || str_contains($ua, 'Tablet') => 'tablet',
            str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone') || str_contains($ua, 'Android') => 'telefon',
            default => 'desktop',
        };

        return new self($pelayar, $platform, $peranti);
    }

    public function fingerprint(): string
    {
        return hash('sha256', strtolower($this->pelayar.'|'.$this->platform.'|'.$this->peranti));
    }
}
