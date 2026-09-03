<?php
/**
 * Template Name: Pagina em branco (WPBakery)
 *
 * Sem titulo automatico e sem container: o conteudo montado no builder
 * controla toda a largura da tela. E o template recomendado para Home,
 * landings e campanhas.
 *
 * @package Contorno
 */

declare( strict_types = 1 );

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="contorno-page contorno-page--blank" id="post-<?php the_ID(); ?>">
		<?php the_content(); ?>
	</article>
	<?php
endwhile;

get_footer();
