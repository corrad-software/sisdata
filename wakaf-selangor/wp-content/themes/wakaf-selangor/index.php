<?php
/**
 * Templat asas: halaman Berita (senarai pos), carian dan arkib.
 */
get_header();
?>
<section class="phero">
	<div class="container">
		<p class="eyebrow">Berita</p>
		<h1 class="phero__title"><span>Aktiviti dan</span> <strong>perkembangan terbaru</strong></h1>
		<p class="phero__lead">Berita dan aktiviti terkini Perbadanan Wakaf Selangor.</p>
	</div>
</section>

<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<ul class="news-grid news-grid--list">
				<?php while ( have_posts() ) : the_post(); ?>
					<li>
						<a class="news-card" href="<?php the_permalink(); ?>">
							<?php if ( has_post_thumbnail() ) : ?>
								<span class="news-card__img"><?php the_post_thumbnail( 'pws-card', array( 'loading' => 'lazy', 'alt' => '' ) ); ?></span>
							<?php endif; ?>
							<span class="news-card__body">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
								<h2><?php the_title(); ?></h2>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>
							</span>
						</a>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php
			the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '← Terdahulu', 'next_text' => 'Seterusnya →', 'screen_reader_text' => 'Navigasi berita' ) );
			?>
		<?php else : ?>
			<p class="muted" style="text-align:center">Tiada kandungan dijumpai.</p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
