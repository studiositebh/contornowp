<?php
/**
 * Plugin Name:       Contorno EVO Sync
 * Plugin URI:        https://contornodocorpo.com.br
 * Description:       Sincroniza os planos (memberships) do EVO com as unidades e CTNs do site. O EVO manda nos dados comerciais (nome, preço, descrição, link de venda, duração, parcelas, status); o WordPress continua mandando na apresentação (ordem, destaque, selo, benefícios). Dados persistidos localmente — a API nunca é consultada por visitante.
 * Version:           1.1.0
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

define( 'CONTORNO_EVO_VERSION', '1.1.0' );
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
		'includes/class-gateway.php',
		'includes/class-activities.php',
		'includes/class-checkout.php',
		'includes/class-checkout-rest.php',
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
		Contorno_Evo_Checkout_Rest::boot();
	},
	20
);

/**
 * O checkout nativo esta ligado para esta unidade?
 *
 * Unica porta que o contorno-core (apresentacao) usa para saber se deve
 * desenhar as etapas nativas ou manter o redirecionamento atual para a EVO.
 * E uma FUNCAO, e nao uma classe, porque o core precisa poder perguntar sem
 * saber se este plugin existe:
 *
 *   if ( function_exists( 'contorno_evo_native_checkout_enabled' ) && ... )
 *
 * Plugin desativado = pergunta nem chega a ser feita = comportamento atual.
 * Nao existe caminho em que a ausencia deste plugin quebre /matricula/.
 */
function contorno_evo_native_checkout_enabled( string $slug ): bool {
	return Contorno_Evo_Settings::checkout_enabled_for( $slug );
}

/**
 * Comprovante da matricula, para a pagina de confirmacao.
 *
 * Recebe o token opaco que veio em ?ck= e devolve SO o que pode aparecer na
 * tela: unidade, plano e o numero da operacao. Nome, e-mail, CPF, idProspect
 * e idMember nao saem daqui — nem para a propria pessoa, porque a pagina de
 * confirmacao e acessivel a quem tiver o link.
 *
 * Token invalido, expirado ou de venda nao concluida devolve array vazio, e a
 * pagina de confirmacao simplesmente nao mostra o bloco.
 *
 * @return array<string,string>
 */
function contorno_evo_checkout_receipt( string $token ): array {
	$state = Contorno_Evo_Checkout::load( $token );

	if ( null === $state || 'success' !== ( $state['status'] ?? '' ) ) {
		return array();
	}

	$post = get_post( (int) $state['post_id'] );

	return array(
		'unit'  => $post instanceof WP_Post ? (string) get_the_title( $post ) : '',
		'plan'  => (string) ( $state['quote']['name'] ?? '' ),
		// idSale e o numero da propria venda de quem esta lendo: e o que o
		// atendimento pede quando a pessoa liga. Nao identifica terceiros.
		'order' => (int) ( $state['id_sale'] ?? 0 ) > 0 ? (string) $state['id_sale'] : '',
	);
}

/**
 * Nonce e rotas que o formulario nativo precisa. Nunca DNS nem token.
 *
 * @return array<string,mixed>
 */
function contorno_evo_checkout_boot_data(): array {
	return array(
		'nonce'           => wp_create_nonce( Contorno_Evo_Checkout_Rest::NONCE ),
		// Quem manda no conjunto de etapas e a configuracao da integracao, nao
		// o plugin de apresentacao: e a EVO que diria que precisa de endereco.
		'requiresAddress' => (bool) Contorno_Evo_Settings::get( 'checkout_require_address', false ),
		'routes'          => array(
			'open'     => rest_url( Contorno_Evo_Checkout_Rest::NAMESPACE . '/checkout/open' ),
			'identify' => rest_url( Contorno_Evo_Checkout_Rest::NAMESPACE . '/checkout/identify' ),
			'pay'      => rest_url( Contorno_Evo_Checkout_Rest::NAMESPACE . '/checkout/pay' ),
			'status'   => rest_url( Contorno_Evo_Checkout_Rest::NAMESPACE . '/checkout/status' ),
		),
	);
}

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
