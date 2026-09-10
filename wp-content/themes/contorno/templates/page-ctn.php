<?php
/**
 * Template Name: Pagina CTN (dark, sem header)
 *
 * Aplica a skin dark premium e SUPRIME o header institucional — a pagina
 * comeca direto no Hero dark, com o logo CTN sobre o proprio hero.
 *
 * @package Contorno
 */

declare( strict_types = 1 );

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="contorno-page contorno-page--ctn" id="post-<?php the_ID(); ?>">
		<?php the_content(); ?>
	</article>
	<?php
endwhile;

get_footer();
