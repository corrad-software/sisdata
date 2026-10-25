<?php
if (! defined('ABSPATH')) {
    exit;
}
get_header();
?>
<div class="hws-wrap hws-page">
<?php if (have_posts()) { ?>
    <?php while (have_posts()) {
        the_post(); ?>
        <article <?php post_class('hws-article'); ?>>
            <h1><?php echo esc_html(hws_page_title()); ?></h1>
            <?php the_content(); ?>
        </article>
    <?php } ?>
<?php } else { ?>
    <h1><?php echo esc_html(hws_t('tiada_kandungan')); ?></h1>
<?php } ?>
</div>
<?php
get_footer();
