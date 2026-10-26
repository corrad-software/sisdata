<?php
/**
 * Halaman projek Wakaf Khas.
 */
get_header();
the_post();
$id    = get_the_ID();
$kos   = (float) get_post_meta( $id, '_pws_kos', true );
$kutip = get_post_meta( $id, '_pws_kutip', true );
$pct   = ( '' !== $kutip && $kos > 0 ) ? min( 100, (int) round( (float) $kutip / $kos * 100 ) ) : null;
$img   = get_the_post_thumbnail_url( $id, 'large' );
$others = array_values( array_filter( pws_projects(), function ( $p ) use ( $id ) { return $p['id'] !== $id; } ) );
?>
<article class="proj">
	<div class="container proj__grid">
		<div class="proj__copy">
			<nav class="crumbs" aria-label="Jejak"><a href="<?php echo pws_khas_url(); ?>">Wakaf Khas</a> <span aria-hidden="true">/</span> <span><?php the_title(); ?></span></nav>
			<h1 class="proj__title"><?php the_title(); ?></h1>
			<?php if ( $kos > 0 ) : ?>
				<p class="proj__cost"><small>Kos pembinaan</small><b><?php echo esc_html( pws_juta_label( $kos ) ); ?></b></p>
			<?php endif; ?>

			<?php if ( null !== $pct ) : ?>
				<div class="proj__progress">
					<div class="progress progress--lg" role="img" aria-label="<?php echo (int) $pct; ?> peratus dikutip"><span style="--w:<?php echo (int) $pct; ?>%"></span></div>
					<p><strong><?php echo esc_html( pws_juta_label( (float) $kutip ) ); ?></strong> telah dikutip daripada <?php echo esc_html( pws_juta_label( $kos ) ); ?> (<?php echo (int) $pct; ?>%)</p>
				</div>
			<?php endif; ?>

			<div class="entry__content proj__content"><?php the_content(); ?></div>

			<div class="proj__cta">
				<a class="btn btn--primary btn--lg" href="<?php echo pws_donate_url(); ?>">Sumbang Wakaf <?php echo pws_icon( 'arrow', 18 ); ?></a>
				<a class="btn btn--outline btn--lg" href="<?php echo pws_khas_url(); ?>">Semua projek</a>
			</div>
		</div>

		<div class="proj__visual">
			<div class="hx proj__hex"><span class="hx__shape"><span class="hx__in"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( 'Pandangan ' . get_the_title() ); ?>" width="516" height="318"><?php endif; ?></span></span></div>
		</div>
	</div>
</article>

<?php if ( $others ) : ?>
<section class="section section--sand">
	<div class="container">
		<h2 class="proj__more-title">Projek Wakaf Khas lain</h2>
		<ul class="pgrid">
			<?php foreach ( $others as $p ) : ?>
				<li>
					<a class="pcard" href="<?php echo esc_url( $p['link'] ); ?>">
						<span class="pcard__media"><img src="<?php echo esc_url( $p['img'] ); ?>" alt="<?php echo esc_attr( $p['alt'] ); ?>" loading="lazy" width="516" height="318"></span>
						<?php if ( $p['label'] ) : ?><span class="pcard__badge"><small>Kos</small><b><?php echo esc_html( str_replace( ' juta', '', $p['label'] ) ); ?></b><i>juta</i></span><?php endif; ?>
						<span class="pcard__body"><h3><?php echo esc_html( $p['name'] ); ?></h3><span class="link-arrow">Lihat projek <?php echo pws_icon( 'arrow', 16 ); ?></span></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>
<?php
get_footer();
