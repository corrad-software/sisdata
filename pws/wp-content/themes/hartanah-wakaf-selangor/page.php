<?php
if (! defined('ABSPATH')) {
    exit;
}
get_header();
while (have_posts()) {
    the_post();
    $hws_intro = (string) get_post_meta(get_the_ID(), hws_lang() === 'en' ? 'hws_intro_en' : 'hws_intro_ms', true);
    ?>
    <div class="hws-pagehead">
        <div class="hws-wrap">
            <nav class="hws-breadcrumb" aria-label="<?php echo esc_attr(hws_t('jejak')); ?>"><a href="<?php echo esc_url(hws_url()); ?>"><?php echo esc_html(hws_t('utama')); ?></a> <span aria-hidden="true">›</span> <span aria-current="page"><?php echo esc_html(hws_page_title()); ?></span></nav>
            <h1><?php echo esc_html(hws_page_title()); ?></h1>
            <?php if ($hws_intro !== '') { ?><p class="hws-lead"><?php echo esc_html($hws_intro); ?></p><?php } ?>
        </div>
    </div>
    <div class="hws-wrap hws-page">
        <article <?php post_class('hws-article'); ?>>
            <?php
            // EN body (post meta hws_kandungan_en) for static pages; shortcodes render bilingual themselves.
            $hws_en = (string) get_post_meta(get_the_ID(), 'hws_kandungan_en', true);
    if (hws_lang() === 'en' && $hws_en !== '') {
        echo do_shortcode(shortcode_unautop(wpautop(wp_kses_post($hws_en)))); // phpcs:ignore -- editor content sanitised first; shortcodes escape their own output
    } else {
        the_content();
    }
    ?>
        </article>
    </div>
<?php }
get_footer();
