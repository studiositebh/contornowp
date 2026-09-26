<?php
/**
 * Slider da Home — dados.
 *
 * Recurso nativo para substituir o uso do WPBakery Hero (com overlay/scrim)
 * na Home quando o cliente so precisa de banners rotativos simples. Guardado
 * numa OPTION (mesma razao do catalogo de atributos: configuracao, nao
 * conteudo — sem CPT, sem revisao, sem REST).
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_HOME_SLIDER_OPTION = 'contorno_home_slider';
const CONTORNO_HOME_SLIDER_PAGE   = 'contorno-slider-home';

/**
 * @return array{interval:int,speed:int}
 */
function contorno_home_slider_default_settings(): array {
	return array(
		'interval' => 6000,
		'speed'    => 600,
	);
}

/**
 * @param array<string,mixed> $settings
 *
 * @return array{interval:int,speed:int}
 */
function contorno_home_slider_sanitize_settings( array $settings ): array {
	$defaults = contorno_home_slider_default_settings();

	// Intervalo entre 2s e 20s; velocidade da transicao entre 150ms e 2s.
	$interval = (int) ( $settings['interval'] ?? $defaults['interval'] );
	$interval = min( 20000, max( 2000, $interval ) );

	$speed = (int) ( $settings['speed'] ?? $defaults['speed'] );
	$speed = min( 2000, max( 150, $speed ) );

	return array(
		'interval' => $interval,
		'speed'    => $speed,
	);
}

/**
 * @param array<string,mixed> $item
 *
 * @return array{image_desktop_id:int,image_mobile_id:int,link_url:string,link_target:string,order:int,active:bool}
 */
function contorno_home_slider_sanitize_slide( array $item ): array {
	$image_desktop_id = absint( $item['image_desktop_id'] ?? 0 );
	if ( $image_desktop_id > 0 && ( 'attachment' !== get_post_type( $image_desktop_id ) || ! wp_attachment_is_image( $image_desktop_id ) ) ) {
		$image_desktop_id = 0;
	}

	$image_mobile_id = absint( $item['image_mobile_id'] ?? 0 );
	if ( $image_mobile_id > 0 && ( 'attachment' !== get_post_type( $image_mobile_id ) || ! wp_attachment_is_image( $image_mobile_id ) ) ) {
		$image_mobile_id = 0;
	}

	$link_url = esc_url_raw( trim( (string) ( $item['link_url'] ?? '' ) ) );

	$link_target = sanitize_key( (string) ( $item['link_target'] ?? 'self' ) );
	$link_target = 'blank' === $link_target ? 'blank' : 'self';

	return array(
		'image_desktop_id' => $image_desktop_id,
		'image_mobile_id'  => $image_mobile_id,
		'link_url'         => $link_url,
		'link_target'      => $link_target,
		'order'            => (int) ( $item['order'] ?? 0 ),
		'active'           => ! empty( $item['active'] ),
	);
}

/**
 * @return array{settings:array{interval:int,speed:int},slides:array<int,array<string,mixed>>}
 */
function contorno_home_slider_data(): array {
	$stored = get_option( CONTORNO_HOME_SLIDER_OPTION, null );

	if ( ! is_array( $stored ) ) {
		return array(
			'settings' => contorno_home_slider_default_settings(),
			'slides'   => array(),
		);
	}

	$slides = array();
	foreach ( (array) ( $stored['slides'] ?? array() ) as $slide ) {
		if ( is_array( $slide ) ) {
			$slides[] = contorno_home_slider_sanitize_slide( $slide );
		}
	}

	usort( $slides, static fn ( array $a, array $b ): int => $a['order'] <=> $b['order'] );

	return array(
		'settings' => contorno_home_slider_sanitize_settings( is_array( $stored['settings'] ?? null ) ? (array) $stored['settings'] : array() ),
		'slides'   => $slides,
	);
}

/**
 * Apenas os slides ativos, na ordem de exibicao — o que o frontend usa.
 *
 * @return array<int,array<string,mixed>>
 */
function contorno_home_slider_active_slides(): array {
	return array_values(
		array_filter(
			contorno_home_slider_data()['slides'],
			static fn ( array $slide ): bool => $slide['active'] && $slide['image_desktop_id'] > 0
		)
	);
}

/**
 * @param array<int,array<string,mixed>> $slides
 * @param array<string,mixed>            $settings
 */
function contorno_home_slider_save( array $slides, array $settings ): bool {
	$clean = array();

	foreach ( $slides as $slide ) {
		$clean[] = contorno_home_slider_sanitize_slide( is_array( $slide ) ? $slide : array() );
	}

	return (bool) update_option(
		CONTORNO_HOME_SLIDER_OPTION,
		array(
			'version'  => 1,
			'settings' => contorno_home_slider_sanitize_settings( $settings ),
			'slides'   => $clean,
		),
		true
	);
}
