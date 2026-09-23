<?php
/**
 * Tela "Contorno > Localização": chave da Google Maps Platform (Geocoding
 * API) usada na busca de unidades por CEP.
 *
 * A chave nunca volta para o HTML (so os 4 ultimos caracteres), campo vazio
 * preserva a chave atual e a constante CONTORNO_GOOGLE_MAPS_API_KEY no
 * wp-config.php tem precedencia sobre o valor salvo aqui.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_LOCATION_PAGE = 'contorno-localizacao';

add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'contorno-migracao',
			__( 'Localização', 'contorno' ),
			__( 'Localização', 'contorno' ),
			'manage_options',
			CONTORNO_LOCATION_PAGE,
			'contorno_render_location_page'
		);
	},
	20
);

/**
 * Apaga todos os transients de geocodificacao (CEP, Google, Nominatim).
 */
function contorno_geo_flush_cache(): int {
	global $wpdb;

	$like    = $wpdb->esc_like( '_transient_contorno_geo_' ) . '%';
	$timeout = $wpdb->esc_like( '_transient_timeout_contorno_geo_' ) . '%';

	return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

function contorno_render_location_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'contorno' ) );
	}

	$notices = array();

	if ( isset( $_POST['contorno_location_action'] ) ) {
		check_admin_referer( 'contorno_location' );

		$action = sanitize_key( wp_unslash( (string) $_POST['contorno_location_action'] ) );

		if ( 'save' === $action ) {
			$new_key = isset( $_POST['contorno_google_key'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['contorno_google_key'] ) ) ) : '';

			if ( ! empty( $_POST['contorno_google_key_remove'] ) ) {
				contorno_google_maps_save_key( '' );
				contorno_geo_flush_cache();
				$notices[] = array( 'success', __( 'Chave removida. A busca volta a usar a geocodificação de reserva (OpenStreetMap).', 'contorno' ) );
			} elseif ( '' === $new_key ) {
				$notices[] = array( 'info', __( 'Nenhuma chave informada — a chave atual foi mantida.', 'contorno' ) );
			} elseif ( 1 !== preg_match( '/^[A-Za-z0-9_\-]{20,120}$/', $new_key ) ) {
				$notices[] = array( 'error', __( 'Formato de chave inválido. Copie a chave exatamente como aparece no Google Cloud.', 'contorno' ) );
			} elseif ( contorno_google_maps_save_key( $new_key ) ) {
				contorno_geo_flush_cache();
				$notices[] = array( 'success', __( 'Chave salva (cifrada). Use "Testar conexão" para validar.', 'contorno' ) );
			} else {
				$notices[] = array( 'error', __( 'Não foi possível cifrar a chave neste servidor (sem libsodium/OpenSSL). Defina a constante CONTORNO_GOOGLE_MAPS_API_KEY no wp-config.php.', 'contorno' ) );
			}
		} elseif ( 'test' === $action ) {
			$hit    = contorno_google_geocode(
				array(
					'street'      => 'Praça Sete de Setembro',
					'city'        => 'Belo Horizonte',
					'state'       => 'MG',
				),
				false
			);
			$status = contorno_google_geocode_status();

			if ( is_array( $hit ) ) {
				$notices[] = array( 'success', sprintf( /* translators: 1: latitude, 2: longitude */ __( 'Conexão OK. Praça Sete, Belo Horizonte/MG → %1$s, %2$s.', 'contorno' ), number_format( $hit['lat'], 5 ), number_format( $hit['lng'], 5 ) ) );
			} elseif ( null === $hit ) {
				$notices[] = array( 'warning', __( 'O Google respondeu, mas não encontrou o endereço de teste.', 'contorno' ) );
			} elseif ( '' === contorno_google_maps_api_key() ) {
				$notices[] = array( 'error', __( 'Nenhuma chave configurada.', 'contorno' ) );
			} else {
				$messages = array(
					'http_400' => __( 'HTTP 400 — chave inválida ou requisição recusada.', 'contorno' ),
					'http_403' => __( 'HTTP 403 — a Geocoding API não está habilitada no projeto, o faturamento está desativado ou a restrição da chave bloqueou este servidor (confira o IP de saída).', 'contorno' ),
					'http_429' => __( 'HTTP 429 — cota excedida.', 'contorno' ),
					'network'  => __( 'Falha de rede ao acessar geocode.googleapis.com.', 'contorno' ),
				);
				$notices[] = array( 'error', $messages[ $status ] ?? sprintf( /* translators: %s: status code */ __( 'Falha ao consultar o Google (%s).', 'contorno' ), $status ) );
			}
		} elseif ( 'flush' === $action ) {
			$count     = contorno_geo_flush_cache();
			$notices[] = array( 'success', sprintf( /* translators: %d: rows */ __( 'Cache de geocodificação limpo (%d registros).', 'contorno' ), $count ) );
		}
	}

	$source = contorno_google_maps_key_source();
	$hint   = contorno_google_maps_key_hint();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Contorno — Localização', 'contorno' ); ?></h1>
		<p><?php esc_html_e( 'Busca de unidades por CEP: o ViaCEP informa o endereço do CEP e a Google Geocoding API converte esse endereço em coordenadas. Sem chave, a busca continua funcionando com a geocodificação de reserva (OpenStreetMap), com precisão menor.', 'contorno' ); ?></p>

		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?>"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php endforeach; ?>

		<h2><?php esc_html_e( 'Google Maps API Key', 'contorno' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Situação', 'contorno' ); ?></th>
				<td>
					<?php if ( 'constant' === $source ) : ?>
						<strong><?php esc_html_e( 'Definida no wp-config.php (CONTORNO_GOOGLE_MAPS_API_KEY)', 'contorno' ); ?></strong> — <code><?php echo esc_html( $hint ); ?></code>
						<p class="description"><?php esc_html_e( 'A constante tem precedência: o valor salvo nesta tela é ignorado enquanto ela existir.', 'contorno' ); ?></p>
					<?php elseif ( 'option' === $source && '' !== $hint ) : ?>
						<strong><?php esc_html_e( 'Configurada', 'contorno' ); ?></strong> — <code><?php echo esc_html( $hint ); ?></code>
					<?php elseif ( 'option' === $source ) : ?>
						<strong style="color:#b32d2e"><?php esc_html_e( 'Salva com salts antigos — informe a chave novamente.', 'contorno' ); ?></strong>
					<?php else : ?>
						<strong><?php esc_html_e( 'Não configurada', 'contorno' ); ?></strong> — <?php esc_html_e( 'usando OpenStreetMap como reserva.', 'contorno' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php if ( 'constant' !== $source ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'contorno_location' ); ?>
				<input type="hidden" name="contorno_location_action" value="save" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="contorno-google-key"><?php esc_html_e( 'Nova chave', 'contorno' ); ?></label></th>
						<td>
							<input type="password" id="contorno-google-key" name="contorno_google_key" class="regular-text" value="" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr( '' !== $hint ? $hint : 'AIza…' ); ?>" />
							<p class="description"><?php esc_html_e( 'Deixe em branco para manter a chave atual. A chave é guardada cifrada e nunca é exibida de volta.', 'contorno' ); ?></p>
							<?php if ( 'option' === $source ) : ?>
								<p><label><input type="checkbox" name="contorno_google_key_remove" value="1" /> <?php esc_html_e( 'Remover a chave salva', 'contorno' ); ?></label></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Salvar chave', 'contorno' ) ); ?>
			</form>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Diagnóstico', 'contorno' ); ?></h2>
		<form method="post" style="display:inline-block;margin-right:8px">
			<?php wp_nonce_field( 'contorno_location' ); ?>
			<input type="hidden" name="contorno_location_action" value="test" />
			<?php submit_button( __( 'Testar conexão com o Google', 'contorno' ), 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" style="display:inline-block">
			<?php wp_nonce_field( 'contorno_location' ); ?>
			<input type="hidden" name="contorno_location_action" value="flush" />
			<?php submit_button( __( 'Limpar cache de geocodificação', 'contorno' ), 'secondary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Configuração no Google Cloud', 'contorno' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Projeto com faturamento ativo.', 'contorno' ); ?></li>
			<li><?php esc_html_e( 'Habilitar somente: Geocoding API.', 'contorno' ); ?></li>
			<li><?php esc_html_e( 'Restrição de API da chave: apenas Geocoding API.', 'contorno' ); ?></li>
			<li><?php esc_html_e( 'Restrição de aplicativo: endereços IP — o IP de saída do servidor (a chamada é feita pelo WordPress, não pelo navegador).', 'contorno' ); ?></li>
		</ol>
	</div>
	<?php
}
