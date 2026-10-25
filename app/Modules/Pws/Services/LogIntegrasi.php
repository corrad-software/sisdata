<?php

namespace App\Modules\Pws\Services;

use App\Modules\Adm\Contracts\Integrations;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * SRS-PWS-22 / SRS-INT-06: every inbound call from the portal and every
 * outbound call MOD-PWS makes on its behalf is logged with a payload SUMMARY
 * (keys + sizes, personal fields masked — never the full payload), status,
 * duration and reference. Also fed to MOD-ADM's integration log so the
 * integration health screen sees the portal link (SRS-INT-12).
 */
class LogIntegrasi
{
    public const KOD = 'portal_hws';

    /** Keys whose values are never kept, even in a summary. */
    private const PERIBADI = ['no_pengenalan', 'emel', 'email', 'no_telefon', 'telefon', 'nama', 'nama_pewakaf', 'alamat', 'pemohon', 'client_secret', 'rahsia', 'password', 'captcha', 'pengesahan', 'keterangan', 'fail'];

    private static ?int $klien = null;

    private static ?int $retries = null;

    public static function forClient(?int $klienId): void
    {
        self::$klien = $klienId;
    }

    public static function retry(?int $count): void
    {
        self::$retries = $count;
    }

    /** @param array<string, mixed> $payload */
    public function write(string $direction, string $method, string $endpoint, ?int $status, ?int $ms, ?string $reference = null, array $payload = [], ?string $error = null, ?string $ip = null, ?int $bytes = null): void
    {
        try {
            DB::table('pws_log_integrasi')->insert([
                'arah' => $direction,
                'endpoint' => mb_substr($endpoint, 0, 200),
                'kaedah' => $method,
                'klien_id' => self::$klien,
                'nombor_rujukan' => $reference !== null ? mb_substr($reference, 0, 80) : null,
                'ringkasan_payload' => json_encode(self::summary($payload)),
                'kod_status' => $status,
                'bilangan_cuba_semula' => self::$retries ?? 0,
                'mesej_ralat' => $error !== null && $error !== '' ? mb_substr($error, 0, 500) : null,
                'tempoh_ms' => $ms,
                'saiz_bait' => $bytes,
                'ip' => $ip,
                'id_permintaan' => request()->headers->get('X-Request-Id'),
                'cap_masa' => now(),
            ]);
            if (app()->bound(Integrations::class)) {
                app(Integrations::class)->log(self::KOD, $status !== null && $status < 400, $status, $ms, $error, $method.' '.$endpoint, null, [], $direction);
            }
        } catch (Throwable $e) {
            report($e); // logging must never break the call
        }
    }

    /**
     * Keys and value shapes only; personal fields masked.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function summary(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            $digits = is_scalar($value) ? preg_replace('/[\s\-]/', '', (string) $value) : '';
            if (in_array((string) $key, self::PERIBADI, true) || preg_match('/kad|card|cvv|cvc|pin|bank|akaun|secret|token/i', (string) $key)
                || preg_match('/^\d{12,19}$/', (string) $digits)) {
                $out[$key] = '***'; // personal data, credentials or anything shaped like a card number (PCI-DSS)
            } elseif (is_array($value)) {
                $out[$key] = '['.count($value).']';
            } elseif (is_string($value)) {
                $out[$key] = mb_strlen($value) > 40 ? mb_substr($value, 0, 40).'…' : $value;
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
