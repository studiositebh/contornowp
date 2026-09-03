<?php
/**
 * Blog.
 *
 * DECISAO DE CONTEUDO
 * -------------------
 * O blog do React le do Supabase. O que existe la sao 297 posts do template
 * ANTERIOR (Renato Assis advocacia: direito medico, blindagem patrimonial,
 * protecao veicular), com midia em midia-renatoassis.voceconecta.com.br.
 * Nao ha um unico post da Contorno do Corpo.
 *
 * Migrar esse acervo violaria a regra explicita do projeto: nenhum residuo do
 * template antigo e nenhuma referencia a renatoassis. Por isso o blog nasce
 * VAZIO no WordPress, com a estrutura pronta para a Contorno publicar.
 *
 * FALLBACK FIEL
 * -------------
 * Quando nao ha post publicado, o React mostra tres cards institucionais
 * estaticos (HOME_BLOG_POSTS) — nao clicaveis, so para nao deixar a secao
 * vazia. Reproduzimos exatamente isso, com as mesmas imagens da Contorno.
 * Assim que o cliente publicar o primeiro post, os cards reais assumem.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cards institucionais de exemplo — porte de HOME_BLOG_POSTS.
 *
 * @return array<int,array<string,string>>
 */
function contorno_blog_placeholder_cards(): array {
	return array(
		array(
			'category' => __( 'Inauguração', 'contorno' ),
			'title'    => __( 'Nova unidade em São José dos Campos!', 'contorno' ),
			'excerpt'  => __( 'Chegamos com tudo! Estrutura completa para transformar sua rotina.', 'contorno' ),
			'date'     => __( '06 de Maio de 2024', 'contorno' ),
			'image'    => '/blog/inauguracao.jpg',
		),
		array(
			'category' => __( 'Eventos', 'contorno' ),
			'title'    => __( 'Contorno Run: vem aí!', 'contorno' ),
			'excerpt'  => __( 'Participe da nossa corrida oficial e viva essa energia com a gente.', 'contorno' ),
			'date'     => __( '02 de Maio de 2024', 'contorno' ),
			'image'    => '/blog/contorno-run.jpg',
		),
		array(
			'category' => __( 'Novidades', 'contorno' ),
			'title'    => __( 'Novas aulas no seu app', 'contorno' ),
			'excerpt'  => __( 'Pilates, Yoga e HIIT agora disponíveis em mais unidades.', 'contorno' ),
			'date'     => __( '28 de Abril de 2024', 'contorno' ),
			'image'    => '/blog/aulas-app.jpg',
		),
	);
}

/**
 * Card de post real.
 */
function contorno_render_post_card( int $post_id ): string {
	$image = (string) get_the_post_thumbnail_url( $post_id, 'contorno-card' );
	$terms = get_the_terms( $post_id, 'category' );
	$label = is_array( $terms ) && isset( $terms[0] ) ? $terms[0]->name : __( 'Blog', 'contorno' );

	ob_start();
	?>
	<article class="contorno-post-card motion-card motion-item">
		<a class="contorno-post-card__media motion-media" href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>" tabindex="-1" aria-hidden="true">
			<?php if ( '' !== $image ) : ?>
				<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
			<?php else : ?>
				<span class="contorno-placeholder is-variant-<?php echo esc_attr( (string) ( $post_id % 4 ) ); ?>" aria-hidden="true"></span>
			<?php endif; ?>
			<span class="contorno-post-card__tag"><?php echo esc_html( $label ); ?></span>
		</a>
		<div class="contorno-post-card__body">
			<p class="contorno-post-card__date"><?php echo esc_html( (string) get_the_date( '', $post_id ) ); ?></p>
			<h3 class="contorno-post-card__title">
				<a class="motion-title-link" href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>"><?php echo esc_html( (string) get_the_title( $post_id ) ); ?></a>
			</h3>
			<p class="contorno-post-card__excerpt"><?php echo esc_html( wp_strip_all_tags( (string) get_the_excerpt( $post_id ) ) ); ?></p>
		</div>
	</article>
	<?php

	return (string) ob_get_clean();
}

/**
 * Card estatico de exemplo (sem link).
 *
 * @param array<string,string> $card
 */
function contorno_render_placeholder_card( array $card, int $index ): string {
	$image = contorno_resolve_media( $card['image'], 'contorno-card' );

	ob_start();
	?>
	<article class="contorno-post-card motion-item is-placeholder">
		<div class="contorno-post-card__media">
			<?php if ( '' !== $image ) : ?>
				<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
			<?php else : ?>
				<span class="contorno-placeholder is-variant-<?php echo esc_attr( (string) ( $index % 4 ) ); ?>" aria-hidden="true"></span>
			<?php endif; ?>
			<span class="contorno-post-card__tag"><?php echo esc_html( $card['category'] ); ?></span>
		</div>
		<div class="contorno-post-card__body">
			<p class="contorno-post-card__date"><?php echo esc_html( $card['date'] ); ?></p>
			<h3 class="contorno-post-card__title"><?php echo esc_html( $card['title'] ); ?></h3>
			<p class="contorno-post-card__excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p>
		</div>
	</article>
	<?php

	return (string) ob_get_clean();
}

/**
 * CONTORNO — Blog (prévia).
 *
 * Usado na Home e nas paginas de unidade. Mostra posts reais quando existem;
 * cai nos cards institucionais quando o blog ainda esta vazio.
 */
contorno_add_shortcode(
	'contorno_blog_preview',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow'   => '',
				'title'     => '',
				'text'      => '',
				'limit'     => '3',
				'cta_label' => '',
				'tone'      => 'light',
			),
			(array) $atts,
			'contorno_blog_preview'
		);

		$limit = max( 1, (int) $a['limit'] );

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
			)
		);

		$blog_page = get_page_by_path( 'blog' );
		$blog_url  = $blog_page instanceof WP_Post ? (string) get_permalink( $blog_page ) : home_url( '/blog/' );

		ob_start();
		echo contorno_section_open( 'blog', array( 'tone' => (string) $a['tone'], 'class' => 'news-section blog-section' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="contorno-blog-head">
			<?php echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			echo contorno_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'' !== trim( (string) $a['cta_label'] ) ? (string) $a['cta_label'] : __( 'Ver o blog', 'contorno' ),
				$blog_url,
				'outline',
				array( 'class' => 'cta-label' )
			);
			?>
		</div>

		<div class="contorno-post-grid motion-stagger" data-contorno-reveal>
			<?php
			if ( array() !== $posts ) {
				foreach ( $posts as $post ) {
					echo contorno_render_post_card( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			} else {
				foreach ( array_slice( contorno_blog_placeholder_cards(), 0, $limit ) as $index => $card ) {
					echo contorno_render_placeholder_card( $card, (int) $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			?>
		</div>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Listagem do Blog.
 *
 * Componente da pagina /blog, com paginacao nativa do WordPress.
 */
contorno_add_shortcode(
	'contorno_blog_list',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'per_page'   => '9',
				'empty_text' => '',
				'tone'       => 'light',
			),
			(array) $atts,
			'contorno_blog_list'
		);

		$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) $a['per_page'] ),
				'paged'          => $paged,
			)
		);

		$empty = '' !== trim( (string) $a['empty_text'] )
			? (string) $a['empty_text']
			: __( 'Ainda não há publicações. Os conteúdos da Contorno aparecem aqui assim que forem publicados.', 'contorno' );

		ob_start();
		echo contorno_section_open( 'blog-list', array( 'tone' => (string) $a['tone'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $query->have_posts() ) {
			echo '<div class="contorno-post-grid motion-stagger" data-contorno-reveal>';
			while ( $query->have_posts() ) {
				$query->the_post();
				echo contorno_render_post_card( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';

			$links = paginate_links(
				array(
					'total'     => (int) $query->max_num_pages,
					'current'   => $paged,
					'mid_size'  => 1,
					'prev_text' => __( 'Anterior', 'contorno' ),
					'next_text' => __( 'Próxima', 'contorno' ),
					'type'      => 'array',
				)
			);

			if ( is_array( $links ) && array() !== $links ) {
				echo '<nav class="contorno-pagination" aria-label="' . esc_attr__( 'Paginação', 'contorno' ) . '">';
				foreach ( $links as $link ) {
					echo wp_kses_post( $link );
				}
				echo '</nav>';
			}

			wp_reset_postdata();
		} else {
			printf( '<p class="contorno-blog-empty">%s</p>', esc_html( $empty ) );
		}

		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);
