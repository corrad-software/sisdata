<?php
if (! defined('ABSPATH')) {
    exit;
}
$hws_current = is_singular() ? get_post_field('post_name', get_queried_object_id()) : '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?php echo esc_attr(hws_t('meta_keterangan')); ?>">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="hws-skip" href="#kandungan"><?php echo esc_html(hws_t('langkau')); ?></a>
<div class="hws-masthead" role="note">
    <div class="hws-wrap"><span><?php echo esc_html(hws_t('masthead')); ?></span><?php echo hws_lang_switch(); // phpcs:ignore -- escaped inside?></div>
</div>
<header class="hws-header">
    <div class="hws-wrap hws-header__row">
        <a class="hws-brand" href="<?php echo esc_url(hws_url()); ?>" aria-label="<?php echo esc_attr(hws_t('nama_rasmi').' — '.hws_t('utama')); ?>">
            <svg class="hws-logo" viewBox="0 0 48 48" width="44" height="44" aria-hidden="true" focusable="false"><rect width="48" height="48" rx="10" fill="#00573F"/><path d="M24 8l12 9v4H12v-4l12-9zm-9 15h4v12h-4V23zm7 0h4v12h-4V23zm7 0h4v12h-4V23zM11 37h26v3H11v-3z" fill="#C9A227"/></svg>
            <span class="hws-brand__text"><span class="hws-brand__name"><?php echo esc_html(hws_t('nama_rasmi')); ?></span><span class="hws-brand__org">Majlis Agama Islam Selangor</span></span>
        </a>
        <button class="hws-menu-toggle" type="button" aria-expanded="false" aria-controls="hws-nav"><span class="hws-menu-toggle__bar" aria-hidden="true"></span><?php echo esc_html(hws_t('menu')); ?></button>
    </div>
    <nav id="hws-nav" class="hws-nav" aria-label="<?php echo esc_attr(hws_t('navigasi_utama')); ?>">
        <ul class="hws-wrap">
            <?php foreach (hws_nav_items() as [$slug, $key]) { ?>
                <li><a href="<?php echo esc_url(hws_url($slug)); ?>"<?php echo $hws_current === $slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html(hws_t($key)); ?></a></li>
            <?php } ?>
        </ul>
    </nav>
</header>
<main id="kandungan" class="hws-main" tabindex="-1">
