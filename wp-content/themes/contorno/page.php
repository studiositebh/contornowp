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

	// Texto corrido, sem shortcodes (politica, termos, regulamentos): coluna de leitura.
	$contorno_is_document = false === strpos( (string) get_post_field( 'post_content' ), '[' );
	?>
	<article class="contorno-page<?php echo $contorno_is_document ? ' contorno-page--document' : ''; ?>" id="post-<?php the_ID(); ?>">
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

		<?php
		$request_path    = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) : '';
		$is_contact_page = is_page( 'fale-conosco' ) || 'fale-conosco' === basename( $request_path );
		?>
		<?php if ( $is_contact_page ) : ?>
			<div class="contorno-page__content contorno-contact-page">
				<div class="contorno-contact-page__grid">
					<?php the_content(); ?>
				</div>
			</div>
		<?php else : ?>
			<div class="contorno-page__content<?php echo $contorno_is_document ? ' contorno-document' : ''; ?>">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
