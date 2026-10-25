<?php

namespace App\Modules\Tkt\Services;

/**
 * Security control "penyamaran data peribadi yang tersangkut dalam konteks
 * skrin": masks MyKad numbers, e-mail addresses and phone numbers in free
 * text, and the values of sensitive query parameters in captured URLs,
 * before anything is stored or handed to MOD-KBA.
 */
final class Penyamar
{
    private const PARAM_SENSITIF = '/^(token|access_token|password|kata_laluan|secret|rahsia|kod|code|no_kp|nokp|kp|ic|nric|mykad|emel|email|telefon|phone|signature|sig)$/i';

    public static function teks(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }
        $text = (string) preg_replace('/\b\d{6}-?\d{2}-?\d{4}\b/', '######-##-####', $text);
        $text = (string) preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[emel disamarkan]', $text);

        return (string) preg_replace('/(?<!\d)(?:\+?6?01\d)[\s\-]?\d{3,4}[\s\-]?\d{4}(?!\d)/', '[telefon disamarkan]', $text);
    }

    public static function url(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return self::teks(mb_substr($url, 0, 1000));
        }
        $query = '';
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);
            foreach ($params as $key => $value) {
                $params[$key] = preg_match(self::PARAM_SENSITIF, (string) $key) ? '***' : (is_string($value) ? self::teks($value) : $value);
            }
            $query = '?'.http_build_query($params);
        }
        $base = isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : '';

        return mb_substr($base.self::teks($parts['path'] ?? '').$query, 0, 1000);
    }
}
