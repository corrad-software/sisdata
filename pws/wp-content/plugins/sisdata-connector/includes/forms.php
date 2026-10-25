<?php

/**
 * Form handlers (admin-post.php, logged-in or not). Post/Redirect/Get: the
 * outcome is kept in a short-lived transient read once by the page, so a
 * reload never resubmits; the Idempotency-Key rendered into each form makes
 * a double submit harmless on the SISDATA side too (SRS-PWS-22).
 *
 * Every handler: nonce, honeypot + CAPTCHA (SRS-PWS-24), server-side field
 * checks, file type/size checks before forwarding (SISDATA re-checks and
 * virus-scans). Nothing submitted is stored by the portal (SRS-PWS-20).
 */
if (! defined('ABSPATH')) {
    exit;
}

const HWS_BORANG = ['wakaf', 'sewaan', 'status', 'bayaran', 'gateway', 'aduan', 'semak_aduan', 'sokongan', 'tanya'];

foreach (HWS_BORANG as $hws_form) {
    add_action('admin_post_hws_'.$hws_form, 'hws_handle_'.$hws_form);
    add_action('admin_post_nopriv_hws_'.$hws_form, 'hws_handle_'.$hws_form);
}

/** Opening tag + hidden fields of a portal form. */
function hws_form_open(string $form, string $slug, bool $files = false, bool $captcha = true): string
{
    $html = '<form class="hws-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"'.($files ? ' enctype="multipart/form-data"' : '').' novalidate data-hws-draf="'.esc_attr($form).'">';
    $html .= '<input type="hidden" name="action" value="hws_'.esc_attr($form).'">';
    $html .= '<input type="hidden" name="hws_kembali" value="'.esc_attr($slug).'">';
    $html .= '<input type="hidden" name="hws_kunci" value="'.esc_attr(wp_generate_uuid4()).'">';
    $html .= '<input type="hidden" name="hws_lang" value="'.esc_attr(hws_lang()).'">';
    $html .= wp_nonce_field('hws_'.$form, '_hws_nonce', true, false);

    return $html;
}

/** @param array<string, mixed> $state */
function hws_redirect(string $slug, array $state, array $query = []): void
{
    $token = wp_generate_password(20, false);
    set_transient('hws_r_'.$token, $state, 10 * MINUTE_IN_SECONDS);
    $lang = isset($_POST['hws_lang']) && $_POST['hws_lang'] === 'en' ? ['lang' => 'en'] : [];
    wp_safe_redirect(add_query_arg($query + ['hws_r' => $token] + $lang, home_url('/'.trailingslashit($slug))).'#hws-hasil', 303);
    exit;
}

/** Read (once) the outcome of the last submission for this page. */
function hws_outcome(): ?array
{
    $token = isset($_GET['hws_r']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['hws_r'])) : '';
    if ($token === '') {
        return null;
    }
    $state = get_transient('hws_r_'.$token);
    delete_transient('hws_r_'.$token);

    return is_array($state) ? $state : null;
}

function hws_guard(string $form): string
{
    $slug = isset($_POST['hws_kembali']) ? sanitize_title(wp_unslash($_POST['hws_kembali'])) : '';
    if (! isset($_POST['_hws_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_hws_nonce'])), 'hws_'.$form)) {
        hws_redirect($slug, ['ok' => false, 'mesej' => hws_t('ralat_sesi')]);
    }

    return $slug;
}

function hws_require_captcha(string $slug, array $old): void
{
    if (! hws_captcha_ok()) {
        hws_redirect($slug, ['ok' => false, 'mesej' => hws_t('ralat_captcha'), 'ralat' => ['hws_captcha' => [hws_t('ralat_captcha')]], 'lama' => $old]);
    }
}

/** @param list<string> $fields */
function hws_input(array $fields): array
{
    $out = [];
    foreach ($fields as $f) {
        if (! isset($_POST[$f])) {
            continue;
        }
        $v = wp_unslash($_POST[$f]);
        $out[$f] = is_string($v) ? ($f === 'keterangan' || $f === 'tujuan_wakaf' || $f === 'alamat' || $f === 'tujuan' ? sanitize_textarea_field($v) : sanitize_text_field($v)) : '';
    }

    return array_filter($out, fn ($v) => $v !== '');
}

/**
 * Validated uploads ready to forward (<= 5 MB, PDF/JPG/PNG by content).
 *
 * @return list<array{path: string, name: string, type: string}>|string list, or an error message
 */
function hws_files(string $field, int $max = 5)
{
    if (empty($_FILES[$field]) || ! is_array($_FILES[$field]['name'] ?? null)) {
        return [];
    }
    $out = [];
    $allowed = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    foreach ($_FILES[$field]['name'] as $i => $name) {
        if (($_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $tmp = (string) $_FILES[$field]['tmp_name'][$i];
        $size = (int) $_FILES[$field]['size'][$i];
        $check = wp_check_filetype_and_ext($tmp, (string) $name, $allowed);
        if (($_FILES[$field]['error'][$i] ?? 1) !== UPLOAD_ERR_OK || ! is_uploaded_file($tmp) || $size > 5 * MB_IN_BYTES || empty($check['type'])) {
            return hws_t('ralat_fail');
        }
        $out[] = ['path' => $tmp, 'name' => sanitize_file_name((string) $name), 'type' => (string) $check['type']];
    }

    return count($out) > $max ? hws_t('ralat_fail') : $out;
}

/** @param array{status: int, body: array<string, mixed>} $res */
function hws_api_error(array $res, string $slug, array $old): void
{
    $errors = isset($res['body']['errors']) && is_array($res['body']['errors']) ? $res['body']['errors'] : [];
    $msg = match (true) {
        $res['status'] === 422 => hws_t('ralat_semak_borang'),
        $res['status'] === 429 => hws_t('ralat_had'),
        $res['status'] === 404 => (string) ($res['body']['message'] ?? hws_t('ralat_tiada')),
        default => hws_t('ralat_sambungan'),
    };
    hws_redirect($slug, ['ok' => false, 'mesej' => $msg, 'ralat' => $errors, 'lama' => $old]);
}

function hws_kunci(): string
{
    $k = isset($_POST['hws_kunci']) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string) wp_unslash($_POST['hws_kunci'])) : '';

    return 'phws-'.($k !== '' ? $k : wp_generate_uuid4());
}

/** SRS-PWS-26 (portal side): confirmation e-mail with the SISDATA reference. */
function hws_mail(string $to, string $subjectKey, string $bodyKey, array $vars): void
{
    if (! is_email($to)) {
        return;
    }
    wp_mail($to, hws_t('nama_rasmi').' — '.hws_t($subjectKey, $vars), hws_t($bodyKey, $vars)."\n\n".hws_t('emel_kaki'));
}

function hws_handle_wakaf(): void
{
    $slug = hws_guard('wakaf');
    $old = hws_input(['nama_pewakaf', 'jenis_pemohon', 'no_pengenalan', 'emel', 'jenis_wakaf', 'tujuan_wakaf', 'no_lot', 'kod_mukim', 'keluasan', 'nilai_anggaran']);
    hws_require_captcha($slug, $old);
    $files = hws_files('lampiran');
    if (is_string($files)) {
        hws_redirect($slug, ['ok' => false, 'mesej' => $files, 'ralat' => ['lampiran' => [$files]], 'lama' => $old]);
    }
    $data = $old + ['persetujuan_pdpa' => empty($_POST['persetujuan_pdpa']) ? '0' : '1'];
    $res = HWS_Api::post('permohonan/wakaf', $data, hws_kunci(), $files ? ['lampiran' => $files] : []);
    if ($res['status'] !== 201) {
        hws_api_error($res, $slug, $old);
    }
    hws_analytics_form('/permohonan-wakaf', 'hantar');
    $ref = (string) ($res['body']['data']['no_rujukan'] ?? '');
    hws_mail($old['emel'] ?? '', 'emel_subjek_permohonan', 'emel_isi_permohonan', ['no' => $ref]);
    hws_redirect($slug, ['ok' => true, 'jenis' => 'permohonan', 'no_rujukan' => $ref, 'status' => $res['body']['data']['status_nama'] ?? '']);
}

function hws_handle_sewaan(): void
{
    $slug = hws_guard('sewaan');
    $old = hws_input(['kod_premis', 'jenis_entiti', 'nama', 'no_pengenalan', 'no_pendaftaran_syarikat', 'alamat', 'poskod', 'telefon', 'emel', 'tarikh_mula_dicadang', 'tempoh_bulan', 'tujuan']);
    hws_require_captcha($slug, $old);
    $files = hws_files('lampiran');
    if (is_string($files)) {
        hws_redirect($slug, ['ok' => false, 'mesej' => $files, 'ralat' => ['lampiran' => [$files]], 'lama' => $old]);
    }
    $res = HWS_Api::post('permohonan/sewaan', $old + ['persetujuan_pdpa' => empty($_POST['persetujuan_pdpa']) ? '0' : '1'], hws_kunci(), $files ? ['lampiran' => $files] : []);
    if ($res['status'] !== 201) {
        hws_api_error($res, $slug, $old);
    }
    hws_analytics_form('/permohonan-sewaan', 'hantar');
    $ref = (string) ($res['body']['data']['no_rujukan'] ?? '');
    hws_mail($old['emel'] ?? '', 'emel_subjek_permohonan', 'emel_isi_permohonan', ['no' => $ref]);
    hws_redirect($slug, ['ok' => true, 'jenis' => 'permohonan', 'no_rujukan' => $ref, 'status' => $res['body']['data']['status_nama'] ?? '']);
}

function hws_handle_status(): void
{
    $slug = hws_guard('status');
    $old = hws_input(['no_rujukan', 'emel']);
    $res = HWS_Api::post('permohonan/status', $old);
    if ($res['status'] !== 200) {
        hws_api_error($res, $slug, $old);
    }
    hws_redirect($slug, ['ok' => true, 'jenis' => 'status', 'data' => $res['body']['data'] ?? []]);
}

function hws_handle_bayaran(): void
{
    $slug = hws_guard('bayaran');
    $old = hws_input(['no_kontrak', 'saluran', 'nama_pembayar', 'emel_pembayar']);
    hws_require_captcha($slug, $old);
    $res = HWS_Api::post('bayaran', $old, hws_kunci());
    if ($res['status'] !== 201) {
        hws_api_error($res, $slug, $old);
    }
    $d = (array) ($res['body']['data'] ?? []);
    $url = (string) ($d['url_bayaran'] ?? '');
    if (str_starts_with($url, 'ujian:')) {
        wp_safe_redirect(hws_url('gateway-ujian', ['ruj' => substr($url, 6)]), 303);
        exit;
    }
    if ($url !== '' && wp_http_validate_url($url)) {
        // Leave for the gateway's own page — card / bank details are entered there only (SRS-PWS-16).
        wp_redirect($url, 303); // phpcs:ignore WordPress.Security.SafeRedirect -- gateway host
        exit;
    }
    hws_redirect($slug, ['ok' => false, 'mesej' => hws_t('ralat_sambungan')]);
}

function hws_handle_gateway(): void
{
    $slug = hws_guard('gateway');
    $ref = isset($_POST['ruj']) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string) wp_unslash($_POST['ruj'])) : '';
    $outcome = isset($_POST['keputusan']) ? sanitize_key(wp_unslash($_POST['keputusan'])) : '';
    if (! (defined('HWS_GATEWAY_UJIAN') && HWS_GATEWAY_UJIAN) || $ref === '') {
        hws_redirect($slug, ['ok' => false, 'mesej' => hws_t('ralat_tiada')]);
    }
    HWS_Api::post('bayaran/'.rawurlencode($ref).'/simulasi', ['keputusan' => $outcome]);
    wp_safe_redirect(hws_url('bayaran', ['ruj' => $ref]), 303);
    exit;
}

function hws_handle_aduan(): void
{
    $slug = hws_guard('aduan');
    $old = hws_input(['kategori', 'keterangan', 'lokasi_teks', 'nama', 'emel', 'no_telefon']);
    $old['tanpa_nama'] = empty($_POST['tanpa_nama']) ? '0' : '1';
    hws_require_captcha($slug, $old);
    $files = hws_files('fail');
    if (is_string($files)) {
        hws_redirect($slug, ['ok' => false, 'mesej' => $files, 'ralat' => ['fail' => [$files]], 'lama' => $old]);
    }
    $res = HWS_Api::post('aduan', $old + ['captcha' => hws_captcha_token()], hws_kunci(), $files ? ['fail' => $files] : []);
    if (! in_array($res['status'], [200, 201], true)) {
        hws_api_error($res, $slug, $old);
    }
    hws_analytics_form('/aduan', 'hantar');
    hws_redirect($slug, ['ok' => true, 'jenis' => 'aduan', 'no_rujukan' => $res['body']['data']['no_rujukan_aduan'] ?? '', 'status' => $res['body']['data']['status'] ?? '']);
}

function hws_handle_semak_aduan(): void
{
    $slug = hws_guard('semak_aduan');
    $old = hws_input(['no_rujukan_aduan', 'pengesahan']);
    $res = HWS_Api::post('aduan/semak', $old);
    if ($res['status'] !== 200) {
        hws_api_error($res, $slug, $old);
    }
    hws_redirect($slug, ['ok' => true, 'jenis' => 'semak_aduan', 'data' => $res['body']['data'] ?? []]);
}

function hws_handle_sokongan(): void
{
    $slug = hws_guard('sokongan');
    $old = hws_input(['tajuk', 'keterangan', 'kategori', 'emel']);
    hws_require_captcha($slug, $old);
    $res = HWS_Api::post('sokongan', $old + ['captcha' => hws_captcha_token(), 'halaman' => '/'.$slug.'/'], hws_kunci());
    if ($res['status'] !== 201) {
        if ($res['status'] === 503) {
            hws_redirect($slug, ['ok' => false, 'mesej' => (string) ($res['body']['message'] ?? hws_t('ralat_sambungan')), 'lama' => $old]);
        }
        hws_api_error($res, $slug, $old);
    }
    hws_redirect($slug, ['ok' => true, 'jenis' => 'sokongan', 'no_rujukan' => $res['body']['data']['no_tiket'] ?? '']);
}

function hws_handle_tanya(): void
{
    $slug = hws_guard('tanya');
    $old = hws_input(['soalan']);
    $res = HWS_Api::post('soalan-lazim/tanya', $old);
    if ($res['status'] !== 200) {
        hws_api_error($res, $slug, $old);
    }
    hws_redirect($slug, ['ok' => true, 'jenis' => 'tanya', 'soalan' => $old['soalan'] ?? '', 'data' => $res['body']['data'] ?? []]);
}
