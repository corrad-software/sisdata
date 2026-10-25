<?php

/**
 * wp-admin status screen: connection check only. Credentials are wp-config
 * constants and are never shown or editable here.
 */
if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function (): void {
    add_options_page('SISDATA Connector', 'SISDATA Connector', 'manage_options', 'hws-sisdata', 'hws_admin_page');
});

function hws_admin_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }
    $ping = null;
    if (isset($_POST['hws_ping']) && check_admin_referer('hws_ping')) {
        $ping = HWS_Api::ping();
    }
    echo '<div class="wrap"><h1>SISDATA Connector — Portal Hartanah Wakaf Selangor</h1>';
    echo '<table class="form-table"><tr><th>URL SISDATA</th><td><code>'.esc_html(defined('HWS_SISDATA_URL') ? HWS_SISDATA_URL : '—').'</code></td></tr>';
    echo '<tr><th>ID klien</th><td><code>'.esc_html(defined('HWS_CLIENT_ID') ? HWS_CLIENT_ID : '—').'</code></td></tr>';
    echo '<tr><th>Rahsia klien</th><td>'.(defined('HWS_CLIENT_SECRET') && HWS_CLIENT_SECRET !== '' ? 'ditetapkan dalam wp-config.php (tidak dipaparkan)' : '<strong>tiada</strong>').'</td></tr>';
    echo '<tr><th>Gateway ujian</th><td>'.(defined('HWS_GATEWAY_UJIAN') && HWS_GATEWAY_UJIAN ? 'AKTIF (bukan produksi)' : 'tidak aktif').'</td></tr></table>';
    if ($ping !== null) {
        echo $ping['ok'] ? '<div class="notice notice-success"><p>Sambungan berjaya — token akses diperoleh.</p></div>' : '<div class="notice notice-error"><p>Sambungan gagal. Semak URL, kelayakan dan senarai IP dibenarkan dalam SISDATA.</p></div>';
    }
    echo '<form method="post">';
    wp_nonce_field('hws_ping');
    echo '<p><button class="button button-primary" name="hws_ping" value="1">Uji sambungan</button></p></form></div>';
}
