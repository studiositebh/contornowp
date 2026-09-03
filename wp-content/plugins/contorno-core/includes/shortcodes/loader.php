<?php
/**
 * Carregador dos shortcodes e utilitarios comuns de renderizacao.
 *
 * Cada componente funcional e um shortcode. Isso e deliberado: shortcode e a
 * interface universal — funciona no WPBakery (via vc_map), no editor de
 * blocos, no editor classico e dentro de qualquer template. Se um dia o
 * builder mudar, os componentes continuam validos.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CONTORNO_CORE_DIR . 'includes/shortcodes/institutional.php';
require_once CONTORNO_CORE_DIR . 'includes/shortcodes/units.php';
require_once CONTORNO_CORE_DIR . 'includes/shortcodes/ctn.php';

/**
 * Resolve o post de contexto de um componente.
 *
 * Precedencia: atributo explicito (slug ou ID) > post da query atual.
 * Permite inserir "CTN — Planos" numa pagina institucional apontando para
 * uma CTN especifica, e ao mesmo tempo reaproveitar o mesmo componente no
 * template compartilhado sem configurar nada.
 */
function contorno_resolve_context_id( array $atts, string $post_type = '' ): int {
	$ref = trim( (string) ( $atts['unit'] ?? $atts['ctn'] ?? $atts['post'] ?? '' ) );

	if ( '' !== $ref ) {
		if ( is_numeric( $ref ) ) {
			return (int) $ref;
		}

		$post = '' !== $post_type && CONTORNO_CPT_CTN === $post_type
			? contorno_get_ctn_by_slug( $ref )
			: contorno_get_unit_by_slug( $ref );

		if ( $post instanceof WP_Post ) {
			return $post->ID;
		}

		return 0;
	}

	return (int) get_the_ID();
}

/**
 * Normaliza um atributo de imagem que pode vir como ID de anexo (WPBakery
 * attach_image) ou como caminho migrado do React.
 */
function contorno_attr_image( mixed $value, string $size = 'contorno-hero' ): string {
	if ( is_array( $value ) ) {
		$value = $value[0] ?? '';
	}

	return contorno_resolve_media( $value, $size );
}

/**
 * Decodifica um param_group do WPBakery (JSON base64) em array de linhas.
 *
 * @return array<int,array<string,mixed>>
 */
function contorno_decode_param_group( string $value ): array {
	$value = trim( $value );

	if ( '' === $value ) {
		return array();
	}

	// WPBakery entrega param_group como JSON urlencoded/base64.
	$decoded = json_decode( $value, true );

	if ( ! is_array( $decoded ) ) {
		$decoded = json_decode( (string) rawurldecode( $value ), true );
	}

	if ( ! is_array( $decoded ) ) {
		$base64 = base64_decode( $value, true );
		if ( is_string( $base64 ) ) {
			$decoded = json_decode( urldecode( $base64 ), true );
		}
	}

	return is_array( $decoded ) ? array_values( array_filter( $decoded, 'is_array' ) ) : array();
}

/**
 * Quebra um textarea "um item por linha" em array.
 *
 * @return string[]
 */
function contorno_lines_to_array( string $value ): array {
	$lines = preg_split( '/\R/', $value );

	if ( ! is_array( $lines ) ) {
		return array();
	}

	$lines = array_map( 'trim', $lines );

	return array_values( array_filter( $lines, static fn ( string $line ): bool => '' !== $line ) );
}

/**
 * Botao padrao do design system.
 */
function contorno_button( string $label, string $url, string $variant = 'primary', array $args = array() ): string {
	$label = trim( $label );
	$url   = trim( $url );

	if ( '' === $label || '' === $url ) {
		return '';
	}

	$classes = 'contorno-btn contorno-btn--' . sanitize_html_class( $variant );
	if ( ! empty( $args['class'] ) ) {
		$classes .= ' ' . (string) $args['class'];
	}

	$external = ! empty( $args['external'] ) || ! str_contains( $url, (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) && preg_match( '#^https?://#', $url );

	return sprintf(
		'<a class="%s" href="%s"%s>%s%s</a>',
		esc_attr( $classes ),
		esc_url( $url ),
		$external ? ' target="_blank" rel="noopener noreferrer"' : '',
		esc_html( $label ),
		! empty( $args['icon'] ) ? contorno_icon( (string) $args['icon'], 'contorno-btn__icon' ) : ''
	);
}

/**
 * Cabecalho de secao (eyebrow + titulo + texto) usado por varios componentes.
 */
function contorno_section_header( string $eyebrow, string $title, string $text = '', string $align = 'left' ): string {
	if ( '' === $eyebrow && '' === $title && '' === $text ) {
		return '';
	}

	$html = '<header class="contorno-section-header contorno-section-header--' . sanitize_html_class( $align ) . '">';

	if ( '' !== $eyebrow ) {
		$html .= '<p class="eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}

	if ( '' !== $title ) {
		$html .= '<h2 class="contorno-section-header__title">' . wp_kses_post( $title ) . '</h2>';
	}

	if ( '' !== $text ) {
		$html .= '<div class="contorno-section-header__text">' . wp_kses_post( wpautop( $text ) ) . '</div>';
	}

	return $html . '</header>';
}

/**
 * Envelope de secao com classes de espacamento e tom (claro/escuro/CTN).
 */
function contorno_section_open( string $name, array $args = array() ): string {
	$tone    = (string) ( $args['tone'] ?? 'light' );
	$classes = array( 'contorno-section', 'contorno-section--' . sanitize_html_class( $name ), 'is-tone-' . sanitize_html_class( $tone ) );

	if ( ! empty( $args['class'] ) ) {
		$classes[] = (string) $args['class'];
	}

	$id    = ! empty( $args['id'] ) ? ' id="' . esc_attr( (string) $args['id'] ) . '"' : '';
	$style = '';

	if ( ! empty( $args['background'] ) ) {
		$style = ' style="--contorno-section-bg:url(' . esc_url( (string) $args['background'] ) . ')"';
		$classes[] = 'has-background';
	}

	return sprintf(
		'<section class="%s"%s%s><div class="site-container">',
		esc_attr( implode( ' ', $classes ) ),
		$id,
		$style
	);
}

function contorno_section_close(): string {
	return '</div></section>';
}

/**
 * Registra um shortcode e devolve sempre string (nunca echo direto).
 */
function contorno_add_shortcode( string $tag, callable $callback ): void {
	add_shortcode( $tag, $callback );
}
