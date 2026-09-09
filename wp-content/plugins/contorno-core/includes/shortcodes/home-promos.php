<?php
/** Blocos da home, compartilhados entre shortcodes e o conteudo ja migrado. */
declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function contorno_home_app( array $atts = array() ): string {
	$a = shortcode_atts( array( 'google_play' => '', 'app_store' => '' ), $atts, 'contorno_app' );
	ob_start();
	require CONTORNO_CORE_DIR . 'templates/home-app.php';
	return (string) ob_get_clean();
}

function contorno_home_prime( array $atts = array() ): string {
	$a = shortcode_atts( array( 'image' => '', 'image_alt' => 'Máquina Panatta de glúteos — equipamento premium CTN Contorno', 'cta_url' => '', 'cta_label' => 'Conheça as CTNs' ), $atts, 'contorno_ctn_prime' );
	$image = contorno_attr_image( $a['image'], 'contorno-hero' );
	if ( '' === $image ) {
		$image = contorno_asset_url( '/ctn/castelo/gallery-05.jpg' );
	}
	$url = '' !== trim( (string) $a['cta_url'] ) ? (string) $a['cta_url'] : home_url( '/ctn/' );
	ob_start();
	require CONTORNO_CORE_DIR . 'templates/home-prime.php';
	return (string) ob_get_clean();
}

contorno_add_shortcode( 'contorno_app', static fn ( array|string $atts ): string => contorno_home_app( (array) $atts ) );
contorno_add_shortcode( 'contorno_ctn_prime', static fn ( array|string $atts ): string => contorno_home_prime( (array) $atts ) );

/* Adapta apenas o CTN Prime da home existente, sem regravar o banco de dados.
 * O shortcode explicito contorno_app permite ao editor controlar sua posicao.
 */
add_filter( 'do_shortcode_tag', static function ( string $output, string $tag, array|string $atts ): string {
	if ( ! is_front_page() || 'contorno_puv' !== $tag ) {
		return $output;
	}
	$atts = (array) $atts;
	$title = trim( wp_strip_all_tags( (string) ( $atts['title'] ?? '' ) ) );
	if ( 'CTN Prime' !== $title ) {
		return $output;
	}
	$content = (string) get_post_field( 'post_content', get_queried_object_id() );
	return contorno_home_prime( $atts ) . ( has_shortcode( $content, 'contorno_app' ) ? '' : contorno_home_app() );
}, 10, 3 );
