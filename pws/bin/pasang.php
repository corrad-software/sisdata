<?php

/**
 * Idempotent WordPress installer/configurator for Portal Hartanah Wakaf
 * Selangor (no wp-cli needed). Called by infra/bin/pws-setup.sh:
 *
 *   php pws/bin/pasang.php <wordpress-dir> <site-url> <admin-email>
 *
 * Installs WordPress if needed (prints the generated admin password once),
 * sets the official name, permalinks, timezone and safe defaults, activates
 * the theme + SISDATA Connector, removes demo content and creates/updates the
 * portal pages (BM title + EN title meta, shortcode bodies).
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
[$_, $wpDir, $siteUrl, $email] = $argv + [null, null, 'http://127.0.0.1:8090', 'admin@hartanahwakaf.test'];
if (! $wpDir || ! is_file($wpDir.'/wp-config.php')) {
    fwrite(STDERR, "wp-config.php tidak ditemui di {$wpDir}\n");
    exit(1);
}
$host = (string) parse_url($siteUrl, PHP_URL_HOST);
$port = parse_url($siteUrl, PHP_URL_PORT);
$_SERVER['HTTP_HOST'] = $host.($port ? ':'.$port : '');
$_SERVER['SERVER_NAME'] = $host;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('WP_INSTALLING', true);
require $wpDir.'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
require_once ABSPATH.'wp-admin/includes/theme.php';
require_once ABSPATH.'wp-admin/includes/file.php';

$name = 'Portal Hartanah Wakaf Selangor';
if (! is_blog_installed()) {
    $password = wp_generate_password(20, true, false);
    wp_install($name, 'pentadbir_hws', $email, false, '', $password);
    echo "WordPress dipasang. Pentadbir: pentadbir_hws / {$password}  (simpan sekarang — tidak dipaparkan lagi)\n";
} else {
    echo "WordPress sudah dipasang.\n";
}

update_option('siteurl', $siteUrl);
update_option('home', $siteUrl);
update_option('blogname', $name);
update_option('blogdescription', 'Majlis Agama Islam Selangor');
update_option('timezone_string', 'Asia/Kuala_Lumpur');
update_option('date_format', 'j F Y');
update_option('permalink_structure', '/%postname%/');
update_option('default_comment_status', 'closed');
update_option('default_ping_status', 'closed');
update_option('users_can_register', 0);
update_option('blog_public', 0); // local/staging: no indexing
update_option('WPLANG', '');

// Theme + plugin (symlinked from the repo by pws-setup.sh).
switch_theme('hartanah-wakaf-selangor');
$plugin = 'sisdata-connector/sisdata-connector.php';
if (! is_plugin_active($plugin)) {
    $res = activate_plugin($plugin);
    echo is_wp_error($res) ? 'Gagal aktifkan pemalam: '.$res->get_error_message()."\n" : "Pemalam SISDATA Connector diaktifkan.\n";
}
foreach (['hello.php', 'akismet/akismet.php'] as $p) {
    if (is_file(WP_PLUGIN_DIR.'/'.$p) || is_dir(WP_PLUGIN_DIR.'/'.dirname($p))) {
        deactivate_plugins($p, true);
        delete_plugins([$p]);
    }
}

// Demo content out.
foreach (['hello-world' => 'post', 'sample-page' => 'page', 'privacy-policy' => 'page'] as $slug => $type) {
    $p = get_page_by_path($slug, OBJECT, $type);
    if ($p && ! get_post_meta($p->ID, 'hws_urus', true)) {
        wp_delete_post($p->ID, true);
    }
}

/** slug => [BM title, EN title, BM content, EN content, BM intro, EN intro] */
$pages = [
    'utama' => ['Utama', 'Home', '', '', '', ''],
    'hartanah' => ['Hartanah Tersedia', 'Available Properties', '[hws_hartanah]', '', 'Premis dan tanah wakaf MAIS yang tersedia untuk disewa atau dipajak.', 'MAIS waqf premises and land available to rent or lease.'],
    'projek-wakaf' => ['Projek Wakaf', 'Waqf Projects', '[hws_projek]', '', 'Projek pembangunan wakaf yang diluluskan dan kemajuannya.', 'Approved waqf development projects and their progress.'],
    'permohonan-wakaf' => ['Permohonan Hasrat Wakaf', 'Waqf Pledge Application', '[hws_borang_wakaf]', '', '', ''],
    'permohonan-sewaan' => ['Permohonan Sewaan / Pajakan', 'Rental / Lease Application', '[hws_borang_sewaan]', '', '', ''],
    'semak-status' => ['Semak Status Permohonan', 'Check Application Status', '[hws_semak_status]', '', 'Masukkan nombor rujukan PHWS dan e-mel yang digunakan semasa permohonan.', 'Enter your PHWS reference number and the e-mail used in the application.'],
    'bayaran' => ['Bayaran Dalam Talian', 'Online Payment', '[hws_bayaran]', '', '', ''],
    'gateway-ujian' => ['Gateway Pembayaran Ujian', 'Test Payment Gateway', '[hws_gateway_ujian]', '', '', ''],
    'agihan-manfaat' => ['Dasar Agihan Manfaat', 'Benefit Distribution Policy', '[hws_agihan]', '', '', ''],
    'berita' => ['Berita dan Hebahan', 'News and Announcements', '[hws_berita]', '', '', ''],
    'soalan-lazim' => ['Soalan Lazim', 'Frequently Asked Questions', '[hws_soalan_lazim]', '', '', ''],
    'aduan' => ['Aduan Awam', 'Public Complaints', '[hws_aduan]', '', 'Laporkan pencerobohan, kerosakan atau isu lain berkaitan tanah dan hartanah wakaf.', 'Report encroachment, damage or other issues on waqf land and property.'],
    'statistik' => ['Statistik', 'Statistics', '[hws_statistik]', '', 'Statistik agregat yang diterbitkan oleh MAIS.', 'Aggregate statistics published by MAIS.'],
    'hubungi' => ['Hubungi Kami', 'Contact Us', '[hws_hubungi]', '', '', ''],
    'notis-privasi' => ['Notis Privasi', 'Privacy Notice',
        "Majlis Agama Islam Selangor (MAIS) memproses data peribadi yang anda berikan melalui Portal Hartanah Wakaf Selangor bagi tujuan memproses permohonan hasrat wakaf, permohonan sewaan/pajakan, bayaran, aduan dan pertanyaan anda, mengikut Akta Perlindungan Data Peribadi 2010.\n\nData permohonan dihantar terus ke sistem SISDATA MAIS dan tidak disimpan oleh portal ini. Portal tidak menggunakan kuki penjejakan; statistik lawatan dikumpul tanpa pengecam pelawat.\n\nAnda boleh memohon akses atau pembetulan data peribadi anda dengan menghubungi MAIS.",
        "Majlis Agama Islam Selangor (MAIS) processes the personal data you provide through Portal Hartanah Wakaf Selangor to handle your waqf pledges, rental/lease applications, payments, complaints and enquiries, in accordance with the Personal Data Protection Act 2010.\n\nApplication data is sent directly to the MAIS SISDATA system and is not kept by this portal. The portal uses no tracking cookies; visit statistics are collected without visitor identifiers.\n\nYou may request access to or correction of your personal data by contacting MAIS.", '', ''],
];
$ids = [];
foreach ($pages as $slug => [$bm, $en, $body, $bodyEn, $introMs, $introEn]) {
    $existing = get_page_by_path($slug, OBJECT, 'page');
    $data = ['post_title' => $bm, 'post_name' => $slug, 'post_content' => $body, 'post_status' => 'publish', 'post_type' => 'page', 'comment_status' => 'closed'];
    if ($existing) {
        $data['ID'] = $existing->ID;
        $id = wp_update_post($data);
    } else {
        $id = wp_insert_post($data);
    }
    if (is_wp_error($id) || ! $id) {
        echo "Gagal mencipta halaman {$slug}\n";

        continue;
    }
    update_post_meta($id, 'hws_urus', 1);
    update_post_meta($id, 'hws_tajuk_en', $en);
    update_post_meta($id, 'hws_kandungan_en', $bodyEn);
    update_post_meta($id, 'hws_intro_ms', $introMs);
    update_post_meta($id, 'hws_intro_en', $introEn);
    $ids[$slug] = (int) $id;
}
update_option('show_on_front', 'page');
update_option('page_on_front', $ids['utama'] ?? 0);
update_option('wp_page_for_privacy_policy', $ids['notis-privasi'] ?? 0);
flush_rewrite_rules(false);

if (function_exists('hws_activate')) {
    hws_activate();
}
echo count($ids)." halaman portal disediakan.\n";
