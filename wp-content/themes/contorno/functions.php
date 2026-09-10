<?php
/**
 * Bootstrap do tema Contorno do Corpo.
 *
 * CAMADA DE DESIGN. O tema cuida de design system, header, footer, tipografia,
 * cores, containers, breakpoints, templates e responsividade.
 *
 * Toda a funcionalidade estrutural (CPTs Unidade/CTN, campos, planos,
 * pre-venda, aulas coletivas, SEO, shortcodes, elementos do WPBakery) vive no
 * plugin contorno-core, para sobreviver a uma troca de tema.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONTORNO_THEME_VERSION', '0.1.0' );
define( 'CONTORNO_THEME_DIR', get_template_directory() );
define( 'CONTORNO_THEME_URI', get_template_directory_uri() );

require_once CONTORNO_THEME_DIR . '/inc/fallbacks.php';
require_once CONTORNO_THEME_DIR . '/inc/setup.php';
require_once CONTORNO_THEME_DIR . '/inc/assets.php';
require_once CONTORNO_THEME_DIR . '/inc/template-tags.php';
require_once CONTORNO_THEME_DIR . '/inc/page-templates.php';

/**
 * O tema depende do Contorno Core. Sem ele, avisa no painel em vez de
 * quebrar a tela do cliente com fatal error.
 */
add_action(
	'admin_notices',
	static function (): void {
		if ( defined( 'CONTORNO_CORE_VERSION' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Contorno do Corpo:', 'contorno' ),
			esc_html__( 'o plugin Contorno Core está inativo. Unidades, CTNs, planos e os elementos do WPBakery não vão funcionar até ativá-lo.', 'contorno' )
		);
	}
);
