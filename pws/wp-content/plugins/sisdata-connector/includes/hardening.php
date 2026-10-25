<?php

/**
 * CMS-level hardening (SRS-PWS-25, application part; server/OS hardening,
 * WAF, patching schedule and VAPT are operational — see pws/README.md):
 * security headers, no XML-RPC, no user enumeration through REST/author
 * archives, no version disclosure, no file editing from wp-admin (also
 * DISALLOW_FILE_EDIT in wp-config), outgoing mail via the configured relay.
 */
if (! defined('ABSPATH')) {
    exit;
}

add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', function (array $headers): array {
    unset($headers['X-Pingback']);

    return $headers;
});
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');

add_action('send_headers', function (): void {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self' ".(defined('HWS_GATEWAY_ASAL') ? HWS_GATEWAY_ASAL : '')."; frame-ancestors 'self'; base-uri 'self'");
    if (is_ssl()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
});

// No user enumeration.
add_filter('rest_endpoints', function (array $endpoints): array {
    if (! is_user_logged_in()) {
        foreach (array_keys($endpoints) as $route) {
            if (str_starts_with($route, '/wp/v2/users')) {
                unset($endpoints[$route]);
            }
        }
    }

    return $endpoints;
});
add_action('template_redirect', function (): void {
    if (is_author() || (isset($_GET['author']) && ! is_admin())) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
});

// Login errors never say whether the user exists.
add_filter('login_errors', fn () => esc_html__('Log masuk gagal.', 'sisdata-connector'));

// SMTP relay for outgoing mail (local: Mailpit).
add_action('phpmailer_init', function ($mailer): void {
    if (! defined('HWS_SMTP_HOST') || HWS_SMTP_HOST === '') {
        return;
    }
    $mailer->isSMTP();
    $mailer->Host = HWS_SMTP_HOST;
    $mailer->Port = defined('HWS_SMTP_PORT') ? (int) HWS_SMTP_PORT : 25;
    $mailer->SMTPAuth = defined('HWS_SMTP_USER');
    if ($mailer->SMTPAuth) {
        $mailer->Username = HWS_SMTP_USER;
        $mailer->Password = defined('HWS_SMTP_PASS') ? HWS_SMTP_PASS : '';
    }
    $mailer->SMTPAutoTLS = defined('HWS_SMTP_TLS') && HWS_SMTP_TLS;
});
add_filter('wp_mail_from_name', fn () => 'Portal Hartanah Wakaf Selangor');
