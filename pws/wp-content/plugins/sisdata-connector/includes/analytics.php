<?php

/**
 * SRS-PWS-27 portal analytics without tracking: per-day counters per page
 * (views, form starts/submissions) and a referrer CATEGORY (search, social,
 * direct, other) — no cookies, IPs or visitor identifiers are stored. Pushed
 * once a day to SISDATA (/awam/analitik); re-sending a day replaces it.
 */
if (! defined('ABSPATH')) {
    exit;
}

const HWS_ANALITIK_OPT = 'hws_analitik';

function hws_referrer_category(): string
{
    $ref = isset($_SERVER['HTTP_REFERER']) ? (string) wp_parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_HOST) : '';
    if ($ref === '' || $ref === wp_parse_url(home_url(), PHP_URL_HOST)) {
        return 'terus';
    }
    foreach (['google' => 'carian', 'bing' => 'carian', 'yahoo' => 'carian', 'duckduckgo' => 'carian', 'facebook' => 'sosial', 'instagram' => 'sosial', 't.co' => 'sosial', 'x.com' => 'sosial', 'tiktok' => 'sosial', 'whatsapp' => 'sosial', 'gov.my' => 'kerajaan'] as $needle => $cat) {
        if (str_contains($ref, $needle)) {
            return $cat;
        }
    }

    return 'lain';
}

function hws_analytics_bump(string $page, string $field): void
{
    if (is_admin() || (defined('DOING_CRON') && DOING_CRON)) {
        return;
    }
    $data = get_option(HWS_ANALITIK_OPT, []);
    $data = is_array($data) ? $data : [];
    $day = wp_date('Y-m-d');
    $src = $field === 'bilangan_lawatan' ? hws_referrer_category() : 'terus';
    $key = $day.'|'.$page.'|'.$src;
    $data[$key] ??= ['tarikh' => $day, 'halaman' => $page, 'sumber_rujukan' => $src, 'bilangan_lawatan' => 0, 'borang_dimulakan' => 0, 'borang_dihantar' => 0];
    $data[$key][$field]++;
    update_option(HWS_ANALITIK_OPT, $data, false);
}

/** Page view (and a form "start" when the page carries a form). */
function hws_analytics_hit(string $page, bool $form = false): void
{
    static $done = [];
    if (isset($done[$page]) || ! empty($_GET['hws_r'])) {
        return;
    }
    $done[$page] = true;
    hws_analytics_bump($page, 'bilangan_lawatan');
    if ($form) {
        hws_analytics_bump($page, 'borang_dimulakan');
    }
}

function hws_analytics_form(string $page, string $what): void
{
    if ($what === 'hantar') {
        hws_analytics_bump($page, 'borang_dihantar');
    }
}

add_action('hws_hantar_analitik', 'hws_push_analytics');

/** Send all complete days (and today so far) to SISDATA; keep 7 days locally. */
function hws_push_analytics(): void
{
    $data = get_option(HWS_ANALITIK_OPT, []);
    if (! is_array($data) || $data === []) {
        return;
    }
    $rows = array_values($data);
    foreach (array_chunk($rows, 400) as $chunk) {
        $res = HWS_Api::post('analitik', ['rekod' => $chunk]);
        if ($res['status'] !== 200) {
            return; // retried on the next run
        }
    }
    $keep = wp_date('Y-m-d', time() - 7 * DAY_IN_SECONDS);
    update_option(HWS_ANALITIK_OPT, array_filter($data, fn ($r) => $r['tarikh'] >= $keep), false);
}
