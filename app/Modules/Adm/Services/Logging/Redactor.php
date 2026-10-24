<?php

namespace App\Modules\Adm\Services\Logging;

/**
 * SRS-ADM-15: logs never carry passwords, tokens, API keys, encryption keys
 * or full personal data in plain text. Applied to every log channel (message,
 * context, formatted output), the error-log table, the integration log and
 * job output.
 */
class Redactor
{
    public const MASK = '[DISEMBUNYIKAN]';

    /** Context keys whose value is never logged. */
    private const SENSITIVE_KEY = '/(pass(word|wd)?$|pass(word|wd)?_|kata_?laluan|secret|rahsia|token|api[_-]?key|access[_-]?key|private[_-]?key|encryption[_-]?key|app[_-]?key|authori[sz]ation|cookie|credential|kelayakan|nilai_tersulit|signature)/i';

    /**
     * @var array<string, string> pattern => replacement
     */
    private const PATTERNS = [
        // PEM private keys / certificates' private parts
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s' => self::MASK,
        // Authorization headers
        '/\b(Bearer|Basic)\s+[A-Za-z0-9\-._~+\/]+=*/i' => '$1 '.self::MASK,
        // JWTs
        '/\beyJ[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}\b/' => self::MASK,
        // key=value / "key":"value" pairs in query strings, JSON, DSNs
        '/((?:password|passwd|pwd|kata_laluan|secret|client_secret|token|access_token|refresh_token|api_key|apikey|api-key|private_key)["\']?\s*[:=]\s*["\']?)([^\s&"\',;}]+)/i' => '$1'.self::MASK,
        // user:password@host in URLs/DSNs
        '#(://[^:/\s@]+:)([^@\s/]+)(@)#' => '$1'.self::MASK.'$3',
        // Laravel APP_KEY style keys, AWS access keys
        '/\bbase64:[A-Za-z0-9+\/=]{32,}/' => self::MASK,
        '/\bAKIA[0-9A-Z]{16}\b/' => self::MASK,
        // Sanctum plain-text tokens "id|40chars"
        '/\b\d+\|[A-Za-z0-9]{40,}\b/' => self::MASK,
    ];

    public static function string(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $value = (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $value);

        return self::maskIc($value);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function array(array $data, int $depth = 0): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key)) {
                $data[$key] = self::MASK;

                continue;
            }

            if (is_string($value)) {
                $data[$key] = self::string($value);
            } elseif (is_array($value)) {
                $data[$key] = $depth > 8 ? self::MASK : self::array($value, $depth + 1);
            }
        }

        return $data;
    }

    /**
     * Malaysian MyKad numbers (YYMMDD-PB-###G): keep only the last 4 digits,
     * which is how SISDATA displays them to staff anyway.
     */
    public static function maskIc(string $value): string
    {
        return (string) preg_replace_callback(
            '/(?<![\d-])(\d{2})(\d{2})(\d{2})(-?)(\d{2})(-?)(\d{4})(?![\d-])/',
            function (array $m) {
                $month = (int) $m[2];
                $day = (int) $m[3];
                if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                    return $m[0];
                }

                return '******'.$m[4].'**'.$m[6].$m[7];
            },
            $value,
        );
    }
}
