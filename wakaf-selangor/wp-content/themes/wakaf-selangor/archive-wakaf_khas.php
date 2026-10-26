<?php
/**
 * Senarai projek Wakaf Khas.
 */
get_header();
$projects = pws_projects();
$total    = array_sum( wp_list_pluck( $projects, 'kos' ) );
?>
<section class="phero">
	<div class="container">
		<p class="eyebrow">Wakaf Khas</p>
		<h1 class="phero__title"><span>Projek masjid</span> <strong>yang sedang dibangunkan</strong></h1>
		<p class="phero__lead"><?php echo (int) count( $projects ); ?> projek dengan jumlah kos <strong><?php echo esc_html( pws_juta_label( $total ) ); ?></strong>. Setiap sumbangan wakaf anda membantu membina masjid untuk masyarakat Selangor.</p>
	</div>
</section>

<section class="section">
	<div class="container">
		<ul class="pgrid">
			<?php foreach ( $projects as $p ) : ?>
				<li>
					<a class="pcard" href="<?php echo esc_url( $p['link'] ); ?>">
						<span class="pcard__media"><img src="<?php echo esc_url( $p['img'] ); ?>" alt="<?php echo esc_attr( $p['alt'] ); ?>" loading="lazy" width="516" height="318"></span>
						<?php if ( $p['label'] ) : ?><span class="pcard__badge"><small>Kos</small><b><?php echo esc_html( str_replace( ' juta', '', $p['label'] ) ); ?></b><i>juta</i></span><?php endif; ?>
						<span class="pcard__body">
							<h2><?php echo esc_html( $p['name'] ); ?></h2>
							<?php if ( null !== $p['kutip'] && $p['kos'] > 0 ) : $pct = min( 100, round( $p['kutip'] / $p['kos'] * 100 ) ); ?>
								<span class="progress" role="img" aria-label="<?php echo (int) $pct; ?> peratus dikutip"><span style="--w:<?php echo (int) $pct; ?>%"></span></span>
								<span class="pcard__meta"><?php echo esc_html( pws_juta_label( $p['kutip'] ) ); ?> telah dikutip (<?php echo (int) $pct; ?>%)</span>
							<?php endif; ?>
							<span class="link-arrow">Lihat projek <?php echo pws_icon( 'arrow', 16 ); ?></span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="khas__more"><a class="btn btn--primary btn--lg" href="<?php echo pws_donate_url(); ?>">Sumbang Wakaf <?php echo pws_icon( 'arrow', 18 ); ?></a></p>
	</div>
</section>
<?php
get_footer();
