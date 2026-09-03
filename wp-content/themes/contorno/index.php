<?php
/**
 * Fallback de listagem (blog, busca, arquivos de categoria).
 *
 * @package Contorno
 */

declare( strict_types = 1 );

get_header();
?>

<div class="contorno-archive contorno-archive--posts">
	<header class="contorno-page__header">
		<div class="site-container">
			<h1 class="contorno-page__title">
				<?php
				if ( is_search() ) {
					printf(
						/* translators: %s: search query */
						esc_html__( 'Resultados para "%s"', 'contorno' ),
						esc_html( get_search_query() )
					);
				} elseif ( is_home() ) {
					esc_html_e( 'Blog', 'contorno' );
				} else {
					echo esc_html( wp_strip_all_tags( (string) get_the_archive_title() ) );
				}
				?>
			</h1>
		</div>
	</header>

	<div class="site-container">
		<?php if ( have_posts() ) : ?>
			<div class="contorno-post-grid motion-stagger" data-contorno-reveal>
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article class="contorno-post-card motion-item motion-card" id="post-<?php the_ID(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="contorno-post-card__media motion-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
								<?php the_post_thumbnail( 'contorno-card' ); ?>
							</a>
						<?php endif; ?>

						<div class="contorno-post-card__body">
							<p class="contorno-post-card__date"><?php echo esc_html( (string) get_the_date() ); ?></p>
							<h2 class="contorno-post-card__title">
								<a class="motion-title-link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
							<p class="contorno-post-card__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination(
				array(
					'mid_size'  => 1,
					'prev_text' => esc_html__( 'Anterior', 'contorno' ),
					'next_text' => esc_html__( 'Proxima', 'contorno' ),
				)
			);
			?>
		<?php else : ?>
			<p class="contorno-archive__empty"><?php esc_html_e( 'Nada encontrado.', 'contorno' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
