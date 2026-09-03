<?php
/**
 * Componentes editoriais institucionais.
 *
 * Todos recebem conteudo por ATRIBUTO — nenhum texto fica hardcoded em PHP.
 * No WPBakery cada atributo aparece como um campo amigavel (ver
 * includes/builder/wpbakery.php).
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CONTORNO — Hero.
 *
 * Hero institucional: eyebrow, headline com trecho destacado, subtitulo,
 * imagem de fundo, ate dois CTAs e busca opcional de unidades.
 */
contorno_add_shortcode(
	'contorno_hero',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow'     => '',
				'title'       => '',
				'highlight'   => '',
				'subtitle'    => '',
				'image'       => '',
				'overlay'     => '65',
				'cta_label'   => '',
				'cta_url'     => '',
				'cta2_label'  => '',
				'cta2_url'    => '',
				'show_search' => 'no',
				'height'      => 'tall',
				'tone'        => 'dark',
			),
			(array) $atts,
			'contorno_hero'
		);

		$image = contorno_attr_image( $a['image'], 'contorno-hero' );

		// O trecho destacado recebe a cor da marca sem exigir HTML do editor.
		$title = esc_html( (string) $a['title'] );
		if ( '' !== trim( (string) $a['highlight'] ) ) {
			$needle = esc_html( (string) $a['highlight'] );
			$title  = str_replace( $needle, '<em class="contorno-hero__highlight">' . $needle . '</em>', $title );
		}

		ob_start();
		?>
		<section class="contorno-hero is-height-<?php echo esc_attr( sanitize_html_class( (string) $a['height'] ) ); ?> is-tone-<?php echo esc_attr( sanitize_html_class( (string) $a['tone'] ) ); ?>">
			<?php if ( '' !== $image ) : ?>
				<img class="contorno-hero__media motion-hero-media" src="<?php echo esc_url( $image ); ?>" alt="" fetchpriority="high" decoding="async" />
				<span class="contorno-hero__scrim" style="--contorno-overlay:<?php echo esc_attr( (string) ( (int) $a['overlay'] / 100 ) ); ?>" aria-hidden="true"></span>
			<?php endif; ?>

			<div class="site-container contorno-hero__inner">
				<div class="contorno-hero__copy motion-hero-copy">
					<?php if ( '' !== trim( (string) $a['eyebrow'] ) ) : ?>
						<p class="eyebrow"><?php echo esc_html( (string) $a['eyebrow'] ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== trim( (string) $a['title'] ) ) : ?>
						<h1 class="contorno-hero__title"><?php echo wp_kses_post( $title ); ?></h1>
					<?php endif; ?>

					<?php if ( '' !== trim( (string) $a['subtitle'] ) ) : ?>
						<p class="contorno-hero__subtitle"><?php echo esc_html( (string) $a['subtitle'] ); ?></p>
					<?php endif; ?>

					<?php
					$buttons = contorno_button( (string) $a['cta_label'], (string) $a['cta_url'], 'primary' )
						. contorno_button( (string) $a['cta2_label'], (string) $a['cta2_url'], 'ghost' );

					if ( '' !== $buttons ) :
						?>
						<div class="contorno-hero__actions motion-hero-actions"><?php echo $buttons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado por contorno_button(). ?></div>
						<?php
					endif;
					?>

					<?php if ( 'yes' === $a['show_search'] ) : ?>
						<div class="contorno-hero__search motion-hero-search">
							<?php echo do_shortcode( '[contorno_units_search target="hero"]' ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — PUV (proposta unica de valor).
 *
 * Bloco de texto + imagem com ordem invertivel. Serve para "Sobre",
 * "Diferenciais institucionais" e qualquer secao 50/50.
 */
contorno_add_shortcode(
	'contorno_puv',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow'    => '',
				'title'      => '',
				'text'       => '',
				'extra_text' => '',
				'image'      => '',
				'image_alt'  => '',
				'layout'     => 'image-right',
				'tone'       => 'light',
				'cta_label'  => '',
				'cta_url'    => '',
				'bullets'    => '',
			),
			(array) $atts,
			'contorno_puv'
		);

		$image   = contorno_attr_image( $a['image'], 'contorno-gallery' );
		$bullets = contorno_lines_to_array( (string) $a['bullets'] );

		ob_start();
		echo contorno_section_open( 'puv', array( 'tone' => (string) $a['tone'], 'class' => 'is-layout-' . sanitize_html_class( (string) $a['layout'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="contorno-puv">
			<div class="contorno-puv__copy motion-reveal" data-contorno-reveal>
				<?php echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php if ( '' !== trim( (string) $a['text'] ) ) : ?>
					<div class="contorno-puv__text prose-institutional"><?php echo wp_kses_post( wpautop( (string) $a['text'] ) ); ?></div>
				<?php endif; ?>

				<?php if ( array() !== $bullets ) : ?>
					<ul class="contorno-puv__bullets">
						<?php foreach ( $bullets as $bullet ) : ?>
							<li><?php echo contorno_icon( 'check', 'contorno-puv__check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $bullet ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( '' !== trim( (string) $a['extra_text'] ) ) : ?>
					<p class="contorno-puv__extra"><?php echo esc_html( (string) $a['extra_text'] ); ?></p>
				<?php endif; ?>

				<?php echo contorno_button( (string) $a['cta_label'], (string) $a['cta_url'], 'primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<?php if ( '' !== $image ) : ?>
				<figure class="contorno-puv__media motion-reveal motion-media" data-contorno-reveal>
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $a['image_alt'] ); ?>" loading="lazy" decoding="async" />
				</figure>
			<?php endif; ?>
		</div>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Banner.
 *
 * Faixa promocional larga com imagem de fundo e CTA.
 */
contorno_add_shortcode(
	'contorno_banner',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow'   => '',
				'title'     => '',
				'text'      => '',
				'image'     => '',
				'overlay'   => '70',
				'cta_label' => '',
				'cta_url'   => '',
				'tone'      => 'dark',
				'align'     => 'left',
			),
			(array) $atts,
			'contorno_banner'
		);

		$image = contorno_attr_image( $a['image'], 'contorno-hero' );

		ob_start();
		?>
		<section class="contorno-banner is-tone-<?php echo esc_attr( sanitize_html_class( (string) $a['tone'] ) ); ?> is-align-<?php echo esc_attr( sanitize_html_class( (string) $a['align'] ) ); ?>">
			<?php if ( '' !== $image ) : ?>
				<img class="contorno-banner__media" src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
				<span class="contorno-banner__scrim" style="--contorno-overlay:<?php echo esc_attr( (string) ( (int) $a['overlay'] / 100 ) ); ?>" aria-hidden="true"></span>
			<?php endif; ?>
			<div class="site-container contorno-banner__inner motion-reveal" data-contorno-reveal>
				<?php echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'], (string) $a['align'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo contorno_button( (string) $a['cta_label'], (string) $a['cta_url'], 'primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — CTA final.
 *
 * Faixa de fechamento de pagina (porte de FinalCta).
 */
contorno_add_shortcode(
	'contorno_cta',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'headline'  => '',
				'text'      => '',
				'cta_label' => '',
				'cta_url'   => '',
				'image'     => '',
				'tone'      => 'dark',
			),
			(array) $atts,
			'contorno_cta'
		);

		$image = contorno_attr_image( $a['image'], 'contorno-hero' );

		ob_start();
		?>
		<section class="contorno-final-cta is-tone-<?php echo esc_attr( sanitize_html_class( (string) $a['tone'] ) ); ?>">
			<?php if ( '' !== $image ) : ?>
				<img class="contorno-final-cta__media" src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
				<span class="contorno-final-cta__scrim" aria-hidden="true"></span>
			<?php endif; ?>
			<div class="site-container contorno-final-cta__inner motion-reveal" data-contorno-reveal>
				<?php if ( '' !== trim( (string) $a['headline'] ) ) : ?>
					<h2 class="contorno-final-cta__headline"><?php echo esc_html( (string) $a['headline'] ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $a['text'] ) ) : ?>
					<p class="contorno-final-cta__text"><?php echo esc_html( (string) $a['text'] ); ?></p>
				<?php endif; ?>
				<?php echo contorno_button( (string) $a['cta_label'], (string) $a['cta_url'], 'primary', array( 'icon' => 'arrow-right' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Destaques.
 *
 * Grade de cards com icone + titulo + texto. Os itens vem de um param_group
 * do WPBakery (nao de HTML solto).
 */
contorno_add_shortcode(
	'contorno_highlights',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow' => '',
				'title'   => '',
				'text'    => '',
				'items'   => '',
				'columns' => '4',
				'tone'    => 'light',
				'align'   => 'center',
			),
			(array) $atts,
			'contorno_highlights'
		);

		$items = contorno_decode_param_group( (string) $a['items'] );

		if ( array() === $items ) {
			return '';
		}

		ob_start();
		echo contorno_section_open( 'highlights', array( 'tone' => (string) $a['tone'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'], (string) $a['align'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="contorno-highlights is-columns-<?php echo esc_attr( (string) (int) $a['columns'] ); ?> motion-stagger" data-contorno-reveal>
			<?php foreach ( $items as $item ) : ?>
				<?php
				$label = (string) ( $item['label'] ?? '' );
				$icon  = (string) ( $item['icon'] ?? '' );
				$icon  = '' !== $icon ? $icon : contorno_icon_for_label( $label );
				?>
				<article class="unit-highlight-card motion-item">
					<span class="unit-highlight-card__icon-wrap"><?php echo contorno_icon( $icon, 'unit-highlight-card__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3 class="unit-highlight-card__label"><?php echo esc_html( $label ); ?></h3>
					<?php if ( ! empty( $item['text'] ) ) : ?>
						<p class="unit-highlight-card__text"><?php echo esc_html( (string) $item['text'] ); ?></p>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Area editorial.
 *
 * Slot opcional de conteudo por unidade/CTN. O editor monta o conteudo no
 * WPBakery dentro do proprio registro da unidade, e o template compartilhado
 * injeta esse conteudo na posicao escolhida — sem duplicar paginas.
 */
contorno_add_shortcode(
	'contorno_editorial_area',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'slot' => 'before_plans',
				'unit' => '',
			),
			(array) $atts,
			'contorno_editorial_area'
		);

		$post_id = contorno_resolve_context_id( (array) $a );

		if ( ! $post_id ) {
			return '';
		}

		return contorno_render_editorial_slot( $post_id, (string) $a['slot'] );
	}
);

/**
 * Renderiza o conteudo editorial da Unidade/CTN quando o slot pedido coincide
 * com a posicao escolhida no campo "Posicao do conteudo editorial".
 *
 * O conteudo em si e o post_content — editado no WPBakery, dentro do proprio
 * registro. Assim a estrutura obrigatoria do template continua no codigo e o
 * editor ganha um espaco livre, sem duplicar paginas por unidade.
 */
function contorno_render_editorial_slot( int $post_id, string $slot ): string {
	$position = contorno_field_text( 'editorial_position', $post_id, 'none' );

	if ( 'main' !== $slot && $position !== $slot ) {
		return '';
	}

	if ( 'main' === $slot && 'none' === $position ) {
		return '';
	}

	$content = (string) get_post_field( 'post_content', $post_id );

	if ( '' === trim( $content ) ) {
		return '';
	}

	$html = apply_filters( 'the_content', $content );

	return '<div class="contorno-editorial contorno-editorial--' . esc_attr( sanitize_html_class( $slot ) ) . '">' . $html . '</div>';
}
