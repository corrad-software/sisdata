<?php
if (! defined('ABSPATH')) {
    exit;
}
status_header(404);
get_header();
?>
<div class="hws-wrap hws-page">
    <h1><?php echo esc_html(hws_t('tiada_halaman')); ?></h1>
    <p><a class="hws-btn hws-btn--primary" href="<?php echo esc_url(hws_url()); ?>"><?php echo esc_html(hws_t('ke_utama')); ?></a></p>
</div>
<?php
get_footer();
