<?php
/**
 * Halaman umum.
 */
get_header();
the_post();
?>
<article class="article">
	<div class="container container--narrow">
		<h1 class="article__title"><?php the_title(); ?></h1>
		<div class="entry__content"><?php the_content(); ?></div>
	</div>
</article>
<?php
get_footer();
