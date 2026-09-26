<?php
/**
 * CONTORNO — Slider da Home.
 *
 * Alternativa nativa e leve ao Hero (que tem escurecimento/overlay e imagem
 * unica por edicao de pagina): banners rotativos simples, sem overlay e sem
 * texto sobreposto, geridos em Contorno > Slider da Home — nao por atributo
 * do shortcode. O bloco em si nao recebe parametros: so mostra os slides
 * ativos, na ordem cadastrada.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array<string,mixed> $slide
 */
function contorno_render_home_slider_slide( array $slide, int $index, int $total, bool $is_active ): string {
	$desktop_id = (int) $slide['image_desktop_id'];

	if ( 0 === $desktop_id ) {
		return '';
	}

	// wp_get_attachment_image() ja resolve srcset/sizes responsivos a partir
	// dos tamanhos intermediarios do attachment — nao serve so o "full".
	$desktop_img = wp_get_attachment_image(
		$desktop_id,
		'full',
		false,
		array(
			'class'         => 'contorno-home-slider__img',
			'alt'           => '',
			'loading'       => 0 === $index ? 'eager' : 'lazy',
			'decoding'      => 'async',
			'fetchpriority' => 0 === $index ? 'high' : 'auto',
			'sizes'         => '100vw',
		)
	);

	if ( '' === $desktop_img ) {
		return '';
	}

	$mobile_id     = (int) $slide['image_mobile_id'];
	$mobile_srcset = $mobile_id > 0 ? wp_get_attachment_image_srcset( $mobile_id, 'full' ) : false;

	$image = sprintf(
		'<picture class="contorno-home-slider__picture">%s%s</picture>',
		$mobile_srcset ? sprintf( '<source media="(max-width: 640px)" srcset="%s" />', esc_attr( $mobile_srcset ) ) : '',
		$desktop_img // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado por wp_get_attachment_image(), ja escapado.
	);

	$link_url = (string) $slide['link_url'];

	if ( '' !== $link_url ) {
		$image = sprintf(
			'<a class="contorno-home-slider__link" href="%s" %s>%s</a>',
			esc_url( $link_url ),
			'blank' === $slide['link_target'] ? 'target="_blank" rel="noopener"' : '',
			$image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado acima, ja escapado.
		);
	}

	return sprintf(
		'<div class="contorno-home-slider__slide%s" data-contorno-home-slider-slide role="group" aria-roledescription="slide" aria-label="%s" %s>%s</div>',
		$is_active ? ' is-active' : '',
		esc_attr( sprintf( /* translators: 1: posicao 2: total */ __( 'Slide %1$d de %2$d', 'contorno' ), $index + 1, $total ) ),
		$is_active ? '' : 'aria-hidden="true"',
		$image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado acima, ja escapado.
	);
}

contorno_add_shortcode(
	'contorno_home_slider',
	static function (): string {
		$slides = contorno_home_slider_active_slides();

		if ( array() === $slides ) {
			return '';
		}

		contorno_enqueue_component( 'home-slider' );

		$settings = contorno_home_slider_data()['settings'];
		$total    = count( $slides );

		$markup = '';
		foreach ( $slides as $index => $slide ) {
			$markup .= contorno_render_home_slider_slide( $slide, $index, $total, 0 === $index );
		}

		$dots = '';
		if ( $total > 1 ) {
			for ( $i = 0; $i < $total; $i++ ) {
				$dots .= sprintf(
					'<button type="button" class="contorno-home-slider__dot%s" data-contorno-home-slider-dot data-index="%d" aria-label="%s"></button>',
					0 === $i ? ' is-active' : '',
					$i,
					esc_attr( sprintf( /* translators: %d: numero do slide */ __( 'Ir para o slide %d', 'contorno' ), $i + 1 ) )
				);
			}
		}

		// Velocidade da transicao via variavel CSS — nada de JS mexendo em estilo.
		return sprintf(
			'<div class="contorno-home-slider" data-contorno-home-slider data-interval="%d" style="--contorno-slider-speed:%dms" role="region" aria-roledescription="carousel" aria-label="%s">%s%s</div>',
			(int) $settings['interval'],
			(int) $settings['speed'],
			esc_attr__( 'Destaques', 'contorno' ),
			$markup, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado por contorno_render_home_slider_slide(), ja escapado.
			$dots // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- botoes montados acima com esc_attr().
		);
	}
);
