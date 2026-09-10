<?php
/**
 * Plugin Name:       Contorno Core
 * Plugin URI:        https://contornodocorpo.com.br
 * Description:       Infraestrutura funcional da Academia Contorno do Corpo: CPTs Unidade e CTN, campos estruturados, planos, pre-venda, integracao de aulas coletivas (EVO/W12), SEO, shortcodes e elementos personalizados do WPBakery Page Builder. Independe do tema ativo.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Conecta Digital
 * License:           GPLv2 or later
 * Text Domain:       contorno
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONTORNO_CORE_VERSION', '0.1.0' );
define( 'CONTORNO_CORE_FILE', __FILE__ );
define( 'CONTORNO_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTORNO_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Dominio canonico oficial. Espelha src/lib/site-url.ts do projeto React.
 * Nunca apontar para renatoassis.com.br nem para qualquer branding anterior.
 */
if ( ! defined( 'CONTORNO_CANONICAL_URL' ) ) {
	define( 'CONTORNO_CANONICAL_URL', 'https://contornodocorpo.com.br' );
}

/**
 * Versao por filemtime — cache-busting sem pipeline de build.
 */
function contorno_core_asset_version( string $relative_path ): string {
	$absolute = CONTORNO_CORE_DIR . ltrim( $relative_path, '/' );
	$mtime    = is_readable( $absolute ) ? (string) filemtime( $absolute ) : '';

	return '' !== $mtime ? CONTORNO_CORE_VERSION . '.' . $mtime : CONTORNO_CORE_VERSION;
}

function contorno_core_url( string $relative_path ): string {
	return CONTORNO_CORE_URL . ltrim( $relative_path, '/' );
}

$contorno_core_modules = array(
	'includes/brand.php',
	'includes/post-types.php',
	'includes/meta/registry.php',
	'includes/meta/fields.php',
	'includes/meta/metaboxes.php',
	'includes/data/evo.php',
	'includes/data/presale.php',
	'includes/data/units.php',
	'includes/data/ctn.php',
	'includes/helpers.php',
	'includes/assets.php',
	'includes/seo.php',
	'includes/forms.php',
	'includes/redirects.php',
	'includes/shortcodes/loader.php',
	'includes/builder/wpbakery.php',
	'includes/builder/wpbakery-phase2.php',
	'includes/admin/migration-page.php',
	'includes/cli/commands.php',
);

foreach ( $contorno_core_modules as $contorno_core_module ) {
	$contorno_core_path = CONTORNO_CORE_DIR . $contorno_core_module;
	if ( is_readable( $contorno_core_path ) ) {
		require_once $contorno_core_path;
	}
}

unset( $contorno_core_module, $contorno_core_path, $contorno_core_modules );

/**
 * Rewrites dos CPTs precisam ser gravados na ativacao.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		if ( function_exists( 'contorno_register_post_types' ) ) {
			contorno_register_post_types();
		}
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
