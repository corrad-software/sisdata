<?php

/**
 * Server-side client for the SISDATA public API (SRS-PWS-21/22/23).
 *
 *  - OAuth 2.0 client-credentials: the token is fetched with the id/secret
 *    from wp-config.php and cached in a transient until shortly before it
 *    expires; a 401 triggers one re-authentication;
 *  - every call carries Accept-Language (BM/EN) and the visitor's IP as
 *    X-Pengguna-IP so SISDATA's per-user limits apply to the member of the
 *    public, not to the portal server;
 *  - state-changing calls carry an Idempotency-Key (a retry never creates a
 *    second application or payment);
 *  - GET feeds may be cached briefly (public data only).
 */
if (! defined('ABSPATH')) {
    exit;
}

final class HWS_Api
{
    private const TOKEN_KEY = 'hws_token_akses';

    public static function configured(): bool
    {
        return defined('HWS_SISDATA_URL') && defined('HWS_CLIENT_ID') && defined('HWS_CLIENT_SECRET')
            && HWS_SISDATA_URL !== '' && HWS_CLIENT_ID !== '' && HWS_CLIENT_SECRET !== '';
    }

    /**
     * GET a public feed, cached for $ttl seconds (0 = no cache).
     *
     * @param  array<string, scalar>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function get(string $path, array $query = [], int $ttl = 0): array
    {
        $lang = hws_lang();
        $key = 'hws_c_'.md5($path.'|'.wp_json_encode($query).'|'.$lang);
        if ($ttl > 0) {
            $hit = get_transient($key);
            if (is_array($hit)) {
                return $hit;
            }
        }
        $url = self::url($path).($query ? '?'.http_build_query($query) : '');
        $res = self::send('GET', $url, null, []);
        if ($ttl > 0 && $res['status'] === 200) {
            set_transient($key, $res, $ttl);
        }

        return $res;
    }

    /**
     * POST JSON (or multipart when $files given).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, list<array{path: string, name: string, type: string}>>  $files
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function post(string $path, array $data, ?string $idempotencyKey = null, array $files = []): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        if ($files !== []) {
            $boundary = 'hws'.wp_generate_password(24, false);
            $headers['Content-Type'] = 'multipart/form-data; boundary='.$boundary;
            $body = self::multipart($data, $files, $boundary);
        } else {
            $headers['Content-Type'] = 'application/json';
            $body = wp_json_encode($data);
        }

        return self::send('POST', self::url($path), $body, $headers);
    }

    /** Connection check for the admin screen. */
    public static function ping(): array
    {
        delete_transient(self::TOKEN_KEY);
        $token = self::token();

        return ['ok' => $token !== null, 'url' => defined('HWS_SISDATA_URL') ? HWS_SISDATA_URL : null];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function send(string $method, string $url, ?string $body, array $headers, bool $retry = true): array
    {
        if (! self::configured()) {
            return ['status' => 503, 'body' => ['message' => hws_t('ralat_sambungan')]];
        }
        $token = self::token();
        if ($token === null) {
            return ['status' => 503, 'body' => ['message' => hws_t('ralat_sambungan')]];
        }
        $args = [
            'method' => $method,
            'timeout' => 20,
            'redirection' => 0,
            'headers' => $headers + [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
                'Accept-Language' => hws_lang() === 'en' ? 'en' : 'ms',
                'X-Pengguna-IP' => hws_client_ip(),
                'User-Agent' => 'PortalHartanahWakafSelangor/'.HWS_CONNECTOR_VERSION,
            ],
            'sslverify' => ! (defined('HWS_TLS_TANPA_SAHKAN') && HWS_TLS_TANPA_SAHKAN),
        ];
        if ($body !== null) {
            $args['body'] = $body;
        }
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            return ['status' => 503, 'body' => ['message' => hws_t('ralat_sambungan')]];
        }
        $status = (int) wp_remote_retrieve_response_code($res);
        if ($status === 401 && $retry) {
            delete_transient(self::TOKEN_KEY);

            return self::send($method, $url, $body, $headers, false);
        }
        $decoded = json_decode((string) wp_remote_retrieve_body($res), true);

        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : []];
    }

    private static function token(): ?string
    {
        $cached = get_transient(self::TOKEN_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $res = wp_remote_post(self::url('token'), [
            'timeout' => 15,
            'redirection' => 0,
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            'body' => wp_json_encode(['grant_type' => 'client_credentials', 'client_id' => HWS_CLIENT_ID, 'client_secret' => HWS_CLIENT_SECRET]),
            'sslverify' => ! (defined('HWS_TLS_TANPA_SAHKAN') && HWS_TLS_TANPA_SAHKAN),
        ]);
        if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) {
            return null;
        }
        $data = json_decode((string) wp_remote_retrieve_body($res), true);
        if (! is_array($data) || empty($data['access_token'])) {
            return null;
        }
        set_transient(self::TOKEN_KEY, (string) $data['access_token'], max(60, (int) ($data['expires_in'] ?? 900) - 60));

        return (string) $data['access_token'];
    }

    private static function url(string $path): string
    {
        return rtrim((string) HWS_SISDATA_URL, '/').'/api/v1/pws/awam/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<array{path: string, name: string, type: string}>>  $files
     */
    private static function multipart(array $data, array $files, string $boundary): string
    {
        $out = '';
        $add = function (string $name, string $value) use (&$out, $boundary): void {
            $out .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        };
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $sub => $vv) {
                    $add($k.'['.$sub.']', (string) $vv);
                }
            } elseif ($v !== null) {
                $add((string) $k, is_bool($v) ? ($v ? '1' : '0') : (string) $v);
            }
        }
        foreach ($files as $field => $list) {
            foreach ($list as $i => $f) {
                $name = str_replace(['"', "\r", "\n"], '', $f['name']);
                $out .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$field}[{$i}]\"; filename=\"{$name}\"\r\nContent-Type: {$f['type']}\r\n\r\n";
                $out .= (string) file_get_contents($f['path'])."\r\n";
            }
        }

        return $out."--{$boundary}--\r\n";
    }
}
