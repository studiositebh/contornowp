<?php
/**
 * CONTORNO — Slider.
 *
 * Renderer unico usado tanto pelo bloco WPBakery quanto pelo shortcode
 * manual [contorno_slider id="..."] — nunca duas copias de HTML/JS pro
 * mesmo slider. Cada instancia na pagina e independente (sem id no DOM,
 * so classes/data-attrs), entao dois sliders na mesma pagina funcionam sem
 * conflito.
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
function contorno_render_slider_slide( array $slide, int $index, int $total, bool $is_active ): string {
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
			'class'         => 'contorno-slider__img',
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
		'<picture class="contorno-slider__picture">%s%s</picture>',
		$mobile_srcset ? sprintf( '<source media="(max-width: 640px)" srcset="%s" />', esc_attr( $mobile_srcset ) ) : '',
		$desktop_img // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado por wp_get_attachment_image(), ja escapado.
	);

	$link_url = (string) $slide['link_url'];

	if ( '' !== $link_url ) {
		$image = sprintf(
			'<a class="contorno-slider__link" href="%s" %s>%s</a>',
			esc_url( $link_url ),
			'blank' === $slide['link_target'] ? 'target="_blank" rel="noopener"' : '',
			$image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado acima, ja escapado.
		);
	}

	return sprintf(
		'<div class="contorno-slider__slide%s" data-contorno-slider-slide role="group" aria-roledescription="slide" aria-label="%s" %s>%s</div>',
		$is_active ? ' is-active' : '',
		esc_attr( sprintf( /* translators: 1: posicao 2: total */ __( 'Slide %1$d de %2$d', 'contorno' ), $index + 1, $total ) ),
		$is_active ? '' : 'aria-hidden="true"',
		$image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado acima, ja escapado.
	);
}

/**
 * @param array<string,mixed> $slider Ja saneado (contorno_slider_get()/contorno_slider_sanitize()).
 */
function contorno_render_slider_markup( array $slider ): string {
	$slides = contorno_slider_active_slides( $slider );

	if ( array() === $slides ) {
		return '';
	}

	contorno_enqueue_component( 'home-slider' );

	$settings = $slider['settings'];
	$total    = count( $slides );

	$markup = '';
	foreach ( $slides as $index => $slide ) {
		$markup .= contorno_render_slider_slide( $slide, $index, $total, 0 === $index );
	}

	$dots = '';
	if ( $total > 1 && ! empty( $settings['show_dots'] ) ) {
		$dot_buttons = '';
		for ( $i = 0; $i < $total; $i++ ) {
			$dot_buttons .= sprintf(
				'<button type="button" class="contorno-slider__dot%s" data-contorno-slider-dot data-index="%d" aria-label="%s"></button>',
				0 === $i ? ' is-active' : '',
				$i,
				esc_attr( sprintf( /* translators: %d: numero do slide */ __( 'Ir para o slide %d', 'contorno' ), $i + 1 ) )
			);
		}
		$dots = '<div class="contorno-slider__dots" data-contorno-slider-dots>' . $dot_buttons . '</div>';
	}

	// Velocidade/autoplay/pausa-no-hover via atributos — nada de JS mexendo em estilo.
	return sprintf(
		'<div class="contorno-slider" data-contorno-slider data-interval="%d" data-autoplay="%s" data-pause-on-hover="%s" style="--contorno-slider-speed:%dms" role="region" aria-roledescription="carousel" aria-label="%s">%s%s</div>',
		(int) $settings['interval'],
		! empty( $settings['autoplay'] ) ? '1' : '0',
		! empty( $settings['pause_on_hover'] ) ? '1' : '0',
		(int) $settings['speed'],
		esc_attr__( 'Destaques', 'contorno' ),
		$markup, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado por contorno_render_slider_slide(), ja escapado.
		$dots // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- botoes montados acima com esc_attr().
	);
}

/**
 * [contorno_slider id="home-principal"] — id e o identificador estavel
 * cadastrado em Contorno > Slider Contorno. Mesmo shortcode que o elemento
 * WPBakery usa (o elemento so escolhe o "id" por um dropdown).
 */
contorno_add_shortcode(
	'contorno_slider',
	static function ( array|string $atts ): string {
		$a  = shortcode_atts( array( 'id' => '' ), (array) $atts, 'contorno_slider' );
		$id = sanitize_key( (string) $a['id'] );

		if ( '' === $id ) {
			return '';
		}

		$slider = contorno_slider_get( $id );

		if ( null === $slider || ! $slider['active'] ) {
			return '';
		}

		return contorno_render_slider_markup( $slider );
	}
);

/**
 * Compatibilidade: o shortcode antigo (sem parametros, de quando so existia
 * UM slider global) continua funcionando caso ja tenha sido inserido em
 * alguma pagina — aponta pro slider migrado (CONTORNO_SLIDER_LEGACY_ID).
 * Nao aparece mais no seletor do WPBakery (substituido por
 * "CONTORNO — Slider"): so existe pra nao quebrar conteudo ja publicado.
 */
contorno_add_shortcode(
	'contorno_home_slider',
	static function (): string {
		$slider = contorno_slider_get( CONTORNO_SLIDER_LEGACY_ID );

		if ( null === $slider || ! $slider['active'] ) {
			return '';
		}

		return contorno_render_slider_markup( $slider );
	}
);
