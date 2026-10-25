<?php

/**
 * Language (BM default, EN), visitor IP, CAPTCHA and small helpers.
 *
 * Bilingual without an extra plugin (SRS-PWS-03): the language comes from
 * ?lang=ms|en (remembered in a cookie); UI strings come from hws_t(); content
 * from SISDATA carries tajuk_bm/tajuk_bi. Polylang is deliberately not used
 * (fewer moving parts to harden and patch — SRS-PWS-25).
 */
if (! defined('ABSPATH')) {
    exit;
}

function hws_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $q = isset($_GET['lang']) ? sanitize_key((string) wp_unslash($_GET['lang'])) : '';
    if (in_array($q, ['ms', 'en'], true)) {
        if (! headers_sent()) {
            setcookie('hws_lang', $q, ['expires' => time() + YEAR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
        }

        return $lang = $q;
    }
    $p = isset($_POST['hws_lang']) ? sanitize_key((string) wp_unslash($_POST['hws_lang'])) : ''; // form handlers
    if (in_array($p, ['ms', 'en'], true)) {
        return $lang = $p;
    }
    $c = isset($_COOKIE['hws_lang']) ? sanitize_key((string) wp_unslash($_COOKIE['hws_lang'])) : '';

    return $lang = in_array($c, ['ms', 'en'], true) ? $c : 'ms';
}

/** Pick the BM or EN variant of a SISDATA field pair. */
function hws_pilih(array $row, string $field): string
{
    $en = $row[$field.'_bi'] ?? null;

    return (string) (hws_lang() === 'en' && is_string($en) && $en !== '' ? $en : ($row[$field.'_bm'] ?? ''));
}

function hws_t(string $key, array $vars = []): string
{
    static $dict = null;
    $dict ??= require __DIR__.'/i18n.php';
    $lang = hws_lang();
    $text = $dict[$lang][$key] ?? $dict['ms'][$key] ?? $key;
    foreach ($vars as $k => $v) {
        $text = str_replace(':'.$k, (string) $v, $text);
    }

    return $text;
}

/** Visitor IP as seen by this server (behind a trusted proxy/WAF set HWS_PROXY_DIPERCAYAI). */
function hws_client_ip(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if (defined('HWS_PROXY_DIPERCAYAI') && HWS_PROXY_DIPERCAYAI && ! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        $ip = $first;
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** Link to a portal page by slug, keeping the language. */
function hws_url(string $slug = '', array $query = []): string
{
    $url = home_url('/'.($slug !== '' ? trailingslashit($slug) : ''));
    if (hws_lang() === 'en') {
        $query['lang'] = 'en';
    }

    return $query ? add_query_arg($query, $url) : $url;
}

function hws_money(?string $amount): string
{
    return 'RM '.number_format((float) $amount, 2, '.', ',');
}

function hws_date(?string $iso): string
{
    if (! $iso) {
        return '—';
    }
    $ts = strtotime($iso);

    return $ts ? wp_date('j M Y', $ts) : '—';
}

/*
 * CAPTCHA (SRS-PWS-24). A server-checked arithmetic challenge signed with the
 * site salts and valid for 30 minutes; plus a honeypot field. In production
 * replace with the MAIS-approved CAPTCHA provider (same hook points). The
 * token SISDATA expects (ADU/TKT CaptchaVerifier) is HWS_CAPTCHA_TOKEN — the
 * real provider's token is forwarded instead once configured.
 */
function hws_captcha_field(): string
{
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $exp = time() + 1800;
    $sig = hash_hmac('sha256', ($a + $b).'|'.$exp, wp_salt('nonce'));
    $label = esc_html(hws_t('captcha_soalan', ['a' => $a, 'b' => $b]));

    return '<div class="hws-field hws-captcha"><label for="hws-captcha">'.$label.' <span class="hws-req" aria-hidden="true">*</span></label>'
        .'<input id="hws-captcha" name="hws_captcha" inputmode="numeric" pattern="[0-9]*" required autocomplete="off" maxlength="3">'
        .'<input type="hidden" name="hws_captcha_sig" value="'.esc_attr($sig.'.'.$exp).'">'
        .'<div class="hws-hp" aria-hidden="true"><label for="hws-laman">Laman web</label><input id="hws-laman" name="hws_laman" tabindex="-1" autocomplete="off"></div></div>';
}

function hws_captcha_ok(): bool
{
    if (! empty($_POST['hws_laman'])) {
        return false; // honeypot filled → bot
    }
    $answer = isset($_POST['hws_captcha']) ? (string) absint(wp_unslash($_POST['hws_captcha'])) : '';
    $parts = explode('.', isset($_POST['hws_captcha_sig']) ? sanitize_text_field(wp_unslash($_POST['hws_captcha_sig'])) : '', 2);
    if (count($parts) !== 2 || (int) $parts[1] < time()) {
        return false;
    }

    return hash_equals(hash_hmac('sha256', $answer.'|'.$parts[1], wp_salt('nonce')), $parts[0]);
}

function hws_captcha_token(): string
{
    return defined('HWS_CAPTCHA_TOKEN') ? (string) HWS_CAPTCHA_TOKEN : 'lulus-captcha';
}
