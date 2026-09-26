<?php
/**
 * Slider Contorno — dados.
 *
 * Varios sliders (Home, CTN, campanhas...), cada um com nome, identificador
 * estavel, ativo/inativo, slides e configuracoes proprias. Guardado numa
 * OPTION so (mapa id => slider): configuracao, nao conteudo — sem CPT, sem
 * revisao, sem REST, igual ao catalogo de atributos.
 *
 * Ate esta rodada existia so UM slider global ("Slider da Home"), guardado
 * na option CONTORNO_SLIDER_LEGACY_OPTION. contorno_sliders_maybe_migrate()
 * converte esse slider unico no primeiro slider desta lista (id
 * "home-principal"), uma unica vez, sem perder nenhum slide/configuracao.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_SLIDERS_OPTION       = 'contorno_sliders';
const CONTORNO_SLIDERS_PAGE         = 'contorno-sliders';
const CONTORNO_SLIDER_LEGACY_OPTION = 'contorno_home_slider';
const CONTORNO_SLIDER_LEGACY_ID     = 'home-principal';
const CONTORNO_SLIDERS_MIGRATED_FLAG = 'contorno_sliders_migrated_v1';

/**
 * @return array{interval:int,speed:int,autoplay:bool,show_dots:bool,pause_on_hover:bool}
 */
function contorno_slider_default_settings(): array {
	return array(
		'interval'       => 6000,
		'speed'          => 600,
		'autoplay'       => true,
		'show_dots'      => true,
		'pause_on_hover' => true,
	);
}

/**
 * @param array<string,mixed> $settings
 *
 * @return array{interval:int,speed:int,autoplay:bool,show_dots:bool,pause_on_hover:bool}
 */
function contorno_slider_sanitize_settings( array $settings ): array {
	$defaults = contorno_slider_default_settings();

	// Intervalo entre 2s e 20s; velocidade da transicao entre 0,1s e 3s.
	$interval = min( 20000, max( 2000, (int) ( $settings['interval'] ?? $defaults['interval'] ) ) );
	$speed    = min( 3000, max( 100, (int) ( $settings['speed'] ?? $defaults['speed'] ) ) );

	return array(
		'interval'       => $interval,
		'speed'          => $speed,
		'autoplay'       => ! empty( $settings['autoplay'] ),
		'show_dots'      => ! empty( $settings['show_dots'] ),
		'pause_on_hover' => ! empty( $settings['pause_on_hover'] ),
	);
}

/**
 * @param array<string,mixed> $item
 *
 * @return array{image_desktop_id:int,image_mobile_id:int,link_url:string,link_target:string,order:int,active:bool}
 */
function contorno_slider_sanitize_slide( array $item ): array {
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
 * @param array<string,mixed> $slider
 *
 * @return array{id:string,name:string,active:bool,settings:array<string,mixed>,slides:array<int,array<string,mixed>>}
 */
function contorno_slider_sanitize( array $slider, string $fallback_id = '' ): array {
	$id = sanitize_key( (string) ( $slider['id'] ?? $fallback_id ) );

	if ( '' === $id ) {
		$id = 'slider-' . substr( md5( wp_generate_uuid4() ), 0, 8 );
	}

	$name = sanitize_text_field( (string) ( $slider['name'] ?? '' ) );

	if ( '' === $name ) {
		$name = __( 'Slider sem nome', 'contorno' );
	}

	$slides = array();
	foreach ( (array) ( $slider['slides'] ?? array() ) as $slide ) {
		if ( is_array( $slide ) ) {
			$slides[] = contorno_slider_sanitize_slide( $slide );
		}
	}

	usort( $slides, static fn ( array $a, array $b ): int => $a['order'] <=> $b['order'] );

	return array(
		'id'       => $id,
		'name'     => $name,
		'active'   => ! empty( $slider['active'] ),
		'settings' => contorno_slider_sanitize_settings( is_array( $slider['settings'] ?? null ) ? (array) $slider['settings'] : array() ),
		'slides'   => $slides,
	);
}

/**
 * Converte o slider unico antigo (CONTORNO_SLIDER_LEGACY_OPTION) no primeiro
 * slider desta lista, uma unica vez. Idempotente: sem efeito depois da
 * primeira execucao (flag em option propria), e nunca sobrescreve um slider
 * "home-principal" que ja exista.
 */
function contorno_sliders_maybe_migrate_legacy(): void {
	if ( get_option( CONTORNO_SLIDERS_MIGRATED_FLAG, false ) ) {
		return;
	}

	$legacy = get_option( CONTORNO_SLIDER_LEGACY_OPTION, null );

	$stored  = get_option( CONTORNO_SLIDERS_OPTION, null );
	$sliders = is_array( $stored ) && is_array( $stored['sliders'] ?? null ) ? $stored['sliders'] : array();

	if ( is_array( $legacy ) && ! isset( $sliders[ CONTORNO_SLIDER_LEGACY_ID ] ) ) {
		$sliders[ CONTORNO_SLIDER_LEGACY_ID ] = array(
			'id'       => CONTORNO_SLIDER_LEGACY_ID,
			'name'     => __( 'Home principal', 'contorno' ),
			'active'   => true,
			'settings' => is_array( $legacy['settings'] ?? null ) ? (array) $legacy['settings'] : contorno_slider_default_settings(),
			'slides'   => is_array( $legacy['slides'] ?? null ) ? (array) $legacy['slides'] : array(),
		);

		update_option(
			CONTORNO_SLIDERS_OPTION,
			array(
				'version' => 1,
				'sliders' => $sliders,
			),
			true
		);
	}

	update_option( CONTORNO_SLIDERS_MIGRATED_FLAG, 1, true );
}

/**
 * Todos os sliders, ja saneados — chave e o id estavel.
 *
 * @return array<string,array<string,mixed>>
 */
function contorno_sliders_data(): array {
	contorno_sliders_maybe_migrate_legacy();

	$stored = get_option( CONTORNO_SLIDERS_OPTION, null );

	if ( ! is_array( $stored ) || ! is_array( $stored['sliders'] ?? null ) ) {
		return array();
	}

	$out = array();
	foreach ( $stored['sliders'] as $slug => $slider ) {
		if ( is_array( $slider ) ) {
			$clean               = contorno_slider_sanitize( $slider, (string) $slug );
			$out[ $clean['id'] ] = $clean;
		}
	}

	return $out;
}

/**
 * @return array<string,mixed>|null
 */
function contorno_slider_get( string $id ): ?array {
	return contorno_sliders_data()[ $id ] ?? null;
}

/**
 * @param array<string,array<string,mixed>> $sliders Chave = id.
 */
function contorno_sliders_save( array $sliders ): bool {
	$clean = array();

	foreach ( $sliders as $slug => $slider ) {
		$entry               = contorno_slider_sanitize( is_array( $slider ) ? $slider : array(), (string) $slug );
		$clean[ $entry['id'] ] = $entry;
	}

	return (bool) update_option(
		CONTORNO_SLIDERS_OPTION,
		array(
			'version' => 1,
			'sliders' => $clean,
		),
		true
	);
}

/**
 * Slides ativos de um slider, na ordem de exibicao — o que o frontend usa.
 *
 * @param array<string,mixed> $slider
 *
 * @return array<int,array<string,mixed>>
 */
function contorno_slider_active_slides( array $slider ): array {
	return array_values(
		array_filter(
			(array) ( $slider['slides'] ?? array() ),
			static fn ( array $slide ): bool => $slide['active'] && $slide['image_desktop_id'] > 0
		)
	);
}

/**
 * Gera um id livre a partir do nome (mesma tecnica de
 * contorno_attribute_unique_key(), adaptada pra uma lista chaveada por id).
 *
 * @param array<string,array<string,mixed>> $sliders
 */
function contorno_slider_unique_id( string $name, array $sliders ): string {
	$base = sanitize_title( $name );

	if ( '' === $base ) {
		$base = 'slider';
	}

	$id    = $base;
	$index = 2;

	while ( isset( $sliders[ $id ] ) ) {
		$id = $base . '-' . $index;
		++$index;
	}

	return $id;
}

/**
 * Quantas paginas/posts publicados referenciam este slider pelo shortcode
 * (WPBakery grava o mesmo shortcode) — usado so pra avisar antes de
 * excluir, nunca pra bloquear de verdade.
 */
function contorno_slider_usage_count( string $id ): int {
	global $wpdb;

	$like = '%[contorno_slider%' . $wpdb->esc_like( 'id="' . $id . '"' ) . '%';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status NOT IN ( 'trash', 'auto-draft' ) AND post_content LIKE %s",
			$like
		)
	);
}
