<?php
/**
 * Post individual do blog.
 *
 * @package Contorno
 */

declare( strict_types = 1 );

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="contorno-single" id="post-<?php the_ID(); ?>">
		<header class="contorno-single__header">
			<div class="site-container">
				<p class="eyebrow"><?php echo esc_html( (string) get_the_date() ); ?></p>
				<h1 class="contorno-single__title"><?php the_title(); ?></h1>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="contorno-single__media">
				<div class="site-container"><?php the_post_thumbnail( 'contorno-hero' ); ?></div>
			</figure>
		<?php endif; ?>

		<div class="site-container">
			<div class="contorno-single__content prose-institutional">
				<?php the_content(); ?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
