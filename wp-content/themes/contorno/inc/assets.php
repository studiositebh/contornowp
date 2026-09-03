<?php
/**
 * Enfileiramento do CSS do design system.
 *
 * Sem build step: CSS nativo com custom properties. Isso mantem o tema
 * editavel no servidor e evita depender de Node em producao.
 *
 * O CSS/JS dos componentes funcionais e responsabilidade do plugin
 * contorno-core (assets/css/components.css).
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function contorno_asset_version( string $relative_path ): string {
	$absolute = CONTORNO_THEME_DIR . '/' . ltrim( $relative_path, '/' );
	$mtime    = is_readable( $absolute ) ? (string) filemtime( $absolute ) : '';

	return '' !== $mtime ? CONTORNO_THEME_VERSION . '.' . $mtime : CONTORNO_THEME_VERSION;
}

function contorno_theme_uri( string $relative_path ): string {
	return CONTORNO_THEME_URI . '/' . ltrim( $relative_path, '/' );
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		// Poppins — mesma familia do React (--font-sans / --font-serif).
		wp_enqueue_style(
			'contorno-fonts',
			'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'contorno-tokens',
			contorno_theme_uri( 'assets/css/tokens.css' ),
			array( 'contorno-fonts' ),
			contorno_asset_version( 'assets/css/tokens.css' )
		);

		wp_enqueue_style(
			'contorno-site',
			contorno_theme_uri( 'assets/css/site.css' ),
			array( 'contorno-tokens' ),
			contorno_asset_version( 'assets/css/site.css' )
		);

		// Skin dark premium das CTNs: apenas onde ha CTN em tela.
		if ( function_exists( 'contorno_is_ctn_context' ) && contorno_is_ctn_context() ) {
			wp_enqueue_style(
				'contorno-ctn',
				contorno_theme_uri( 'assets/css/ctn.css' ),
				array( 'contorno-site' ),
				contorno_asset_version( 'assets/css/ctn.css' )
			);
		}

		wp_enqueue_script(
			'contorno-theme',
			contorno_theme_uri( 'assets/js/theme.js' ),
			array(),
			contorno_asset_version( 'assets/js/theme.js' ),
			true
		);
	}
);

/**
 * Tokens tambem no editor, para o cliente ver cor/tipografia reais no builder.
 */
add_action(
	'enqueue_block_editor_assets',
	static function (): void {
		wp_enqueue_style(
			'contorno-tokens-editor',
			contorno_theme_uri( 'assets/css/tokens.css' ),
			array(),
			contorno_asset_version( 'assets/css/tokens.css' )
		);
	}
);

/**
 * Tokens no editor de backend do WPBakery, para os elementos CONTORNO
 * aparecerem com a cor certa dentro do builder.
 */
add_action(
	'vc_backend_editor_enqueue_js_css',
	static function (): void {
		wp_enqueue_style(
			'contorno-tokens-vc',
			contorno_theme_uri( 'assets/css/tokens.css' ),
			array(),
			contorno_asset_version( 'assets/css/tokens.css' )
		);
	}
);
