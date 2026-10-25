<?php

/**
 * Plugin Name:       SISDATA Connector — Portal Hartanah Wakaf Selangor
 * Description:       Sambungan pelayan-ke-pelayan ke API awam SISDATA (/api/v1/pws/awam/*): katalog hartanah, agihan manfaat, kandungan, permohonan hasrat wakaf dan sewaan, bayaran dalam talian, aduan, sokongan, soalan lazim dan statistik. Kunci API tidak pernah sampai ke pelayar.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            SISDATA / Majlis Agama Islam Selangor
 * License:           Proprietary
 * Text Domain:       sisdata-connector
 *
 * Configuration lives in wp-config.php constants, never in the database:
 *   HWS_SISDATA_URL      e.g. https://api.sisdata.mais.gov.my  (local: http://127.0.0.1:8011)
 *   HWS_CLIENT_ID        client id issued in SISDATA (/admin/pws/klien)
 *   HWS_CLIENT_SECRET    client secret (shown once in SISDATA)
 *   HWS_SMTP_HOST/PORT   optional SMTP relay for confirmation e-mails (local: Mailpit 127.0.0.1:1025)
 *   HWS_GATEWAY_UJIAN    true only on non-production portals (test payment gateway page)
 *
 * SRS-PWS-20: the portal stores no master record — applications, payments
 * and statuses live in SISDATA; this plugin only caches public read-only
 * feeds briefly and keeps per-day page counters (no visitor identifiers).
 */
if (! defined('ABSPATH')) {
    exit;
}

define('HWS_CONNECTOR_VERSION', '1.0.0');
define('HWS_CONNECTOR_DIR', __DIR__);

require_once __DIR__.'/includes/class-hws-api.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/forms.php';
require_once __DIR__.'/includes/shortcodes.php';
require_once __DIR__.'/includes/analytics.php';
require_once __DIR__.'/includes/hardening.php';
require_once __DIR__.'/includes/admin.php';

register_activation_hook(__FILE__, 'hws_activate');
register_deactivation_hook(__FILE__, 'hws_deactivate');

function hws_activate(): void
{
    if (! wp_next_scheduled('hws_hantar_analitik')) {
        wp_schedule_event(time() + 300, 'daily', 'hws_hantar_analitik');
    }
}

function hws_deactivate(): void
{
    wp_clear_scheduled_hook('hws_hantar_analitik');
}
