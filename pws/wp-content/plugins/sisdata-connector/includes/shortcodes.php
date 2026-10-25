<?php

/**
 * Page building blocks as shortcodes, so editors place them on ordinary WP
 * pages. All data is read server-side from SISDATA; everything printed is
 * escaped. Accessible patterns: labelled fields, error summary with links,
 * live region for results, tables with captions.
 */
if (! defined('ABSPATH')) {
    exit;
}

$hws_shortcodes = [
    'hws_hartanah' => 'hws_sc_hartanah', 'hws_projek' => 'hws_sc_projek', 'hws_berita' => 'hws_sc_berita',
    'hws_borang_wakaf' => 'hws_sc_wakaf', 'hws_borang_sewaan' => 'hws_sc_sewaan', 'hws_semak_status' => 'hws_sc_status',
    'hws_bayaran' => 'hws_sc_bayaran', 'hws_gateway_ujian' => 'hws_sc_gateway', 'hws_aduan' => 'hws_sc_aduan',
    'hws_soalan_lazim' => 'hws_sc_soalan_lazim', 'hws_agihan' => 'hws_sc_agihan', 'hws_statistik' => 'hws_sc_statistik',
    'hws_hubungi' => 'hws_sc_hubungi', 'hws_ringkasan_utama' => 'hws_sc_utama',
];
foreach ($hws_shortcodes as $tag => $fn) {
    add_shortcode($tag, $fn);
}

// ------------------------------------------------------------------ pieces

function hws_e(string $s): string
{
    return esc_html($s);
}

function hws_notice(string $type, string $html): string
{
    $role = $type === 'error' ? 'alert' : 'status';

    return '<div class="hws-notice hws-notice--'.esc_attr($type).'" role="'.$role.'">'.$html.'</div>';
}

/** Error summary + success box for a form page, from the PRG outcome. */
function hws_result_box(?array $state): string
{
    if ($state === null) {
        return '';
    }
    if (! empty($state['ok']) && ! empty($state['no_rujukan'])) {
        $label = $state['jenis'] === 'aduan' ? hws_t('no_rujukan_aduan') : ($state['jenis'] === 'sokongan' ? hws_t('no_tiket') : hws_t('no_rujukan'));

        return '<div id="hws-hasil" tabindex="-1">'.hws_notice('success', '<h2>'.hws_e(hws_t('berjaya_dihantar')).'</h2><p>'.hws_e($label).': <strong class="hws-ref">'.hws_e((string) $state['no_rujukan']).'</strong></p>'
            .(! empty($state['status']) ? '<p>'.hws_e(hws_t('status')).': '.hws_e((string) $state['status']).'</p>' : '')
            .'<p>'.hws_e(hws_t('simpan_rujukan')).'</p>').'</div>';
    }
    if (empty($state['ok'])) {
        $items = '';
        foreach ((array) ($state['ralat'] ?? []) as $field => $messages) {
            foreach ((array) $messages as $m) {
                $items .= '<li><a href="#hws-'.esc_attr(str_replace(['.', '_'], '-', (string) $field)).'">'.hws_e((string) $m).'</a></li>';
            }
        }

        return '<div id="hws-hasil" tabindex="-1">'.hws_notice('error', '<h2>'.hws_e((string) ($state['mesej'] ?? hws_t('ralat_semak_borang'))).'</h2>'.($items ? '<ul>'.$items.'</ul>' : '')).'</div>';
    }

    return '';
}

/**
 * One labelled field.
 *
 * @param  array<string, mixed>  $o  type, required, options, help, value, attrs
 */
function hws_field(string $name, string $label, array $o = [], ?array $state = null): string
{
    $id = 'hws-'.str_replace('_', '-', $name);
    $type = $o['type'] ?? 'text';
    $req = ! empty($o['required']);
    $value = (string) ($state['lama'][$name] ?? $o['value'] ?? '');
    $errors = (array) ($state['ralat'][$name] ?? []);
    $describedby = [];
    $html = '<div class="hws-field'.($errors ? ' hws-field--error' : '').'">';
    $html .= '<label for="'.esc_attr($id).'">'.hws_e($label).($req ? ' <span class="hws-req" aria-hidden="true">*</span>' : ' <span class="hws-opt">('.hws_e(hws_t('pilihan')).')</span>').'</label>';
    if (! empty($o['help'])) {
        $html .= '<p class="hws-help" id="'.esc_attr($id).'-help">'.hws_e((string) $o['help']).'</p>';
        $describedby[] = $id.'-help';
    }
    foreach ($errors as $i => $e) {
        $html .= '<p class="hws-error" id="'.esc_attr($id).'-err'.$i.'">'.hws_e((string) $e).'</p>';
        $describedby[] = $id.'-err'.$i;
    }
    $common = ' id="'.esc_attr($id).'" name="'.esc_attr($name).'"'.($req ? ' required aria-required="true"' : '').($errors ? ' aria-invalid="true"' : '')
        .($describedby ? ' aria-describedby="'.esc_attr(implode(' ', $describedby)).'"' : '').(string) ($o['attrs'] ?? '');
    if ($type === 'textarea') {
        $html .= '<textarea'.$common.' rows="5">'.esc_textarea($value).'</textarea>';
    } elseif ($type === 'select') {
        $html .= '<select'.$common.'><option value="">'.hws_e(hws_t('sila_pilih')).'</option>';
        foreach ((array) $o['options'] as $k => $v) {
            $html .= '<option value="'.esc_attr((string) $k).'"'.selected($value, (string) $k, false).'>'.hws_e((string) $v).'</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'file') {
        $html .= '<input type="file"'.str_replace('name="'.esc_attr($name).'"', 'name="'.esc_attr($name).'[]"', $common).' multiple accept=".pdf,.jpg,.jpeg,.png">';
    } elseif ($type === 'checkbox') {
        $html = '<div class="hws-field hws-field--check'.($errors ? ' hws-field--error' : '').'">';
        foreach ($errors as $i => $e) {
            $html .= '<p class="hws-error" id="'.esc_attr($id).'-err'.$i.'">'.hws_e((string) $e).'</p>';
        }
        $html .= '<input type="checkbox" value="1"'.$common.'> <label for="'.esc_attr($id).'">'.wp_kses($label, ['a' => ['href' => []]]).'</label>';
    } else {
        $html .= '<input type="'.esc_attr($type).'" value="'.esc_attr($value).'"'.$common.'>';
    }

    return $html.'</div>';
}

function hws_submit(string $label): string
{
    return '<div class="hws-actions"><button type="submit" class="hws-btn hws-btn--primary">'.hws_e($label).'</button>'
        .'<button type="button" class="hws-btn hws-btn--secondary" data-hws-simpan-draf hidden>'.hws_e(hws_t('simpan_draf')).'</button>'
        .'<span class="hws-draf-status" role="status" aria-live="polite"></span></div>';
}

function hws_unavailable(): string
{
    return hws_notice('warning', '<p>'.hws_e(hws_t('ralat_sambungan')).'</p>');
}

function hws_pdpa_field(?array $state): string
{
    return hws_field('persetujuan_pdpa', hws_t('pdpa_setuju', ['url' => esc_url(hws_url('notis-privasi'))]), ['type' => 'checkbox', 'required' => true], $state);
}

// ------------------------------------------------------------------ feeds

function hws_sc_utama(): string
{
    $html = '<section class="hws-grid hws-grid--3" aria-label="'.esc_attr(hws_t('perkhidmatan')).'">';
    foreach ([['hartanah', 'kad_hartanah'], ['permohonan-wakaf', 'kad_wakaf'], ['permohonan-sewaan', 'kad_sewaan'], ['bayaran', 'kad_bayaran'], ['semak-status', 'kad_status'], ['aduan', 'kad_aduan']] as [$slug, $key]) {
        $html .= '<a class="hws-card hws-card--link" href="'.esc_url(hws_url($slug)).'"><h3>'.hws_e(hws_t($key)).'</h3><p>'.hws_e(hws_t($key.'_d')).'</p></a>';
    }
    $html .= '</section>';

    return $html.'<h2>'.hws_e(hws_t('berita_terkini')).'</h2>'.hws_sc_berita(['bil' => 3]);
}

function hws_sc_hartanah(): string
{
    hws_analytics_hit('/hartanah');
    $page = max(1, absint($_GET['halaman'] ?? 1));
    $query = array_filter([
        'kategori_hartanah' => isset($_GET['kategori']) ? sanitize_key(wp_unslash($_GET['kategori'])) : null,
        'kod_daerah' => isset($_GET['daerah']) && preg_match('/^\d{4}$/', (string) $_GET['daerah']) ? (string) $_GET['daerah'] : null,
        'q' => isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : null,
        'page' => $page, 'per_page' => 12,
    ]);
    $res = HWS_Api::get('hartanah', $query, 60);
    if ($res['status'] !== 200) {
        return hws_unavailable();
    }
    $kategori = ['premis_perniagaan' => hws_t('kat_premis_perniagaan'), 'premis_kediaman' => hws_t('kat_premis_kediaman'), 'tanah' => hws_t('kat_tanah'), 'bazar' => hws_t('kat_bazar')];
    $html = '<form class="hws-filter" method="get" role="search"><div class="hws-grid hws-grid--3">'
        .hws_field('q', hws_t('carian'), ['value' => $query['q'] ?? ''])
        .hws_field('kategori', hws_t('kategori'), ['type' => 'select', 'options' => $kategori, 'value' => $query['kategori_hartanah'] ?? ''])
        .'<div class="hws-actions hws-actions--end"><button class="hws-btn hws-btn--secondary" type="submit">'.hws_e(hws_t('tapis')).'</button></div></div>'
        .(hws_lang() === 'en' ? '<input type="hidden" name="lang" value="en">' : '').'</form>';
    $rows = (array) ($res['body']['data'] ?? []);
    $html .= '<p class="hws-count" role="status">'.hws_e(hws_t('bil_hartanah', ['n' => (int) ($res['body']['meta']['total'] ?? 0)])).'</p>';
    if ($rows === []) {
        return $html.hws_notice('info', '<p>'.hws_e(hws_t('tiada_hartanah')).'</p>');
    }
    $html .= '<ul class="hws-grid hws-grid--3 hws-list-reset">';
    foreach ($rows as $p) {
        $html .= '<li class="hws-card"><p class="hws-tag">'.hws_e((string) ($kategori[$p['kategori_hartanah'] ?? ''] ?? $p['kategori_hartanah_nama'] ?? '')).'</p>'
            .'<h3>'.hws_e((string) ($p['nama_premis'] ?? '')).'</h3><dl class="hws-dl">'
            .'<dt>'.hws_e(hws_t('kod')).'</dt><dd>'.hws_e((string) ($p['kod_premis'] ?? '')).'</dd>'
            .'<dt>'.hws_e(hws_t('keluasan')).'</dt><dd>'.hws_e(number_format((float) ($p['keluasan_m2'] ?? 0), 0)).' m²</dd>'
            .'<dt>'.hws_e(hws_t('kadar_sebulan')).'</dt><dd><strong>'.hws_e(hws_money((string) ($p['kadar_tawaran_sebulan'] ?? '0'))).'</strong></dd>'
            .'<dt>'.hws_e(hws_t('daerah')).'</dt><dd>'.hws_e((string) ($p['kod_daerah'] ?? '')).'</dd></dl>'
            .(! empty($p['keterangan']) ? '<p>'.hws_e((string) $p['keterangan']).'</p>' : '')
            .'<a class="hws-btn hws-btn--primary" href="'.esc_url(hws_url('permohonan-sewaan', ['premis' => (string) $p['kod_premis']])).'">'.hws_e(hws_t('mohon_sewa')).'<span class="screen-reader-text"> '.hws_e((string) ($p['nama_premis'] ?? '')).'</span></a></li>';
    }
    $html .= '</ul>';
    $last = (int) ($res['body']['meta']['last_page'] ?? 1);
    if ($last > 1) {
        $html .= '<nav class="hws-pager" aria-label="'.esc_attr(hws_t('halaman')).'">';
        for ($i = 1; $i <= $last; $i++) {
            $html .= $i === $page ? '<span aria-current="page">'.$i.'</span>' : '<a href="'.esc_url(add_query_arg('halaman', $i)).'">'.$i.'</a>';
        }
        $html .= '</nav>';
    }

    return $html;
}

/** @param array<string, string>|string $atts */
function hws_sc_berita($atts = []): string
{
    $atts = shortcode_atts(['bil' => 10, 'jenis' => ''], $atts);
    $types = $atts['jenis'] !== '' ? [(string) $atts['jenis']] : ['hebahan', 'artikel'];
    $items = [];
    foreach ($types as $t) {
        $res = HWS_Api::get('kandungan', ['jenis' => $t, 'per_page' => (int) $atts['bil']], 120);
        if ($res['status'] !== 200) {
            return hws_unavailable();
        }
        $items = array_merge($items, (array) ($res['body']['data'] ?? []));
    }
    usort($items, fn ($a, $b) => strcmp((string) ($b['tarikh_terbit'] ?? ''), (string) ($a['tarikh_terbit'] ?? '')));
    $items = array_slice($items, 0, (int) $atts['bil']);
    if ($items === []) {
        return hws_notice('info', '<p>'.hws_e(hws_t('tiada_berita')).'</p>');
    }
    $html = '<ul class="hws-news hws-list-reset">';
    foreach ($items as $k) {
        $html .= '<li class="hws-card"><p class="hws-meta"><time datetime="'.esc_attr((string) ($k['tarikh_terbit'] ?? '')).'">'.hws_e(hws_date($k['tarikh_terbit'] ?? null)).'</time>'
            .(! empty($k['dibantu_ai']) ? ' · <span class="hws-tag hws-tag--muted">'.hws_e(hws_t('dibantu_ai')).'</span>' : '').'</p>'
            .'<h3>'.hws_e(hws_pilih($k, 'tajuk')).'</h3>'.wpautop(esc_html(hws_pilih($k, 'kandungan'))).'</li>';
    }

    return $html.'</ul>';
}

function hws_sc_projek(): string
{
    hws_analytics_hit('/projek-wakaf');
    $res = HWS_Api::get('kandungan', ['jenis' => 'projek', 'per_page' => 50], 120);
    if ($res['status'] !== 200) {
        return hws_unavailable();
    }
    $rows = (array) ($res['body']['data'] ?? []);
    if ($rows === []) {
        return hws_notice('info', '<p>'.hws_e(hws_t('tiada_projek')).'</p>');
    }
    $html = '<ul class="hws-grid hws-grid--2 hws-list-reset">';
    foreach ($rows as $p) {
        $pct = max(0, min(100, (int) ($p['kemajuan_peratus'] ?? 0)));
        $html .= '<li class="hws-card"><h3>'.hws_e(hws_pilih($p, 'tajuk')).'</h3>'
            .(! empty($p['lokasi']) ? '<p class="hws-meta">'.hws_e((string) $p['lokasi']).'</p>' : '')
            .wpautop(esc_html(hws_pilih($p, 'kandungan')))
            .'<p id="hws-kemajuan-'.esc_attr((string) $p['slug']).'">'.hws_e(hws_t('kemajuan')).': '.$pct.'%</p>'
            .'<div class="hws-progress" role="progressbar" aria-valuenow="'.$pct.'" aria-valuemin="0" aria-valuemax="100" aria-labelledby="hws-kemajuan-'.esc_attr((string) $p['slug']).'"><span style="width:'.$pct.'%"></span></div></li>';
    }

    return $html.'</ul>';
}

function hws_sc_agihan(): string
{
    hws_analytics_hit('/agihan-manfaat');
    $res = HWS_Api::get('agihan', [], 300);
    if ($res['status'] !== 200) {
        return hws_unavailable();
    }
    $rows = (array) ($res['body']['data'] ?? []);
    $html = '<p>'.hws_e(hws_t('agihan_intro')).'</p>';
    if ($rows === []) {
        $html .= hws_notice('info', '<p>'.hws_e(hws_t('tiada_agihan')).'</p>');
    } else {
        $html .= '<div class="hws-table-wrap" tabindex="0"><table class="hws-table"><caption>'.hws_e(hws_t('agihan_jadual')).'</caption><thead><tr>'
            .'<th scope="col">'.hws_e(hws_t('tempoh')).'</th><th scope="col">'.hws_e(hws_t('kategori')).'</th><th scope="col">'.hws_e(hws_t('status_perolehan')).'</th>'
            .'<th scope="col" class="num">'.hws_e(hws_t('bil_tempat')).'</th><th scope="col" class="num">'.hws_e(hws_t('hasil')).'</th><th scope="col" class="num">'.hws_e(hws_t('agihan')).'</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.hws_e((string) ($r['tempoh'] ?? '')).'</td><td>'.hws_e((string) ($r['kategori_hartanah'] ?? '')).'</td>'
                .'<td><span class="hws-tag">'.hws_e((string) ($r['status_perolehan'] ?? '')).'</span></td><td class="num">'.(int) ($r['bil_tempat'] ?? 0).'</td>'
                .'<td class="num">'.hws_e(hws_money((string) ($r['hasil'] ?? '0'))).'</td><td class="num">'.hws_e(hws_money((string) ($r['agihan'] ?? '0'))).'</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    $meta = (array) ($res['body']['meta'] ?? []);

    return $html.'<p class="hws-meta">'.hws_e(hws_t('dikemas_kini')).': '.hws_e(hws_date($meta['dikemas_kini'] ?? null)).'</p>'
        .hws_notice('info', '<p>'.hws_e((string) ($meta['penafian'] ?? '')).'</p>');
}

function hws_sc_statistik(): string
{
    hws_analytics_hit('/statistik');
    $res = HWS_Api::get('statistik', [], 300);
    if ($res['status'] !== 200) {
        return hws_unavailable();
    }
    $rows = (array) ($res['body']['data'] ?? []);
    if ($rows === []) {
        return hws_notice('info', '<p>'.hws_e(hws_t('tiada_statistik')).'</p>');
    }
    $html = '';
    foreach ($rows as $s) {
        $d = (array) ($s['data'] ?? []);
        $html .= '<section class="hws-card"><h2>'.hws_e((string) $s['tajuk']).'</h2><p class="hws-meta">'.hws_e(hws_t('tempoh')).': '.hws_e((string) $s['tempoh']).'</p><div class="hws-grid hws-grid--3">';
        foreach ((array) ($d['permohonan'] ?? []) as $jenis => $p) {
            $html .= '<div class="hws-stat"><span class="hws-stat__n">'.hws_e((string) ($p['jumlah'] ?? 0)).'</span><span>'.hws_e(hws_t('stat_'.$jenis)).'</span></div>';
        }
        if (isset($d['premis_ditawarkan'])) {
            $html .= '<div class="hws-stat"><span class="hws-stat__n">'.(int) $d['premis_ditawarkan'].'</span><span>'.hws_e(hws_t('stat_premis')).'</span></div>';
        }
        $html .= '</div><p class="hws-help">'.hws_e((string) ($d['nota'] ?? '')).'</p></section>';
    }

    return $html;
}

// ------------------------------------------------------------------ forms

function hws_sc_wakaf(): string
{
    hws_analytics_hit('/permohonan-wakaf', true);
    $s = hws_outcome();
    if ($s !== null && ! empty($s['ok'])) {
        return hws_result_box($s);
    }

    return hws_result_box($s).'<p>'.hws_e(hws_t('wakaf_intro')).'</p>'.hws_form_open('wakaf', 'permohonan-wakaf', true)
        .'<fieldset><legend>'.hws_e(hws_t('maklumat_pewakaf')).'</legend>'
        .hws_field('nama_pewakaf', hws_t('nama_pewakaf'), ['required' => true, 'attrs' => ' autocomplete="name" maxlength="200"'], $s)
        .hws_field('jenis_pemohon', hws_t('jenis_pemohon'), ['type' => 'select', 'options' => ['individu' => hws_t('individu'), 'organisasi' => hws_t('organisasi')]], $s)
        .hws_field('no_pengenalan', hws_t('no_pengenalan'), ['help' => hws_t('no_pengenalan_help'), 'attrs' => ' maxlength="30"'], $s)
        .hws_field('emel', hws_t('emel'), ['type' => 'email', 'required' => true, 'help' => hws_t('emel_help'), 'attrs' => ' autocomplete="email"'], $s)
        .'</fieldset><fieldset><legend>'.hws_e(hws_t('maklumat_wakaf')).'</legend>'
        .hws_field('jenis_wakaf', hws_t('jenis_wakaf'), ['type' => 'select', 'options' => ['am' => hws_t('wakaf_am'), 'khas' => hws_t('wakaf_khas'), 'irsod' => hws_t('wakaf_irsod')]], $s)
        .hws_field('tujuan_wakaf', hws_t('tujuan_wakaf'), ['type' => 'textarea'], $s)
        .hws_field('no_lot', hws_t('no_lot'), ['attrs' => ' maxlength="50"'], $s)
        .hws_field('kod_mukim', hws_t('kod_mukim'), ['help' => hws_t('kod_mukim_help'), 'attrs' => ' inputmode="numeric" pattern="[0-9]{6}" maxlength="6"'], $s)
        .hws_field('keluasan', hws_t('keluasan'), ['attrs' => ' maxlength="40"'], $s)
        .hws_field('nilai_anggaran', hws_t('nilai_anggaran'), ['type' => 'number', 'attrs' => ' min="0" step="0.01"'], $s)
        .hws_field('lampiran', hws_t('lampiran'), ['type' => 'file', 'help' => hws_t('lampiran_help')], $s)
        .'</fieldset>'.hws_pdpa_field($s).hws_captcha_field().hws_submit(hws_t('hantar_permohonan')).'</form>';
}

function hws_sc_sewaan(): string
{
    hws_analytics_hit('/permohonan-sewaan', true);
    $s = hws_outcome();
    if ($s !== null && ! empty($s['ok'])) {
        return hws_result_box($s);
    }
    $premis = isset($_GET['premis']) ? sanitize_text_field(wp_unslash($_GET['premis'])) : '';

    return hws_result_box($s).'<p>'.hws_e(hws_t('sewaan_intro')).'</p>'.hws_form_open('sewaan', 'permohonan-sewaan', true)
        .'<fieldset><legend>'.hws_e(hws_t('premis_dipohon')).'</legend>'
        .hws_field('kod_premis', hws_t('kod_premis'), ['required' => true, 'value' => $premis, 'help' => hws_t('kod_premis_help')], $s)
        .hws_field('tarikh_mula_dicadang', hws_t('tarikh_mula'), ['type' => 'date'], $s)
        .hws_field('tempoh_bulan', hws_t('tempoh_bulan'), ['type' => 'number', 'attrs' => ' min="1" max="360"'], $s)
        .hws_field('tujuan', hws_t('tujuan_sewa'), ['type' => 'textarea'], $s)
        .'</fieldset><fieldset><legend>'.hws_e(hws_t('maklumat_pemohon')).'</legend>'
        .hws_field('jenis_entiti', hws_t('jenis_pemohon'), ['type' => 'select', 'required' => true, 'options' => ['individu' => hws_t('individu'), 'syarikat' => hws_t('syarikat')]], $s)
        .hws_field('nama', hws_t('nama'), ['required' => true, 'attrs' => ' autocomplete="name"'], $s)
        .hws_field('no_pengenalan', hws_t('no_kp'), ['help' => hws_t('no_kp_help')], $s)
        .hws_field('no_pendaftaran_syarikat', hws_t('no_ssm'), [], $s)
        .hws_field('alamat', hws_t('alamat'), ['type' => 'textarea', 'attrs' => ' autocomplete="street-address"'], $s)
        .hws_field('poskod', hws_t('poskod'), ['attrs' => ' inputmode="numeric" pattern="[0-9]{5}" maxlength="5" autocomplete="postal-code"'], $s)
        .hws_field('telefon', hws_t('telefon'), ['type' => 'tel', 'attrs' => ' autocomplete="tel"'], $s)
        .hws_field('emel', hws_t('emel'), ['type' => 'email', 'required' => true, 'help' => hws_t('emel_help'), 'attrs' => ' autocomplete="email"'], $s)
        .hws_field('lampiran', hws_t('lampiran'), ['type' => 'file', 'help' => hws_t('lampiran_help')], $s)
        .'</fieldset>'.hws_pdpa_field($s).hws_captcha_field().hws_submit(hws_t('hantar_permohonan')).'</form>';
}

function hws_sc_status(): string
{
    hws_analytics_hit('/semak-status');
    $s = hws_outcome();
    $html = '';
    if ($s !== null && ! empty($s['ok']) && ($s['jenis'] ?? '') === 'status') {
        $d = (array) $s['data'];
        $html .= '<section id="hws-hasil" tabindex="-1" class="hws-card" aria-live="polite"><h2>'.hws_e(hws_t('status_permohonan')).' '.hws_e((string) ($d['no_rujukan'] ?? '')).'</h2>'
            .'<p><span class="hws-tag hws-tag--status">'.hws_e((string) ($d['status_nama'] ?? $d['status'] ?? '')).'</span> · '.hws_e(hws_t('jenis_permohonan_'.($d['jenis'] ?? ''))).'</p><ol class="hws-timeline">';
        foreach (array_reverse((array) ($d['sejarah'] ?? [])) as $h) {
            $html .= '<li><time datetime="'.esc_attr((string) $h['pada']).'">'.hws_e(hws_date($h['pada'])).'</time> — '.hws_e((string) ($h['status_nama'] ?? $h['status'])).'</li>';
        }
        $html .= '</ol></section>';
        $s = null;
    }

    return $html.hws_result_box($s).hws_form_open('status', 'semak-status', false)
        .hws_field('no_rujukan', hws_t('no_rujukan'), ['required' => true, 'help' => hws_t('no_rujukan_help'), 'attrs' => ' maxlength="30" autocapitalize="characters"'], $s)
        .hws_field('emel', hws_t('emel_digunakan'), ['type' => 'email', 'required' => true], $s)
        .'<div class="hws-actions"><button class="hws-btn hws-btn--primary" type="submit">'.hws_e(hws_t('semak')).'</button></div></form>';
}

function hws_sc_bayaran(): string
{
    hws_analytics_hit('/bayaran');
    $ref = isset($_GET['ruj']) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string) wp_unslash($_GET['ruj'])) : '';
    $html = '';
    if ($ref !== '') {
        // Back from the gateway: ask SISDATA (server-side) — the redirect itself proves nothing (SRS-PWS-12).
        $res = HWS_Api::get('bayaran/'.rawurlencode($ref));
        if ($res['status'] === 200) {
            $d = (array) $res['body']['data'];
            $type = ($d['status'] ?? '') === 'berjaya' ? 'success' : (in_array($d['status'] ?? '', ['gagal', 'dibatalkan', 'tamat_tempoh'], true) ? 'error' : 'info');
            $html .= '<div id="hws-hasil" tabindex="-1">'.hws_notice($type, '<h2>'.hws_e(hws_t('status_bayaran')).': '.hws_e((string) ($d['status_nama'] ?? '')).'</h2>'
                .'<p>'.hws_e(hws_t('no_rujukan_bayaran')).': <strong class="hws-ref">'.hws_e((string) $d['no_rujukan']).'</strong> · '.hws_e(hws_money((string) $d['jumlah'])).'</p>'
                .(($d['status'] ?? '') === 'menunggu' ? '<p>'.hws_e(hws_t('bayaran_menunggu')).'</p>' : '')
                .(($d['status'] ?? '') === 'berjaya' ? '<p>'.hws_e(hws_t('bayaran_resit')).'</p>' : '')).'</div>';
        } else {
            $html .= hws_notice('error', '<p>'.hws_e(hws_t('ralat_tiada')).'</p>');
        }
    }
    $s = hws_outcome();
    $ch = HWS_Api::get('bayaran/saluran', [], 600);
    $channels = [];
    foreach ((array) ($ch['body']['data'] ?? []) as $c) {
        $channels[(string) $c['kod']] = (string) $c['nama'];
    }

    return $html.hws_result_box($s).'<p>'.hws_e(hws_t('bayaran_intro')).'</p>'.hws_form_open('bayaran', 'bayaran', false)
        .hws_field('no_kontrak', hws_t('no_kontrak'), ['required' => true, 'help' => hws_t('no_kontrak_help')], $s)
        .hws_field('saluran', hws_t('saluran_bayaran'), ['type' => 'select', 'required' => true, 'options' => $channels ?: ['fpx' => 'FPX', 'kad' => hws_t('kad')]], $s)
        .hws_field('nama_pembayar', hws_t('nama_pembayar'), ['attrs' => ' autocomplete="name"'], $s)
        .hws_field('emel_pembayar', hws_t('emel_resit'), ['type' => 'email', 'attrs' => ' autocomplete="email"'], $s)
        .hws_captcha_field().'<p class="hws-help">'.hws_e(hws_t('bayaran_pci')).'</p>'
        .'<div class="hws-actions"><button class="hws-btn hws-btn--primary" type="submit">'.hws_e(hws_t('teruskan_bayar')).'</button></div></form>';
}

function hws_sc_gateway(): string
{
    if (! (defined('HWS_GATEWAY_UJIAN') && HWS_GATEWAY_UJIAN)) {
        return hws_notice('info', '<p>'.hws_e(hws_t('ralat_tiada')).'</p>');
    }
    $ref = isset($_GET['ruj']) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string) wp_unslash($_GET['ruj'])) : '';
    $res = $ref !== '' ? HWS_Api::get('bayaran/'.rawurlencode($ref)) : ['status' => 404, 'body' => []];
    if ($res['status'] !== 200) {
        return hws_notice('error', '<p>'.hws_e(hws_t('ralat_tiada')).'</p>');
    }
    $d = (array) $res['body']['data'];
    $html = hws_notice('warning', '<p><strong>'.hws_e(hws_t('gateway_ujian_amaran')).'</strong></p>')
        .'<dl class="hws-dl"><dt>'.hws_e(hws_t('no_rujukan_bayaran')).'</dt><dd>'.hws_e((string) $d['no_rujukan']).'</dd><dt>'.hws_e(hws_t('jumlah')).'</dt><dd>'.hws_e(hws_money((string) $d['jumlah'])).'</dd>'
        .'<dt>'.hws_e(hws_t('saluran_bayaran')).'</dt><dd>'.hws_e((string) $d['saluran']).'</dd></dl>';
    foreach (['berjaya' => 'primary', 'gagal' => 'secondary', 'dibatalkan' => 'secondary'] as $k => $style) {
        $html .= hws_form_open('gateway', 'gateway-ujian', false).'<input type="hidden" name="ruj" value="'.esc_attr($ref).'"><input type="hidden" name="keputusan" value="'.esc_attr($k).'">'
            .'<button class="hws-btn hws-btn--'.$style.'" type="submit">'.hws_e(hws_t('simulasi_'.$k)).'</button></form>';
    }

    return '<div class="hws-gateway">'.$html.'</div>';
}

function hws_sc_aduan(): string
{
    hws_analytics_hit('/aduan', true);
    $s = hws_outcome();
    $html = '';
    if ($s !== null && ! empty($s['ok']) && ($s['jenis'] ?? '') === 'semak_aduan') {
        $d = (array) $s['data'];
        $html .= '<section id="hws-hasil" tabindex="-1" class="hws-card"><h2>'.hws_e(hws_t('status_aduan')).' '.hws_e((string) ($d['no_rujukan_aduan'] ?? '')).'</h2><p><span class="hws-tag hws-tag--status">'.hws_e((string) ($d['status'] ?? '')).'</span></p>'
            .'<p class="hws-meta">'.hws_e(hws_t('dikemas_kini')).': '.hws_e(hws_date($d['tarikh_kemas_kini'] ?? null)).'</p></section>';
        $s = null;
    } elseif ($s !== null && ! empty($s['ok'])) {
        return hws_result_box($s);
    }
    $kat = [];
    $ref = HWS_Api::get('aduan/rujukan', [], 600);
    foreach ((array) ($ref['body']['data']['kategori'] ?? []) as $k) {
        $kat[(string) $k['kod']] = (string) $k['nama'];
    }

    return $html.hws_result_box($s).'<div class="hws-grid hws-grid--2 hws-grid--top"><section><h2>'.hws_e(hws_t('buat_aduan')).'</h2>'.hws_form_open('aduan', 'aduan', true)
        .hws_field('kategori', hws_t('kategori_aduan'), ['type' => 'select', 'required' => true, 'options' => $kat], $s)
        .hws_field('keterangan', hws_t('keterangan_aduan'), ['type' => 'textarea', 'required' => true, 'help' => hws_t('keterangan_help')], $s)
        .hws_field('lokasi_teks', hws_t('lokasi'), ['required' => true], $s)
        .hws_field('nama', hws_t('nama'), ['attrs' => ' autocomplete="name"'], $s)
        .hws_field('emel', hws_t('emel'), ['type' => 'email', 'attrs' => ' autocomplete="email"'], $s)
        .hws_field('no_telefon', hws_t('telefon'), ['type' => 'tel', 'attrs' => ' autocomplete="tel"'], $s)
        .hws_field('tanpa_nama', hws_t('tanpa_nama'), ['type' => 'checkbox'], $s)
        .hws_field('fail', hws_t('gambar_dokumen'), ['type' => 'file', 'help' => hws_t('lampiran_help')], $s)
        .hws_captcha_field().hws_submit(hws_t('hantar_aduan')).'</form></section>'
        .'<section><h2>'.hws_e(hws_t('semak_aduan')).'</h2>'.hws_form_open('semak_aduan', 'aduan', false)
        .hws_field('no_rujukan_aduan', hws_t('no_rujukan_aduan'), ['required' => true], null)
        .hws_field('pengesahan', hws_t('emel_atau_telefon'), ['help' => hws_t('pengesahan_help')], null)
        .'<div class="hws-actions"><button class="hws-btn hws-btn--secondary" type="submit">'.hws_e(hws_t('semak')).'</button></div></form></section></div>';
}

function hws_sc_soalan_lazim(): string
{
    hws_analytics_hit('/soalan-lazim');
    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    $html = '<form class="hws-filter" method="get" role="search">'.hws_field('q', hws_t('cari_soalan'), ['value' => $q])
        .(hws_lang() === 'en' ? '<input type="hidden" name="lang" value="en">' : '').'<div class="hws-actions"><button class="hws-btn hws-btn--secondary" type="submit">'.hws_e(hws_t('cari')).'</button></div></form>';

    $list = HWS_Api::get('kandungan', ['jenis' => 'soal_jawab', 'per_page' => 50], 300);
    $faq = (array) ($list['body']['data'] ?? []);
    if (mb_strlen($q) >= 2) {
        $res = HWS_Api::get('soalan-lazim', ['q' => $q], 120);
        $faq = (array) ($res['body']['data']['soalan_lazim'] ?? []);
        $kb = (array) ($res['body']['data']['pangkalan_pengetahuan'] ?? []);
        if ($kb !== []) {
            $html .= '<h2>'.hws_e(hws_t('rujukan_dokumen')).'</h2><ul>';
            foreach ($kb as $r) {
                $html .= '<li><strong>'.hws_e((string) $r['dokumen']).'</strong>'.(! empty($r['seksyen']) ? ' ('.hws_e((string) $r['seksyen']).')' : '').': '.hws_e((string) $r['petikan']).'</li>';
            }
            $html .= '</ul>';
        }
    }
    if ($faq !== []) {
        $html .= '<div class="hws-accordion">';
        foreach ($faq as $f) {
            $html .= '<details><summary>'.hws_e(hws_pilih($f, 'tajuk')).'</summary>'.wpautop(esc_html(hws_pilih($f, 'kandungan'))).'</details>';
        }
        $html .= '</div>';
    } else {
        $html .= hws_notice('info', '<p>'.hws_e(hws_t('tiada_soalan')).'</p>');
    }
    $s = hws_outcome();
    if ($s !== null && ! empty($s['ok']) && ($s['jenis'] ?? '') === 'tanya') {
        $d = (array) $s['data'];
        $html .= '<section id="hws-hasil" tabindex="-1" class="hws-card" aria-live="polite"><h2>'.hws_e(hws_t('jawapan')).'</h2><p class="hws-meta">'.hws_e((string) $s['soalan']).'</p>'
            .wpautop(esc_html((string) ($d['jawapan'] ?? ''))).'<p class="hws-help">'.hws_e((string) ($d['penafian'] ?? '')).'</p></section>';
        $s = null;
    }

    return $html.hws_result_box($s).'<h2>'.hws_e(hws_t('tanya_soalan')).'</h2>'.hws_form_open('tanya', 'soalan-lazim', false)
        .hws_field('soalan', hws_t('soalan_anda'), ['type' => 'textarea', 'required' => true], $s)
        .'<div class="hws-actions"><button class="hws-btn hws-btn--primary" type="submit">'.hws_e(hws_t('tanya')).'</button></div></form>';
}

function hws_sc_hubungi(): string
{
    hws_analytics_hit('/hubungi');
    $s = hws_outcome();
    if ($s !== null && ! empty($s['ok'])) {
        return hws_result_box($s);
    }

    return '<div class="hws-grid hws-grid--2 hws-grid--top"><section><h2>'.hws_e(hws_t('alamat_mais')).'</h2><address>Majlis Agama Islam Selangor (MAIS)<br>Bahagian Wakaf<br>Tingkat 9, Menara Utara, Bangunan Sultan Idris Shah<br>40000 Shah Alam, Selangor</address>'
        .'<p>'.hws_e(hws_t('waktu_pejabat')).'</p></section><section><h2>'.hws_e(hws_t('permintaan_sokongan')).'</h2>'.hws_result_box($s).hws_form_open('sokongan', 'hubungi', false)
        .hws_field('tajuk', hws_t('tajuk'), ['required' => true, 'attrs' => ' maxlength="200"'], $s)
        .hws_field('kategori', hws_t('kategori'), ['type' => 'select', 'options' => ['akaun' => hws_t('sok_akaun'), 'permohonan' => hws_t('sok_permohonan'), 'bayaran' => hws_t('sok_bayaran'), 'teknikal' => hws_t('sok_teknikal'), 'lain' => hws_t('sok_lain')]], $s)
        .hws_field('keterangan', hws_t('keterangan'), ['type' => 'textarea', 'required' => true], $s)
        .hws_field('emel', hws_t('emel'), ['type' => 'email', 'required' => true, 'attrs' => ' autocomplete="email"'], $s)
        .hws_captcha_field().'<div class="hws-actions"><button class="hws-btn hws-btn--primary" type="submit">'.hws_e(hws_t('hantar')).'</button></div></form></section></div>';
}
