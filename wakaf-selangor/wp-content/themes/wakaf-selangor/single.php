<?php
/**
 * Artikel berita.
 */
get_header();
the_post();
$related = new WP_Query( array( 'posts_per_page' => 3, 'post__not_in' => array( get_the_ID() ), 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
?>
<article class="article">
	<div class="container container--narrow">
		<nav class="crumbs" aria-label="Jejak"><a href="<?php echo pws_news_url(); ?>">Berita</a> <span aria-hidden="true">/</span> <span>Artikel</span></nav>
		<time class="article__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
		<h1 class="article__title"><?php the_title(); ?></h1>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="article__img"><?php the_post_thumbnail( 'large', array( 'alt' => '' ) ); ?></figure>
		<?php endif; ?>
		<div class="entry__content"><?php the_content(); ?></div>
		<?php $src = get_post_meta( get_the_ID(), '_pws_source_url', true ); if ( $src ) : ?>
			<p class="article__source">Sumber asal: <a href="<?php echo esc_url( $src ); ?>" rel="noopener">wakafselangor.gov.my</a></p>
		<?php endif; ?>
		<p><a class="link-arrow" href="<?php echo pws_news_url(); ?>"><?php echo pws_icon( 'arrow', 16 ); ?> Semua berita</a></p>
	</div>
</article>
<?php if ( $related->have_posts() ) : ?>
<section class="section section--sand">
	<div class="container">
		<h2 class="proj__more-title">Berita lain</h2>
		<ul class="news-grid">
			<?php while ( $related->have_posts() ) : $related->the_post(); ?>
				<li>
					<a class="news-card" href="<?php the_permalink(); ?>">
						<?php if ( has_post_thumbnail() ) : ?><span class="news-card__img"><?php the_post_thumbnail( 'pws-card', array( 'loading' => 'lazy', 'alt' => '' ) ); ?></span><?php endif; ?>
						<span class="news-card__body"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time><h3><?php the_title(); ?></h3></span>
					</a>
				</li>
			<?php endwhile; wp_reset_postdata(); ?>
		</ul>
	</div>
</section>
<?php endif; ?>
<?php
get_footer();
