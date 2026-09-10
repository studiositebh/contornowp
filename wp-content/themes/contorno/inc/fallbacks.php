<?php
/**
 * Rede de seguranca: o tema nao pode derrubar o site publico se o plugin
 * Contorno Core estiver desativado.
 *
 * Define versoes neutras das funcoes do plugin usadas pelos templates.
 * Quando o plugin esta ativo, nada aqui e definido.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'CONTORNO_CORE_VERSION' ) ) {
	return;
}

if ( ! defined( 'CONTORNO_CPT_UNIT' ) ) {
	define( 'CONTORNO_CPT_UNIT', 'unidade' );
}

if ( ! defined( 'CONTORNO_CPT_CTN' ) ) {
	define( 'CONTORNO_CPT_CTN', 'ctn' );
}

if ( ! defined( 'CONTORNO_CTN_TAGLINE' ) ) {
	define( 'CONTORNO_CTN_TAGLINE', 'YOUR ONLY LIMIT IS YOU' );
}

if ( ! function_exists( 'contorno_is_ctn_context' ) ) {
	function contorno_is_ctn_context(): bool {
		return false;
	}
}

if ( ! function_exists( 'contorno_show_site_header' ) ) {
	function contorno_show_site_header(): bool {
		return true;
	}
}

if ( ! function_exists( 'contorno_button' ) ) {
	function contorno_button( string $label, string $url, string $variant = 'primary', array $args = array() ): string {
		unset( $variant, $args );

		if ( '' === trim( $label ) || '' === trim( $url ) ) {
			return '';
		}

		return sprintf( '<a class="contorno-btn contorno-btn--primary" href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
	}
}

if ( ! function_exists( 'contorno_brand' ) ) {
	function contorno_brand(): array {
		return array(
			'long_name' => get_bloginfo( 'name' ),
			'name'      => get_bloginfo( 'name' ),
		);
	}
}

if ( ! function_exists( 'contorno_brand_get' ) ) {
	function contorno_brand_get( string $key, string $default = '' ): string {
		$brand = contorno_brand();

		return isset( $brand[ $key ] ) ? (string) $brand[ $key ] : $default;
	}
}

if ( ! function_exists( 'contorno_asset_url' ) ) {
	function contorno_asset_url( string $path ): string {
		unset( $path );

		return '';
	}
}

if ( ! function_exists( 'contorno_whatsapp_link' ) ) {
	function contorno_whatsapp_link( string $number = '', string $message = '' ): string {
		unset( $number, $message );

		return '';
	}
}

if ( ! function_exists( 'contorno_field_text' ) ) {
	function contorno_field_text( string $name, ?int $post_id = null, string $default = '' ): string {
		unset( $name, $post_id );

		return $default;
	}
}

if ( ! function_exists( 'contorno_render_editorial_slot' ) ) {
	function contorno_render_editorial_slot( int $post_id, string $slot ): string {
		unset( $post_id, $slot );

		return '';
	}
}
