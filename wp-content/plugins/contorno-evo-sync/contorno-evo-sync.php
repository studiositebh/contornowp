<?php
/**
 * Plugin Name:       Contorno EVO Sync
 * Plugin URI:        https://contornodocorpo.com.br
 * Description:       Sincroniza os planos (memberships) do EVO com as unidades e CTNs do site. O EVO manda nos dados comerciais (nome, preço, descrição, link de venda, duração, parcelas, status); o WordPress continua mandando na apresentação (ordem, destaque, selo, benefícios). Dados persistidos localmente — a API nunca é consultada por visitante.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  contorno-core
 * Author:            Conecta Digital
 * License:           GPLv2 or later
 * Text Domain:       contorno-evo
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONTORNO_EVO_VERSION', '1.0.0' );
define( 'CONTORNO_EVO_FILE', __FILE__ );
define( 'CONTORNO_EVO_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTORNO_EVO_URL', plugin_dir_url( __FILE__ ) );

/** Base oficial da EVO Integração API (docs: api.abcevo.com). */
define( 'CONTORNO_EVO_DEFAULT_BASE_URL', 'https://evo-integracao-api.w12app.com.br' );

/** Nome do evento de cron da reconciliacao periodica. */
define( 'CONTORNO_EVO_CRON_HOOK', 'contorno_evo_sync_cron' );

/*
 * Metas por unidade/CTN (todas prefixadas com "_" = ocultas do Custom Fields).
 * Vivem fora do registro de campos do contorno-core de proposito: o
 * importador do dataset sobrescreve os campos do registro a cada migracao e
 * esses dados precisam sobreviver a isso.
 */
define( 'CONTORNO_EVO_META_BRANCH', '_contorno_evo_sync_branch' ); // != _contorno_evo_branch_id (campo do core, sobrescrito pelo importador)
define( 'CONTORNO_EVO_META_MEMBERSHIPS', '_contorno_evo_memberships' );
define( 'CONTORNO_EVO_META_SNAPSHOT', '_contorno_evo_snapshot' );
define( 'CONTORNO_EVO_META_LINKS', '_contorno_evo_links' );
define( 'CONTORNO_EVO_META_LAST_SYNC', '_contorno_evo_last_sync' );

foreach (
	array(
		'includes/class-crypto.php',
		'includes/class-settings.php',
		'includes/class-log.php',
		'includes/class-client.php',
		'includes/class-mapping.php',
		'includes/class-sync.php',
		'includes/class-frontend.php',
		'includes/class-cron.php',
		'includes/class-admin.php',
		'includes/class-cli.php',
	) as $contorno_evo_module
) {
	require_once CONTORNO_EVO_DIR . $contorno_evo_module;
}
unset( $contorno_evo_module );

/**
 * O plugin depende dos helpers do contorno-core (CPTs, campos, planos).
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! defined( 'CONTORNO_CPT_UNIT' ) || ! function_exists( 'contorno_field_list' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Contorno EVO Sync precisa do plugin Contorno Core ativo.', 'contorno-evo' ) . '</p></div>';
				}
			);

			return;
		}

		Contorno_Evo_Frontend::boot();
		Contorno_Evo_Cron::boot();
		Contorno_Evo_Admin::boot();
	},
	20
);

register_activation_hook(
	__FILE__,
	static function (): void {
		Contorno_Evo_Settings::ensure_defaults();
		Contorno_Evo_Cron::schedule();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		Contorno_Evo_Cron::unschedule();
	}
);
