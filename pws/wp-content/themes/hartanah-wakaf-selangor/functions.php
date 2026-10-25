<?php

/**
 * Hartanah Wakaf Selangor theme — MAIS corporate identity, responsive,
 * bilingual (BM/EN via the SISDATA Connector's hws_lang()/hws_t()), basic
 * WCAG 2.1 AA (skip link, landmarks, visible focus, contrast, lang attr).
 */
if (! defined('ABSPATH')) {
    exit;
}

// The theme needs the connector for language + data; degrade gracefully without it.
if (! function_exists('hws_lang')) {
    function hws_lang(): string
    {
        return 'ms';
    }
}
if (! function_exists('hws_t')) {
    function hws_t(string $key, array $vars = []): string
    {
        return $key === 'nama_rasmi' ? 'Portal Hartanah Wakaf Selangor' : $key;
    }
}
if (! function_exists('hws_url')) {
    function hws_url(string $slug = '', array $query = []): string
    {
        return home_url('/'.($slug !== '' ? trailingslashit($slug) : ''));
    }
}

add_action('after_setup_theme', function (): void {
    add_theme_support('title-tag');
    add_theme_support('html5', ['search-form', 'comment-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    register_nav_menus(['utama' => 'Menu utama', 'kaki' => 'Menu kaki']);
});

add_action('wp_enqueue_scripts', function (): void {
    $v = wp_get_theme()->get('Version');
    wp_enqueue_style('hws-main', get_template_directory_uri().'/assets/css/main.css', [], $v);
    wp_enqueue_script('hws-main', get_template_directory_uri().'/assets/js/main.js', [], $v, ['in_footer' => true, 'strategy' => 'defer']);
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('global-styles');
}, 20);

// No inline scripts (CSP script-src 'self'): emoji detection, speculation rules.
add_filter('wp_speculation_rules_configuration', '__return_null');
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

// <html lang> follows the portal language.
add_filter('language_attributes', fn () => 'lang="'.(hws_lang() === 'en' ? 'en' : 'ms').'"');

// Official name everywhere (SRS-PWS-01): document title "<page> — Portal Hartanah Wakaf Selangor".
add_filter('pre_get_document_title', fn (string $title): string => is_front_page() ? hws_t('nama_rasmi') : $title);
add_filter('document_title_parts', function (array $parts): array {
    if (is_singular()) {
        $parts['title'] = hws_page_title(get_queried_object());
    } elseif (is_404()) {
        $parts['title'] = hws_t('tiada_halaman');
    }
    unset($parts['tagline']);
    $parts['site'] = hws_t('nama_rasmi');

    return $parts;
});
add_filter('document_title_separator', fn () => '—');

/** Page title in the current language (EN title stored in post meta hws_tajuk_en). */
function hws_page_title($post = null): string
{
    $post = get_post($post);
    if (! $post) {
        return '';
    }
    $en = (string) get_post_meta($post->ID, 'hws_tajuk_en', true);

    return hws_lang() === 'en' && $en !== '' ? $en : get_the_title($post);
}

/** Main navigation: the portal's pages in a fixed, accessible order. */
function hws_nav_items(): array
{
    return [
        ['hartanah', 'nav_hartanah'], ['projek-wakaf', 'nav_projek'], ['permohonan-wakaf', 'nav_wakaf'], ['permohonan-sewaan', 'nav_sewaan'],
        ['bayaran', 'nav_bayaran'], ['semak-status', 'nav_status'], ['agihan-manfaat', 'nav_agihan'], ['berita', 'nav_berita'],
        ['soalan-lazim', 'nav_soalan'], ['aduan', 'nav_aduan'], ['statistik', 'nav_statistik'], ['hubungi', 'nav_hubungi'],
    ];
}

function hws_lang_switch(): string
{
    $other = hws_lang() === 'en' ? 'ms' : 'en';
    $url = add_query_arg('lang', $other, remove_query_arg(['lang', 'hws_r']));

    return '<a class="hws-lang" href="'.esc_url($url).'" hreflang="'.esc_attr($other).'" lang="'.esc_attr($other).'">'
        .esc_html($other === 'en' ? 'English' : 'Bahasa Melayu').'</a>';
}
