<?php
/**
 * Pagina institucional.
 *
 * Todo o conteudo vem do editor (WPBakery). O tema so fornece o container e
 * o cabecalho opcional, para que Home, Sobre, Contato e campanhas sejam
 * montaveis e reorganizaveis pelo painel.
 *
 * @package Contorno
 */

declare( strict_types = 1 );

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="contorno-page" id="post-<?php the_ID(); ?>">
		<?php if ( ! is_front_page() && ! contorno_page_hides_title() ) : ?>
			<header class="contorno-page__header">
				<div class="site-container">
					<h1 class="contorno-page__title"><?php the_title(); ?></h1>
					<?php if ( has_excerpt() ) : ?>
						<p class="contorno-page__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
					<?php endif; ?>
				</div>
			</header>
		<?php endif; ?>

		<div class="contorno-page__content">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
