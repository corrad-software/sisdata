<?php
if (! defined('ABSPATH')) {
    exit;
}
get_header();
?>
<section class="hws-hero" aria-labelledby="hws-hero-title">
    <div class="hws-wrap">
        <h1 id="hws-hero-title"><?php echo esc_html(hws_t('nama_rasmi')); ?></h1>
        <p class="hws-lead"><?php echo esc_html(hws_t('hero_teks')); ?></p>
        <p class="hws-hero__actions">
            <a class="hws-btn hws-btn--gold" href="<?php echo esc_url(hws_url('hartanah')); ?>"><?php echo esc_html(hws_t('lihat_hartanah')); ?></a>
            <a class="hws-btn hws-btn--outline" href="<?php echo esc_url(hws_url('permohonan-wakaf')); ?>"><?php echo esc_html(hws_t('daftar_hasrat')); ?></a>
        </p>
    </div>
</section>
<div class="hws-wrap hws-page">
    <?php echo do_shortcode('[hws_ringkasan_utama]'); ?>
</div>
<?php
get_footer();
