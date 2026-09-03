<?php
/**
 * Tags de template do tema (apresentacao).
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logo do site. Usa o logo customizado do WordPress quando definido,
 * caindo no asset oficial da marca.
 */
function contorno_render_logo( string $variant = 'default' ): void {
	if ( 'default' === $variant && has_custom_logo() ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		echo wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'site-logo' ) );

		return;
	}

	$key = 'light' === $variant ? 'logo_light' : 'logo';
	$src = function_exists( 'contorno_asset_url' ) ? contorno_asset_url( contorno_brand_get( $key ) ) : '';

	if ( '' === $src ) {
		printf( '<span class="site-logo site-logo--text">%s</span>', esc_html( get_bloginfo( 'name' ) ) );

		return;
	}

	printf(
		'<img class="site-logo site-logo--%s" src="%s" alt="%s" width="180" height="48" decoding="async" />',
		esc_attr( sanitize_html_class( $variant ) ),
		esc_url( $src ),
		esc_attr( function_exists( 'contorno_brand_get' ) ? contorno_brand_get( 'long_name' ) : get_bloginfo( 'name' ) )
	);
}

/**
 * Menu de emergencia: enquanto o cliente nao monta o menu em Aparencia >
 * Menus, o header mostra os destinos reais do site.
 */
function contorno_default_nav_fallback(): void {
	$links = array(
		'/unidades/'     => __( 'Unidades', 'contorno' ),
		'/ctn/'          => __( 'CTN', 'contorno' ),
		'/sobre/'        => __( 'Sobre', 'contorno' ),
		'/blog/'         => __( 'Blog', 'contorno' ),
		'/fale-conosco/' => __( 'Fale Conosco', 'contorno' ),
	);

	echo '<ul class="site-header__menu">';
	foreach ( $links as $path => $label ) {
		printf(
			'<li><a href="%s"><span class="site-nav-link">%s</span></a></li>',
			esc_url( home_url( $path ) ),
			esc_html( $label )
		);
	}
	echo '</ul>';
}

/**
 * URL de matricula. Pagina /matricula quando existir; senao o arquivo de
 * unidades, para o visitante escolher a academia.
 */
function contorno_enrollment_url(): string {
	$page = get_page_by_path( 'matricula' );

	if ( $page instanceof WP_Post ) {
		return (string) get_permalink( $page );
	}

	$archive = get_post_type_archive_link( CONTORNO_CPT_UNIT );

	return is_string( $archive ) ? $archive : home_url( '/' );
}

/**
 * Renderiza o conteudo editorial da entidade num ponto do template.
 *
 * Ponte para o plugin: se o Contorno Core estiver inativo, nao quebra.
 */
function contorno_editorial_slot( string $slot, ?int $post_id = null ): void {
	if ( ! function_exists( 'contorno_render_editorial_slot' ) ) {
		return;
	}

	$post_id = $post_id ?: (int) get_the_ID();

	if ( ! $post_id ) {
		return;
	}

	echo contorno_render_editorial_slot( $post_id, $slot ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passa por the_content.
}

/**
 * A pagina esconde o titulo automatico?
 *
 * Quando o conteudo comeca com um Hero do Contorno, o titulo do WordPress
 * seria redundante — o proprio Hero ja traz o H1.
 */
function contorno_page_hides_title(): bool {
	$content = (string) get_post_field( 'post_content', get_the_ID() );

	$starts_with_hero = (bool) preg_match( '/^\s*(\[vc_row[^\]]*\]\s*)*(\[vc_column[^\]]*\]\s*)*\[(contorno_hero|ctn_hero)\b/', $content );

	return (bool) apply_filters( 'contorno_page_hides_title', $starts_with_hero );
}

/**
 * Executa um shortcode do Contorno Core com seguranca.
 *
 * @param array<string,string|int> $atts
 */
function contorno_component( string $tag, array $atts = array() ): void {
	if ( ! shortcode_exists( $tag ) ) {
		return;
	}

	$pairs = '';
	foreach ( $atts as $key => $value ) {
		$pairs .= sprintf( ' %s="%s"', sanitize_key( (string) $key ), esc_attr( (string) $value ) );
	}

	echo do_shortcode( '[' . $tag . $pairs . ']' );
}
