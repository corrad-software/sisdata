<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" href="<?php echo pws_img( 'logoPWSlogo.png' ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Terus ke kandungan</a>

<div class="topbar">
	<div class="container topbar__inner">
		<ul class="topbar__info">
			<li><?php echo pws_icon( 'phone', 15 ); ?><span>Wakaf Hotline <a href="tel:+60355188001">03-5518 8001</a></span></li>
			<li><?php echo pws_icon( 'clock', 15 ); ?><span>Isnin–Jumaat, 8:30 pagi – 4:30 petang</span></li>
		</ul>
		<a class="topbar__link" href="https://bo.wakafselangor.gov.my/" rel="noopener">Borang Online</a>
	</div>
</div>

<header class="site-header" id="top">
	<div class="container site-header__inner">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Perbadanan Wakaf Selangor — laman utama">
			<img src="<?php echo pws_img( 'logoPWShitam200.png' ); ?>" alt="Perbadanan Wakaf Selangor" width="146" height="50">
		</a>

		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
			<span class="nav-toggle__open"><?php echo pws_icon( 'menu', 24 ); ?></span>
			<span class="nav-toggle__close"><?php echo pws_icon( 'close', 24 ); ?></span>
			<span class="screen-reader-text">Menu</span>
		</button>

		<nav class="primary-nav" id="primary-nav" aria-label="Menu utama">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'menu',
				'fallback_cb'    => 'pws_default_menu',
			) );
			?>
			<a class="btn btn--gold nav-cta" href="<?php echo pws_donate_url(); ?>">Sumbang Wakaf</a>
		</nav>
	</div>
</header>

<main id="main">
