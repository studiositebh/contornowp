<?php
/**
 * Telas administrativas (manage_options):
 *   Contorno -> Integração EVO   painel + configuracoes + sincronizar
 *   Contorno -> Unidades EVO     vinculo unidade <-> idBranch, busca de filiais
 *   Contorno -> Log EVO
 *
 * Toda acao passa por nonce + current_user_can('manage_options').
 * O token nunca e impresso: o campo e password e fica vazio; string vazia
 * preserva o token gravado.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Admin {

	public const PAGE_MAIN  = 'contorno-evo';
	public const PAGE_UNITS = 'contorno-evo-unidades';
	public const PAGE_LOG   = 'contorno-evo-log';
	public const PARENT     = 'contorno-migracao'; // menu "Contorno" do contorno-core

	public static function boot(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_contorno_evo_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_contorno_evo_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_post_contorno_evo_map', array( __CLASS__, 'handle_map' ) );
		add_action( 'admin_post_contorno_evo_rollback', array( __CLASS__, 'handle_rollback' ) );
		add_action( 'admin_post_contorno_evo_clear_log', array( __CLASS__, 'handle_clear_log' ) );
		add_action( 'wp_ajax_contorno_evo_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_contorno_evo_branches', array( __CLASS__, 'ajax_branches' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu(): void {
		$parent = menu_page_url( self::PARENT, false ) ? self::PARENT : 'options-general.php';

		add_submenu_page( $parent, __( 'Integração EVO', 'contorno-evo' ), __( 'Integração EVO', 'contorno-evo' ), 'manage_options', self::PAGE_MAIN, array( __CLASS__, 'render_main' ) );
		add_submenu_page( $parent, __( 'Unidades EVO', 'contorno-evo' ), __( 'Unidades EVO', 'contorno-evo' ), 'manage_options', self::PAGE_UNITS, array( __CLASS__, 'render_units' ) );
		add_submenu_page( $parent, __( 'Log EVO', 'contorno-evo' ), __( 'Log EVO', 'contorno-evo' ), 'manage_options', self::PAGE_LOG, array( __CLASS__, 'render_log' ) );
	}

	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'contorno-evo' ) ) {
			return;
		}

		wp_enqueue_style( 'contorno-evo-admin', CONTORNO_EVO_URL . 'assets/admin.css', array(), CONTORNO_EVO_VERSION );
		wp_enqueue_script( 'contorno-evo-admin', CONTORNO_EVO_URL . 'assets/admin.js', array(), CONTORNO_EVO_VERSION, true );
		wp_localize_script(
			'contorno-evo-admin',
			'ContornoEvo',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'contorno_evo_ajax' ),
				'i18n'  => array(
					'testing'  => __( 'Testando…', 'contorno-evo' ),
					'loading'  => __( 'Buscando filiais…', 'contorno-evo' ),
					'failed'   => __( 'Falha na requisição.', 'contorno-evo' ),
					'noResult' => __( 'Nenhuma filial devolvida pela EVO.', 'contorno-evo' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------- */

	private static function guard( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'contorno-evo' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect( string $page, array $args = array() ): void {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) ) );
		exit;
	}

	public static function handle_save(): void {
		self::guard( 'contorno_evo_save' );

		Contorno_Evo_Settings::save(
			array(
				'dns'              => wp_unslash( (string) ( $_POST['dns'] ?? '' ) ),
				'base_url'         => wp_unslash( (string) ( $_POST['base_url'] ?? '' ) ),
				'auto_sync'        => isset( $_POST['auto_sync'] ),
				'interval_minutes' => (int) ( $_POST['interval_minutes'] ?? 360 ),
				'add_new_plans'    => isset( $_POST['add_new_plans'] ),
				'hide_inactive'    => isset( $_POST['hide_inactive'] ),
				'fetch_mode'       => sanitize_key( (string) ( $_POST['fetch_mode'] ?? 'auto' ) ),

				'checkout_mode'            => sanitize_key( (string) ( $_POST['checkout_mode'] ?? 'off' ) ),
				'checkout_allowlist'       => wp_unslash( (string) ( $_POST['checkout_allowlist'] ?? '' ) ),
				'checkout_payment_card'    => (int) ( $_POST['checkout_payment_card'] ?? 0 ),
				'checkout_codes_confirmed' => isset( $_POST['checkout_codes_confirmed'] ),
				'checkout_evopay_script'   => wp_unslash( (string) ( $_POST['checkout_evopay_script'] ?? '' ) ),
				'checkout_require_address' => isset( $_POST['checkout_require_address'] ),
			)
		);

		$notice = 'saved';

		if ( isset( $_POST['clear_token'] ) ) {
			Contorno_Evo_Settings::clear_token();
			$notice = 'token_cleared';
		} elseif ( '' !== trim( (string) ( $_POST['token'] ?? '' ) ) ) {
			// Nao passa por sanitize_text_field: tokens podem ter caracteres que ele removeria.
			$ok = Contorno_Evo_Settings::save_token( wp_unslash( (string) $_POST['token'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$notice = $ok ? 'token_saved' : 'token_failed';
		}

		Contorno_Evo_Cron::reschedule();
		self::redirect( self::PAGE_MAIN, array( 'notice' => $notice ) );
	}

	public static function handle_sync(): void {
		self::guard( 'contorno_evo_sync' );

		$dry_run = ! empty( $_POST['dry_run'] );
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$summary = Contorno_Evo_Sync::run( array( 'dry_run' => $dry_run, 'post_id' => $post_id, 'trigger' => 'admin' ) );

		set_transient( 'contorno_evo_last_result_' . get_current_user_id(), $summary, 10 * MINUTE_IN_SECONDS );
		self::redirect( $post_id > 0 ? self::PAGE_UNITS : self::PAGE_MAIN, array( 'notice' => $dry_run ? 'dry_run' : 'synced' ) );
	}

	public static function handle_map(): void {
		self::guard( 'contorno_evo_map' );

		$branches = (array) ( $_POST['branch'] ?? array() );
		$saved    = 0;

		foreach ( $branches as $post_id => $value ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || ! in_array( get_post_type( $post_id ), array( CONTORNO_CPT_UNIT, CONTORNO_CPT_CTN ), true ) ) {
				continue;
			}
			$new = (int) $value;
			if ( $new !== (int) get_post_meta( $post_id, CONTORNO_EVO_META_BRANCH, true ) ) {
				Contorno_Evo_Mapping::set_branch( $post_id, $new );
				++$saved;
			}
		}

		Contorno_Evo_Log::add( 'info', sprintf( '%d vínculo(s) de filial atualizado(s) pelo painel', $saved ), array( 'action' => 'map' ) );
		self::redirect( self::PAGE_UNITS, array( 'notice' => 'mapped', 'count' => $saved ) );
	}

	public static function handle_rollback(): void {
		self::guard( 'contorno_evo_rollback' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$ok      = $post_id > 0 && Contorno_Evo_Sync::rollback( $post_id );

		self::redirect( self::PAGE_UNITS, array( 'notice' => $ok ? 'rolled_back' : 'rollback_failed' ) );
	}

	public static function handle_clear_log(): void {
		self::guard( 'contorno_evo_clear_log' );
		Contorno_Evo_Log::clear();
		self::redirect( self::PAGE_LOG, array( 'notice' => 'log_cleared' ) );
	}

	public static function ajax_test(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'contorno-evo' ) ), 403 );
		}
		check_ajax_referer( 'contorno_evo_ajax', 'nonce' );

		// Testa com o que esta no formulario (DNS e, se informado, token novo);
		// senao com o que esta gravado. O token nunca volta na resposta.
		$dns   = sanitize_text_field( wp_unslash( (string) ( $_POST['dns'] ?? '' ) ) );
		$token = (string) wp_unslash( (string) ( $_POST['token'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$base  = sanitize_text_field( wp_unslash( (string) ( $_POST['base_url'] ?? '' ) ) );

		$client = new Contorno_Evo_Client(
			'' !== $dns ? $dns : null,
			'' !== trim( $token ) ? trim( $token ) : null,
			'' !== $base ? $base : null
		);

		$result = $client->test();

		Contorno_Evo_Settings::update_status( array( 'last_test' => time(), 'last_test_ok' => $result['ok'], 'connected' => $result['ok'] ) );
		Contorno_Evo_Log::add( $result['ok'] ? 'info' : 'error', 'teste de conexão: ' . $result['message'], array( 'action' => 'test', 'http' => $result['http'] ) );

		if ( $result['ok'] ) {
			wp_send_json_success( array( 'message' => '✓ ' . $result['message'] ) );
		}

		wp_send_json_success( array( 'message' => $result['message'], 'failed' => true ) );
	}

	public static function ajax_branches(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'contorno-evo' ) ), 403 );
		}
		check_ajax_referer( 'contorno_evo_ajax', 'nonce' );

		$client = new Contorno_Evo_Client();
		$result = $client->branches();

		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}

		wp_send_json_success( array( 'branches' => $result['branches'] ) );
	}

	/* ---------------------------------------------------------------
	 * Telas
	 * ------------------------------------------------------------- */

	private static function notice(): void {
		$key = isset( $_GET['notice'] ) ? sanitize_key( (string) $_GET['notice'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$map = array(
			'saved'           => array( 'success', __( 'Configurações salvas.', 'contorno-evo' ) ),
			'token_saved'     => array( 'success', __( 'Configurações e token salvos.', 'contorno-evo' ) ),
			'token_cleared'   => array( 'warning', __( 'Token removido.', 'contorno-evo' ) ),
			'token_failed'    => array( 'error', __( 'Não foi possível proteger o token: nem libsodium nem OpenSSL estão disponíveis no PHP.', 'contorno-evo' ) ),
			'synced'          => array( 'success', __( 'Sincronização executada.', 'contorno-evo' ) ),
			'dry_run'         => array( 'info', __( 'Simulação concluída — nada foi gravado.', 'contorno-evo' ) ),
			'mapped'          => array( 'success', __( 'Vínculos de filial salvos.', 'contorno-evo' ) ),
			'rolled_back'     => array( 'warning', __( 'Unidade restaurada para a versão anterior.', 'contorno-evo' ) ),
			'rollback_failed' => array( 'error', __( 'Não há versão anterior para restaurar.', 'contorno-evo' ) ),
			'log_cleared'     => array( 'success', __( 'Log limpo.', 'contorno-evo' ) ),
		);

		if ( isset( $map[ $key ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $key ][0] ), esc_html( $map[ $key ][1] ) );
		}
	}

	private static function tabs( string $current ): void {
		$tabs = array(
			self::PAGE_MAIN  => __( 'Painel e configurações', 'contorno-evo' ),
			self::PAGE_UNITS => __( 'Unidades', 'contorno-evo' ),
			self::PAGE_LOG   => __( 'Log', 'contorno-evo' ),
		);
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			printf( '<a class="nav-tab%s" href="%s">%s</a>', $slug === $current ? ' nav-tab-active' : '', esc_url( admin_url( 'admin.php?page=' . $slug ) ), esc_html( $label ) );
		}
		echo '</nav>';
	}

	public static function render_main(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'contorno-evo' ) );
		}

		$settings   = Contorno_Evo_Settings::all();
		$status     = Contorno_Evo_Settings::status();
		$from_const = Contorno_Evo_Settings::credentials_from_constants();
		$has_token  = Contorno_Evo_Settings::has_token();
		$result     = get_transient( 'contorno_evo_last_result_' . get_current_user_id() );
		$stats      = self::stats();
		?>
		<div class="wrap contorno-evo">
			<h1><?php esc_html_e( 'Integração EVO', 'contorno-evo' ); ?></h1>
			<?php self::tabs( self::PAGE_MAIN ); ?>
			<?php self::notice(); ?>

			<?php if ( ! Contorno_Evo_Crypto::salts_are_configured() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Os salts do wp-config.php (AUTH_KEY, SECURE_AUTH_KEY, LOGGED_IN_KEY, NONCE_KEY) não estão definidos. O token é cifrado com uma chave derivada deles — defina-os para a proteção valer.', 'contorno-evo' ); ?></p></div>
			<?php endif; ?>
			<?php if ( Contorno_Evo_Settings::token_is_unreadable() ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'O token gravado foi cifrado com salts anteriores e não pode ser lido. Informe o token novamente.', 'contorno-evo' ); ?></p></div>
			<?php endif; ?>

			<div class="contorno-evo__dash">
				<div class="contorno-evo__card">
					<span class="contorno-evo__label"><?php esc_html_e( 'EVO API', 'contorno-evo' ); ?></span>
					<strong class="contorno-evo__value">
						<?php if ( ! Contorno_Evo_Settings::has_credentials() ) : ?>
							<span class="contorno-evo__dot is-off"></span><?php esc_html_e( 'Não configurada', 'contorno-evo' ); ?>
						<?php elseif ( ! empty( $status['connected'] ) ) : ?>
							<span class="contorno-evo__dot is-on"></span><?php esc_html_e( 'Conectado', 'contorno-evo' ); ?>
						<?php else : ?>
							<span class="contorno-evo__dot is-warn"></span><?php esc_html_e( 'Sem teste bem-sucedido', 'contorno-evo' ); ?>
						<?php endif; ?>
					</strong>
					<?php if ( ! empty( $status['last_test'] ) ) : ?>
						<span class="contorno-evo__hint"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Último teste: %s', 'contorno-evo' ), wp_date( 'd/m/Y H:i', (int) $status['last_test'] ) ) ); ?></span>
					<?php endif; ?>
				</div>
				<div class="contorno-evo__card">
					<span class="contorno-evo__label"><?php esc_html_e( 'Última sincronização', 'contorno-evo' ); ?></span>
					<strong class="contorno-evo__value"><?php echo esc_html( ! empty( $status['last_sync'] ) ? wp_date( 'd/m/Y H:i', (int) $status['last_sync'] ) : '—' ); ?></strong>
					<span class="contorno-evo__hint">
						<?php
						$next = Contorno_Evo_Cron::next_run();
						echo esc_html( $next > 0 ? sprintf( /* translators: %s: date */ __( 'Próxima automática: %s', 'contorno-evo' ), wp_date( 'd/m/Y H:i', $next ) ) : __( 'Sincronização automática desligada', 'contorno-evo' ) );
						?>
					</span>
				</div>
				<div class="contorno-evo__card">
					<span class="contorno-evo__label"><?php esc_html_e( 'Unidades', 'contorno-evo' ); ?></span>
					<strong class="contorno-evo__value"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d vinculadas', 'contorno-evo' ), $stats['mapped'] ) ); ?></strong>
					<span class="contorno-evo__hint"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d pendentes', 'contorno-evo' ), $stats['unmapped'] ) ); ?></span>
				</div>
				<div class="contorno-evo__card">
					<span class="contorno-evo__label"><?php esc_html_e( 'Planos EVO', 'contorno-evo' ); ?></span>
					<strong class="contorno-evo__value"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d ativos', 'contorno-evo' ), $stats['active'] ) ); ?></strong>
					<span class="contorno-evo__hint"><?php echo esc_html( sprintf( /* translators: 1: inactive, 2: missing */ __( '%1$d inativos · %2$d ausentes', 'contorno-evo' ), $stats['inactive'], $stats['missing'] ) ); ?></span>
				</div>
				<?php if ( ! empty( $status['last_summary'] ) && is_array( $status['last_summary'] ) ) : ?>
					<?php $s = $status['last_summary']; ?>
					<div class="contorno-evo__card">
						<span class="contorno-evo__label"><?php esc_html_e( 'Última execução', 'contorno-evo' ); ?></span>
						<strong class="contorno-evo__value"><?php echo esc_html( sprintf( '%d %s', (int) ( $s['updated'] ?? 0 ), __( 'atualizados', 'contorno-evo' ) ) ); ?></strong>
						<span class="contorno-evo__hint"><?php echo esc_html( sprintf( '%d novos · %d sem alteração · %d erros', (int) ( $s['created'] ?? 0 ), (int) ( $s['unchanged'] ?? 0 ), (int) ( $s['errors'] ?? 0 ) ) ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( is_array( $result ) ) : ?>
				<?php self::render_result( $result ); ?>
				<?php delete_transient( 'contorno_evo_last_result_' . get_current_user_id() ); ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Configurações', 'contorno-evo' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off" id="contorno-evo-settings">
				<?php wp_nonce_field( 'contorno_evo_save' ); ?>
				<input type="hidden" name="action" value="contorno_evo_save" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="contorno-evo-dns"><?php esc_html_e( 'DNS / usuário EVO', 'contorno-evo' ); ?></label></th>
						<td>
							<input type="text" id="contorno-evo-dns" name="dns" class="regular-text" value="<?php echo esc_attr( $from_const ? Contorno_Evo_Settings::dns() : (string) $settings['dns'] ); ?>" <?php disabled( $from_const ); ?> autocomplete="off" />
							<p class="description"><?php esc_html_e( 'O "DNS" fornecido pela EVO ao criar o token (Configurações → Integrações → API → Novo token).', 'contorno-evo' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="contorno-evo-token"><?php esc_html_e( 'Token da API EVO', 'contorno-evo' ); ?></label></th>
						<td>
							<?php if ( $from_const ) : ?>
								<code>••••••••••••••••</code>
								<p class="description"><?php esc_html_e( 'Definido por constante no wp-config.php (CONTORNO_EVO_TOKEN). Tem precedência sobre o banco.', 'contorno-evo' ); ?></p>
							<?php else : ?>
								<?php if ( $has_token ) : ?>
									<p><code><?php echo esc_html( Contorno_Evo_Settings::token_mask() ); ?></code> <button type="button" class="button-link" data-evo-change-token><?php esc_html_e( 'Alterar token', 'contorno-evo' ); ?></button></p>
								<?php endif; ?>
								<div <?php echo $has_token ? 'hidden' : ''; ?> data-evo-token-field>
									<input type="password" id="contorno-evo-token" name="token" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $has_token ? esc_attr__( 'Cole o novo token (deixe vazio para manter o atual)', 'contorno-evo' ) : esc_attr__( 'Cole o token', 'contorno-evo' ); ?>" />
								</div>
								<?php if ( $has_token ) : ?>
									<p><label><input type="checkbox" name="clear_token" value="1" /> <?php esc_html_e( 'Remover o token gravado', 'contorno-evo' ); ?></label></p>
								<?php endif; ?>
								<p class="description"><?php echo esc_html( sprintf( /* translators: %s: backend */ __( 'Guardado cifrado (%s) com chave derivada dos salts do WordPress, em option sem autoload. Nunca aparece no HTML, JS, log ou REST.', 'contorno-evo' ), Contorno_Evo_Crypto::backend() ) ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="contorno-evo-base"><?php esc_html_e( 'URL base da API', 'contorno-evo' ); ?></label></th>
						<td>
							<input type="url" id="contorno-evo-base" name="base_url" class="regular-text code" value="<?php echo esc_attr( Contorno_Evo_Settings::base_url() ); ?>" <?php disabled( defined( 'CONTORNO_EVO_BASE_URL' ) ); ?> />
							<p class="description"><?php echo esc_html( sprintf( /* translators: %s: url */ __( 'Padrão: %s (documentação api.abcevo.com). Só altere se a EVO indicar outro ambiente.', 'contorno-evo' ), CONTORNO_EVO_DEFAULT_BASE_URL ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Conexão', 'contorno-evo' ); ?></th>
						<td>
							<button type="button" class="button" data-evo-test><?php esc_html_e( 'Testar conexão', 'contorno-evo' ); ?></button>
							<span class="contorno-evo__test-result" data-evo-test-result aria-live="polite"></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sincronização automática', 'contorno-evo' ); ?></th>
						<td>
							<label><input type="checkbox" name="auto_sync" value="1" <?php checked( (bool) $settings['auto_sync'] ); ?> /> <?php esc_html_e( 'Reconciliar periodicamente via WP-Cron', 'contorno-evo' ); ?></label>
							<p>
								<label for="contorno-evo-interval"><?php esc_html_e( 'Intervalo (minutos)', 'contorno-evo' ); ?></label>
								<input type="number" id="contorno-evo-interval" name="interval_minutes" min="<?php echo esc_attr( (string) Contorno_Evo_Settings::MIN_INTERVAL ); ?>" max="<?php echo esc_attr( (string) Contorno_Evo_Settings::MAX_INTERVAL ); ?>" value="<?php echo esc_attr( (string) $settings['interval_minutes'] ); ?>" class="small-text" />
							</p>
							<p class="description"><?php esc_html_e( 'A EVO não oferece webhook para alteração de planos (eventos oficiais: NewSale, CreateMember, AlterMember, NewInvoice). A reconciliação periódica é o mecanismo. Nunca consulta a API por visitante.', 'contorno-evo' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Comportamento', 'contorno-evo' ); ?></th>
						<td>
							<label><input type="checkbox" name="add_new_plans" value="1" <?php checked( (bool) $settings['add_new_plans'] ); ?> /> <?php esc_html_e( 'Exibir planos novos do EVO que ainda não têm card cadastrado na unidade', 'contorno-evo' ); ?></label><br />
							<label><input type="checkbox" name="hide_inactive" value="1" <?php checked( (bool) $settings['hide_inactive'] ); ?> /> <?php esc_html_e( 'Ocultar do site planos inativos ou ausentes no EVO (soft-disable)', 'contorno-evo' ); ?></label>
							<p>
								<label for="contorno-evo-fetch"><?php esc_html_e( 'Estratégia de leitura', 'contorno-evo' ); ?></label>
								<select id="contorno-evo-fetch" name="fetch_mode">
									<option value="auto" <?php selected( $settings['fetch_mode'], 'auto' ); ?>><?php esc_html_e( 'Automática (global; por filial quando faltar)', 'contorno-evo' ); ?></option>
									<option value="global" <?php selected( $settings['fetch_mode'], 'global' ); ?>><?php esc_html_e( 'Somente global (chave multi-filial)', 'contorno-evo' ); ?></option>
									<option value="per-branch" <?php selected( $settings['fetch_mode'], 'per-branch' ); ?>><?php esc_html_e( 'Uma requisição por filial (idBranch)', 'contorno-evo' ); ?></option>
								</select>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Checkout nativo (matrícula dentro do site)', 'contorno-evo' ); ?></h2>
				<?php $blockers = Contorno_Evo_Settings::checkout_blockers(); ?>
				<?php if ( array() !== $blockers ) : ?>
					<div class="notice notice-warning inline">
						<p><strong><?php esc_html_e( 'O checkout nativo não pode ser ligado ainda:', 'contorno-evo' ); ?></strong></p>
						<ul style="list-style:disc;margin-left:1.5em">
							<?php foreach ( $blockers as $blocker ) : ?>
								<li><?php echo esc_html( $blocker ); ?></li>
							<?php endforeach; ?>
						</ul>
						<p><?php esc_html_e( 'Enquanto isso, /matricula/ continua levando ao checkout da EVO, como hoje.', 'contorno-evo' ); ?></p>
					</div>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="contorno-evo-ck-mode"><?php esc_html_e( 'Modo', 'contorno-evo' ); ?></label></th>
						<td>
							<select id="contorno-evo-ck-mode" name="checkout_mode">
								<option value="off" <?php selected( $settings['checkout_mode'], 'off' ); ?>><?php esc_html_e( 'OFF — redireciona para o checkout da EVO (comportamento atual)', 'contorno-evo' ); ?></option>
								<option value="pilot" <?php selected( $settings['checkout_mode'], 'pilot' ); ?>><?php esc_html_e( 'PILOT — somente as unidades listadas abaixo', 'contorno-evo' ); ?></option>
								<option value="on" <?php selected( $settings['checkout_mode'], 'on' ); ?>><?php esc_html_e( 'ON — todas as unidades vinculadas a uma filial', 'contorno-evo' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Antes de sair de OFF: atualize a Política de Privacidade, que hoje afirma que /matricula/ não repassa dados. Com o checkout nativo os dados vão para a EVO.', 'contorno-evo' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="contorno-evo-ck-allow"><?php esc_html_e( 'Unidades do piloto', 'contorno-evo' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="contorno-evo-ck-allow" name="checkout_allowlist" value="<?php echo esc_attr( implode( ', ', (array) $settings['checkout_allowlist'] ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Slugs separados por vírgula. Unidade fora da lista usa o checkout da EVO, sem erro para o visitante.', 'contorno-evo' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="contorno-evo-ck-pay"><?php esc_html_e( 'Código de pagamento — cartão', 'contorno-evo' ); ?></label></th>
						<td>
							<select id="contorno-evo-ck-pay" name="checkout_payment_card">
								<option value="0" <?php selected( (int) $settings['checkout_payment_card'], 0 ); ?>><?php esc_html_e( '— não confirmado —', 'contorno-evo' ); ?></option>
								<?php foreach ( Contorno_Evo_Settings::PAYMENT_CODES as $code ) : ?>
									<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( (int) $settings['checkout_payment_card'], $code ); ?>><?php echo esc_html( (string) $code ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Campo `payment` de POST /api/v2/sales (enum EFormaPagamentoTotem). A especificação pública da EVO lista os valores válidos [1,2,3,4,5,6,7,13,14,15,16,17] mas NÃO diz o que cada número significa — a única tabela nomeada do swagger pertence a outro enum (filtro de /api/v1/receivables). Confirme com a EVO antes de vender.', 'contorno-evo' ); ?>
							</p>
							<label><input type="checkbox" name="checkout_codes_confirmed" value="1" <?php checked( (bool) $settings['checkout_codes_confirmed'] ); ?> /> <?php esc_html_e( 'Conferi este código contra a EVO real (sem isto, PILOT e ON não liberam o pagamento nativo)', 'contorno-evo' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="contorno-evo-ck-script"><?php esc_html_e( 'Componente EVO Pay', 'contorno-evo' ); ?></label></th>
						<td>
							<input type="url" class="large-text code" id="contorno-evo-ck-script" name="checkout_evopay_script" value="<?php echo esc_attr( (string) $settings['checkout_evopay_script'] ); ?>" placeholder="https://..." />
							<p class="description"><?php esc_html_e( 'URL do script que renderiza <evo-cartao> e devolve o token. Só hosts da EVO são aceitos. Não consta da especificação pública — peça na homologação.', 'contorno-evo' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Endereço', 'contorno-evo' ); ?></th>
						<td>
							<label><input type="checkbox" name="checkout_require_address" value="1" <?php checked( (bool) Contorno_Evo_Settings::get( 'checkout_require_address', false ) ); ?> /> <?php esc_html_e( 'Pedir CEP e endereço no checkout', 'contorno-evo' ); ?></label>
							<p class="description"><?php esc_html_e( 'Desligado por padrão: na especificação da EVO todo campo de endereço é opcional, tanto no prospect quanto na venda, e não há rota de leitura das regras de venda da filial. Ligue apenas se a EVO recusar a venda pedindo endereço.', 'contorno-evo' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar configurações', 'contorno-evo' ); ?></button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Sincronizar', 'contorno-evo' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Faça primeiro a simulação: ela mostra o que mudaria, sem gravar nada. A sincronização real guarda uma versão anterior por unidade (restaurável na aba Unidades).', 'contorno-evo' ); ?></p>
			<div class="contorno-evo__actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'contorno_evo_sync' ); ?>
					<input type="hidden" name="action" value="contorno_evo_sync" />
					<input type="hidden" name="dry_run" value="1" />
					<button type="submit" class="button" <?php disabled( ! Contorno_Evo_Settings::has_credentials() ); ?>><?php esc_html_e( 'Simular (dry run)', 'contorno-evo' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Executar a sincronização real agora?', 'contorno-evo' ) ); ?>');">
					<?php wp_nonce_field( 'contorno_evo_sync' ); ?>
					<input type="hidden" name="action" value="contorno_evo_sync" />
					<button type="submit" class="button button-primary" <?php disabled( ! Contorno_Evo_Settings::has_credentials() ); ?>><?php esc_html_e( 'Sincronizar agora', 'contorno-evo' ); ?></button>
				</form>
			</div>

			<h2><?php esc_html_e( 'Linha de comando', 'contorno-evo' ); ?></h2>
			<pre class="contorno-evo__pre">wp contorno evo status
wp contorno evo test
wp contorno evo branches
wp contorno evo sync --dry-run
wp contorno evo sync
wp contorno evo sync --branch=18</pre>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $r
	 */
	private static function render_result( array $r ): void {
		?>
		<div class="contorno-evo__result">
			<h2><?php echo esc_html( ! empty( $r['dry_run'] ) ? __( 'Resultado da simulação', 'contorno-evo' ) : __( 'Resultado da sincronização', 'contorno-evo' ) ); ?></h2>
			<ul class="contorno-evo__summary">
				<li><?php echo esc_html( sprintf( __( '%d unidades avaliadas', 'contorno-evo' ), (int) $r['units'] ) ); ?><?php echo (int) $r['unmapped'] > 0 ? esc_html( sprintf( ' · %d sem vínculo', (int) $r['unmapped'] ) ) : ''; ?></li>
				<li><?php echo esc_html( sprintf( __( '%d planos recebidos', 'contorno-evo' ), (int) $r['received'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( '%d atualizados', 'contorno-evo' ), (int) $r['updated'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( '%d novos', 'contorno-evo' ), (int) $r['created'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( '%d sem alteração', 'contorno-evo' ), (int) $r['unchanged'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( '%d desativados', 'contorno-evo' ), (int) $r['disabled'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( '%d erros', 'contorno-evo' ), (int) $r['errors'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( '%d requisições à EVO', 'contorno-evo' ), (int) $r['requests'] ) ); ?></li>
			</ul>
			<?php foreach ( (array) $r['messages'] as $message ) : ?>
				<p class="contorno-evo__msg"><?php echo esc_html( (string) $message ); ?></p>
			<?php endforeach; ?>
			<?php if ( array() !== (array) $r['diffs'] ) : ?>
				<table class="widefat striped contorno-evo__diffs">
					<thead><tr><th><?php esc_html_e( 'Unidade', 'contorno-evo' ); ?></th><th><?php esc_html_e( 'Plano (idMembership)', 'contorno-evo' ); ?></th><th><?php esc_html_e( 'Mudança', 'contorno-evo' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( (array) $r['diffs'] as $unit ) : ?>
						<?php foreach ( (array) $unit['diffs'] as $diff ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $unit['title'] ); ?> <span class="description">#<?php echo esc_html( (string) $unit['branch'] ); ?></span></td>
								<td><?php echo esc_html( (string) $diff['name'] ); ?> <span class="description">(<?php echo esc_html( (string) $diff['id_membership'] ); ?>)</span></td>
								<td>
									<?php if ( 'new' === $diff['type'] ) : ?>
										<span class="contorno-evo__tag is-new"><?php esc_html_e( 'novo', 'contorno-evo' ); ?></span>
									<?php elseif ( 'missing' === $diff['type'] ) : ?>
										<span class="contorno-evo__tag is-missing"><?php esc_html_e( 'ausente no EVO', 'contorno-evo' ); ?></span>
									<?php else : ?>
										<?php foreach ( (array) $diff['changes'] as $field => $pair ) : ?>
											<div><code><?php echo esc_html( (string) $field ); ?></code> <?php echo esc_html( self::fmt( $pair[0] ) ); ?> → <strong><?php echo esc_html( self::fmt( $pair[1] ) ); ?></strong></div>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function fmt( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'sim' : 'não';
		}
		if ( is_array( $value ) ) {
			return implode( ' | ', array_map( 'strval', $value ) );
		}
		if ( is_float( $value ) ) {
			return number_format( $value, 2, ',', '.' );
		}

		return mb_substr( (string) $value, 0, 120 );
	}

	public static function render_units(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'contorno-evo' ) );
		}

		$result = get_transient( 'contorno_evo_last_result_' . get_current_user_id() );
		?>
		<div class="wrap contorno-evo">
			<h1><?php esc_html_e( 'Unidades × filiais EVO', 'contorno-evo' ); ?></h1>
			<?php self::tabs( self::PAGE_UNITS ); ?>
			<?php self::notice(); ?>
			<p class="description"><?php esc_html_e( 'O vínculo definitivo é pelo idBranch. Sugestões vêm do cadastro (campo da unidade, URLs de checkout já cadastradas ou mapa por slug) — nunca por semelhança de nome. Confira e salve.', 'contorno-evo' ); ?></p>

			<?php if ( is_array( $result ) ) : ?>
				<?php self::render_result( $result ); ?>
				<?php delete_transient( 'contorno_evo_last_result_' . get_current_user_id() ); ?>
			<?php endif; ?>

			<p>
				<button type="button" class="button" data-evo-branches <?php disabled( ! Contorno_Evo_Settings::has_credentials() ); ?>><?php esc_html_e( 'Buscar filiais no EVO', 'contorno-evo' ); ?></button>
				<span data-evo-branches-result aria-live="polite"></span>
			</p>
			<div data-evo-branches-list hidden></div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'contorno_evo_map' ); ?>
				<input type="hidden" name="action" value="contorno_evo_map" />
				<table class="widefat striped contorno-evo__units">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Unidade WordPress', 'contorno-evo' ); ?></th>
							<th><?php esc_html_e( 'EVO Branch ID', 'contorno-evo' ); ?></th>
							<th><?php esc_html_e( 'Origem', 'contorno-evo' ); ?></th>
							<th><?php esc_html_e( 'Status', 'contorno-evo' ); ?></th>
							<th><?php esc_html_e( 'Última sincronização', 'contorno-evo' ); ?></th>
							<th><?php esc_html_e( 'Planos', 'contorno-evo' ); ?></th>
							<th><?php esc_html_e( 'Ação', 'contorno-evo' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( Contorno_Evo_Mapping::posts() as $post ) : ?>
						<?php
						$branch   = Contorno_Evo_Mapping::branch( $post->ID );
						$explicit = Contorno_Evo_Mapping::is_explicit( $post->ID );
						$source   = Contorno_Evo_Mapping::suggestion_source( $post->ID );
						$last     = get_post_meta( $post->ID, CONTORNO_EVO_META_LAST_SYNC, true );
						$stored   = Contorno_Evo_Sync::stored( $post->ID );
						$active   = count( array_filter( $stored, static fn ( array $m ): bool => 'active' === ( $m['status'] ?? 'active' ) ) );
						$local    = count( Contorno_Evo_Mapping::local_plans( $post->ID ) );
						$sources  = array(
							'explicit' => __( 'confirmado', 'contorno-evo' ),
							'field'    => __( 'campo da unidade', 'contorno-evo' ),
							'checkout' => __( 'URL de checkout', 'contorno-evo' ),
							'slug'     => __( 'mapa por slug', 'contorno-evo' ),
							'none'     => __( 'sem sugestão', 'contorno-evo' ),
						);
						?>
						<tr>
							<td>
								<strong><a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a></strong>
								<span class="description"><?php echo esc_html( CONTORNO_CPT_CTN === $post->post_type ? 'CTN' : 'Unidade' ); ?> · <code><?php echo esc_html( $post->post_name ); ?></code></span>
							</td>
							<td><input type="number" min="0" class="small-text" name="branch[<?php echo esc_attr( (string) $post->ID ); ?>]" value="<?php echo esc_attr( $branch > 0 ? (string) $branch : '' ); ?>" data-evo-branch-input data-title="<?php echo esc_attr( $post->post_title ); ?>" /></td>
							<td><?php echo esc_html( $sources[ $source ] ?? $source ); ?></td>
							<td>
								<?php if ( $branch <= 0 ) : ?>
									<span class="contorno-evo__tag is-missing"><?php esc_html_e( 'não vinculada', 'contorno-evo' ); ?></span>
								<?php elseif ( ! $explicit ) : ?>
									<span class="contorno-evo__tag is-suggest"><?php esc_html_e( 'sugerido', 'contorno-evo' ); ?></span>
								<?php elseif ( is_array( $last ) ) : ?>
									<span class="contorno-evo__tag is-new"><?php esc_html_e( '✓ sincronizada', 'contorno-evo' ); ?></span>
								<?php else : ?>
									<span class="contorno-evo__tag"><?php esc_html_e( 'vinculada', 'contorno-evo' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( is_array( $last ) && ! empty( $last['time'] ) ? wp_date( 'd/m/Y H:i', (int) $last['time'] ) : '—' ); ?></td>
							<td><?php echo esc_html( sprintf( /* translators: 1: evo active, 2: local cards */ __( '%1$d EVO · %2$d cards', 'contorno-evo' ), $active, $local ) ); ?></td>
							<td class="contorno-evo__row-actions">
								<button type="submit" class="button button-small" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name="sync_one" value="<?php echo esc_attr( (string) $post->ID ); ?>" data-evo-sync-one="<?php echo esc_attr( (string) $post->ID ); ?>" <?php disabled( $branch <= 0 || ! Contorno_Evo_Settings::has_credentials() ); ?>><?php esc_html_e( 'Sincronizar', 'contorno-evo' ); ?></button>
								<?php if ( '' !== (string) get_post_meta( $post->ID, CONTORNO_EVO_META_SNAPSHOT, true ) ) : ?>
									<button type="submit" class="button-link contorno-evo__link-danger" data-evo-rollback="<?php echo esc_attr( (string) $post->ID ); ?>"><?php esc_html_e( 'Restaurar anterior', 'contorno-evo' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar vínculos', 'contorno-evo' ); ?></button></p>
			</form>

			<?php /* Formularios auxiliares usados pelos botoes de linha (JS preenche post_id). */ ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="contorno-evo-sync-one" hidden>
				<?php wp_nonce_field( 'contorno_evo_sync' ); ?>
				<input type="hidden" name="action" value="contorno_evo_sync" />
				<input type="hidden" name="post_id" value="" />
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="contorno-evo-rollback" hidden>
				<?php wp_nonce_field( 'contorno_evo_rollback' ); ?>
				<input type="hidden" name="action" value="contorno_evo_rollback" />
				<input type="hidden" name="post_id" value="" />
			</form>
		</div>
		<?php
	}

	public static function render_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'contorno-evo' ) );
		}
		?>
		<div class="wrap contorno-evo">
			<h1><?php esc_html_e( 'Log da integração EVO', 'contorno-evo' ); ?></h1>
			<?php self::tabs( self::PAGE_LOG ); ?>
			<?php self::notice(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0">
				<?php wp_nonce_field( 'contorno_evo_clear_log' ); ?>
				<input type="hidden" name="action" value="contorno_evo_clear_log" />
				<button type="submit" class="button"><?php esc_html_e( 'Limpar log', 'contorno-evo' ); ?></button>
				<span class="description"><?php echo esc_html( sprintf( /* translators: %d: limit */ __( 'Mantém as últimas %d entradas. Nunca registra token, cabeçalhos de autenticação ou dados de alunos.', 'contorno-evo' ), Contorno_Evo_Log::LIMIT ) ); ?></span>
			</form>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Quando', 'contorno-evo' ); ?></th><th><?php esc_html_e( 'Nível', 'contorno-evo' ); ?></th><th><?php esc_html_e( 'Ação', 'contorno-evo' ); ?></th><th><?php esc_html_e( 'Unidade', 'contorno-evo' ); ?></th><th>idBranch</th><th>idMembership</th><th>HTTP</th><th><?php esc_html_e( 'Mensagem', 'contorno-evo' ); ?></th></tr></thead>
				<tbody>
				<?php $entries = Contorno_Evo_Log::all(); ?>
				<?php if ( array() === $entries ) : ?>
					<tr><td colspan="8"><em><?php esc_html_e( 'Nenhum registro ainda.', 'contorno-evo' ); ?></em></td></tr>
				<?php endif; ?>
				<?php foreach ( $entries as $e ) : ?>
					<tr class="contorno-evo__log-<?php echo esc_attr( (string) $e['level'] ); ?>">
						<td><?php echo esc_html( wp_date( 'd/m/Y H:i:s', (int) $e['time'] ) ); ?></td>
						<td><?php echo esc_html( (string) $e['level'] ); ?></td>
						<td><?php echo esc_html( (string) $e['action'] ); ?></td>
						<td><?php echo esc_html( (string) $e['unit'] ); ?></td>
						<td><?php echo esc_html( (string) $e['id_branch'] ); ?></td>
						<td><?php echo esc_html( (string) $e['id_membership'] ); ?></td>
						<td><?php echo esc_html( (int) $e['http'] > 0 ? (string) $e['http'] : '' ); ?></td>
						<td><?php echo esc_html( (string) $e['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * @return array{mapped:int,unmapped:int,active:int,inactive:int,missing:int}
	 */
	public static function stats(): array {
		$stats = array( 'mapped' => 0, 'unmapped' => 0, 'active' => 0, 'inactive' => 0, 'missing' => 0 );

		foreach ( Contorno_Evo_Mapping::posts() as $post ) {
			if ( Contorno_Evo_Mapping::branch( $post->ID ) > 0 ) {
				++$stats['mapped'];
			} else {
				++$stats['unmapped'];
			}
			foreach ( Contorno_Evo_Sync::stored( $post->ID ) as $m ) {
				$status = (string) ( $m['status'] ?? 'active' );
				if ( 'active' === $status ) {
					++$stats['active'];
				} elseif ( 'inactive' === $status ) {
					++$stats['inactive'];
				} else {
					++$stats['missing'];
				}
			}
		}

		return $stats;
	}
}
