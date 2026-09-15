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
	$contorno_categories = get_the_category();
	$contorno_category   = isset( $contorno_categories[0] ) ? $contorno_categories[0]->name : __( 'Blog', 'contorno' );
	?>
	<article class="contorno-single" id="post-<?php the_ID(); ?>">
		<header class="contorno-single__header">
			<div class="site-container">
				<a class="contorno-single__back" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>"><?php echo contorno_icon( 'arrow-right', 'contorno-single__back-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Voltar para o blog', 'contorno' ); ?></a>
				<p class="eyebrow"><?php echo esc_html( $contorno_category ); ?></p>
				<h1 class="contorno-single__title"><?php the_title(); ?></h1>
				<div class="contorno-single__meta">
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( (string) get_the_date() ); ?></time>
					<span aria-hidden="true">•</span>
					<span><?php echo esc_html( sprintf( /* translators: %d: estimated reading minutes */ _n( '%d min de leitura', '%d min de leitura', max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 220 ) ), 'contorno' ), max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 220 ) ) ) ); ?></span>
				</div>
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
