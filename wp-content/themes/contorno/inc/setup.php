<?php
/**
 * Suporte do tema, menus e image sizes.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'contorno', CONTORNO_THEME_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 96,
				'width'       => 320,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
		);

		// Paleta do editor espelhando os tokens de src/styles.css do React.
		add_theme_support(
			'editor-color-palette',
			array(
				array(
					'name'  => __( 'Turquesa', 'contorno' ),
					'slug'  => 'brand',
					'color' => '#10B8B1',
				),
				array(
					'name'  => __( 'Turquesa escuro', 'contorno' ),
					'slug'  => 'brand-ink',
					'color' => '#018075',
				),
				array(
					'name'  => __( 'Turquesa profundo', 'contorno' ),
					'slug'  => 'brand-deep',
					'color' => '#004145',
				),
				array(
					'name'  => __( 'Turquesa brilhante', 'contorno' ),
					'slug'  => 'brand-bright',
					'color' => '#04BFB6',
				),
				array(
					'name'  => __( 'Rosa premium', 'contorno' ),
					'slug'  => 'brand-pink',
					'color' => '#D72364',
				),
				array(
					'name'  => __( 'Grafite', 'contorno' ),
					'slug'  => 'graphite',
					'color' => '#000000',
				),
				array(
					'name'  => __( 'Areia', 'contorno' ),
					'slug'  => 'sand',
					'color' => '#F0F5F9',
				),
				array(
					'name'  => __( 'Marfim', 'contorno' ),
					'slug'  => 'ivory',
					'color' => '#FFFFFF',
				),
			)
		);
		add_theme_support( 'disable-custom-colors' );

		register_nav_menus(
			array(
				'primary'      => __( 'Menu principal (header institucional)', 'contorno' ),
				'footer_units' => __( 'Rodape - coluna Unidades', 'contorno' ),
				'footer_inst'  => __( 'Rodape - coluna Institucional', 'contorno' ),
				'footer_legal' => __( 'Rodape - links legais', 'contorno' ),
			)
		);

		// Tamanhos usados pelos cards/galerias reproduzidos do React.
		add_image_size( 'contorno-card', 720, 480, true );
		add_image_size( 'contorno-hero', 1920, 1080, true );
		add_image_size( 'contorno-gallery', 1200, 800, true );
		add_image_size( 'contorno-vertical', 720, 1280, true );
	}
);

/**
 * Largura de conteudo alinhada ao container do React (--max-width-site: 90rem).
 */
add_action(
	'after_setup_theme',
	static function (): void {
		if ( ! isset( $GLOBALS['content_width'] ) ) {
			$GLOBALS['content_width'] = 1440;
		}
	},
	0
);
