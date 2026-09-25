<?php
/**
 * Configuracoes e credenciais.
 *
 * Options (todas autoload = no):
 *   contorno_evo_settings  array  dns, base_url, auto_sync, interval_minutes,
 *                                 add_new_plans, hide_inactive, fetch_mode
 *   contorno_evo_token     string token cifrado (Contorno_Evo_Crypto)
 *   contorno_evo_status    array  ultimo teste de conexao / ultima execucao
 *
 * Constantes em wp-config.php tem precedencia sobre o banco:
 *   CONTORNO_EVO_USERNAME  (DNS)
 *   CONTORNO_EVO_TOKEN
 *   CONTORNO_EVO_BASE_URL  (opcional)
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Settings {

	public const OPTION          = 'contorno_evo_settings';
	public const OPTION_TOKEN    = 'contorno_evo_token';
	public const OPTION_STATUS   = 'contorno_evo_status';
	public const MIN_INTERVAL    = 30;    // minutos
	public const MAX_INTERVAL    = 1440;  // minutos

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'dns'              => '',
			'base_url'         => CONTORNO_EVO_DEFAULT_BASE_URL,
			'auto_sync'        => false,
			'interval_minutes' => 360,
			'add_new_plans'    => true,
			'hide_inactive'    => true,
			'fetch_mode'       => 'auto', // auto | global | per-branch

			/*
			 * Checkout nativo — ver Contorno_Evo_Checkout.
			 *
			 * checkout_mode        off   comportamento atual (redireciona para a urlSale da EVO)
			 *                      pilot so as unidades de checkout_allowlist usam o nativo
			 *                      on    nativo liberado para toda unidade vinculada
			 * checkout_allowlist   slugs liberados no modo pilot
			 * checkout_payment_card CODIGO de "cartao de credito" em
			 *                      EFormaPagamentoTotem (campo `payment` de
			 *                      POST /api/v2/sales). 0 = NAO CONFIRMADO.
			 *                      A especificacao publica da EVO lista o enum
			 *                      como [1,2,3,4,5,6,7,13,14,15,16,17] SEM
			 *                      dizer o que cada numero significa, e a
			 *                      unica tabela nomeada do swagger
			 *                      (filtro de /api/v1/receivables) e de OUTRO
			 *                      enum. Chutar aqui e cobrar na forma errada.
			 * checkout_codes_confirmed  alguem conferiu checkout_payment_card
			 *                      contra a EVO real. Sem isto, pilot/on nao
			 *                      liberam o pagamento nativo.
			 * checkout_evopay_script  URL do componente EVO Pay (<evo-cartao>).
			 *                      Nao consta do swagger; vem da configuracao
			 *                      do gateway/EVO na homologacao.
			 */
			'checkout_mode'            => 'off',
			'checkout_allowlist'       => array( 'lourdes' ),
			'checkout_payment_card'    => 0,
			'checkout_codes_confirmed' => false,
			'checkout_evopay_script'   => '',

			/*
			 * checkout_require_address — pedir endereco no checkout?
			 *
			 * Comeca DESLIGADO por falta de evidencia: em
			 * ProspectApiIntegracaoViewModel e em MemberNewSaleViewModel todos
			 * os campos de endereco sao nullable, e a EVO nao expoe leitura
			 * das sale-settings da filial (existe PATCH
			 * /api/v1/configuration/branch/sale-settings, nao GET). Ou seja:
			 * nao ha como provar, antes da homologacao, que a Contorno exige
			 * endereco. Obrigar o visitante a preencher CEP, rua e numero "por
			 * garantia" e inventar exigencia. Se a EVO devolver 400 pedindo
			 * endereco na homologacao, liga-se aqui.
			 */
			'checkout_require_address' => false,
		);
	}

	public static function ensure_defaults(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
		if ( false === get_option( self::OPTION_TOKEN, false ) ) {
			add_option( self::OPTION_TOKEN, '', '', false );
		}
		if ( false === get_option( self::OPTION_STATUS, false ) ) {
			add_option( self::OPTION_STATUS, array(), '', false );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();

		return $all[ $key ] ?? $default;
	}

	/**
	 * @param array<string,mixed> $values
	 */
	public static function save( array $values ): void {
		$current = self::all();
		$clean   = array(
			'dns'              => sanitize_text_field( (string) ( $values['dns'] ?? $current['dns'] ) ),
			'base_url'         => self::sanitize_base_url( (string) ( $values['base_url'] ?? $current['base_url'] ) ),
			'auto_sync'        => ! empty( $values['auto_sync'] ),
			'interval_minutes' => max( self::MIN_INTERVAL, min( self::MAX_INTERVAL, (int) ( $values['interval_minutes'] ?? $current['interval_minutes'] ) ) ),
			'add_new_plans'    => ! empty( $values['add_new_plans'] ),
			'hide_inactive'    => ! empty( $values['hide_inactive'] ),
			'fetch_mode'       => in_array( (string) ( $values['fetch_mode'] ?? '' ), array( 'auto', 'global', 'per-branch' ), true ) ? (string) $values['fetch_mode'] : $current['fetch_mode'],

			'checkout_mode'            => in_array( (string) ( $values['checkout_mode'] ?? '' ), self::CHECKOUT_MODES, true ) ? (string) $values['checkout_mode'] : $current['checkout_mode'],
			'checkout_allowlist'       => self::sanitize_allowlist( $values['checkout_allowlist'] ?? $current['checkout_allowlist'] ),
			// Fora do enum documentado nao entra: um codigo invalido viraria
			// 400 da EVO no melhor caso e cobranca na forma errada no pior.
			'checkout_payment_card'    => in_array( (int) ( $values['checkout_payment_card'] ?? 0 ), self::PAYMENT_CODES, true ) ? (int) $values['checkout_payment_card'] : 0,
			'checkout_codes_confirmed' => ! empty( $values['checkout_codes_confirmed'] ),
			'checkout_evopay_script'   => self::sanitize_script_url( (string) ( $values['checkout_evopay_script'] ?? $current['checkout_evopay_script'] ) ),
			'checkout_require_address' => ! empty( $values['checkout_require_address'] ),
		);

		self::ensure_defaults();
		update_option( self::OPTION, $clean, false );
	}

	/* ---------------------------------------------------------------
	 * Checkout nativo
	 * ------------------------------------------------------------- */

	public const CHECKOUT_MODES = array( 'off', 'pilot', 'on' );

	/** Valores validos de EFormaPagamentoTotem (POST /api/v2/sales, campo `payment`). */
	public const PAYMENT_CODES = array( 1, 2, 3, 4, 5, 6, 7, 13, 14, 15, 16, 17 );

	/**
	 * @param mixed $value
	 * @return array<int,string>
	 */
	public static function sanitize_allowlist( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value ) ?: array();
		}

		$out = array();

		foreach ( (array) $value as $slug ) {
			$slug = sanitize_title( (string) $slug );

			if ( '' !== $slug ) {
				$out[ $slug ] = true;
			}
		}

		return array_keys( $out );
	}

	/**
	 * URL do componente EVO Pay. Mesma lista fechada de hosts da API: o
	 * script e carregado na pagina de pagamento e, num host arbitrario,
	 * seria XSS com permissao para ler o formulario do cartao.
	 */
	public static function sanitize_script_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url || ! preg_match( '#^https://#i', $url ) ) {
			return '';
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts )
			|| ! self::host_is_allowed( (string) ( $parts['host'] ?? '' ) )
			|| isset( $parts['user'], $parts['pass'] )
			|| ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] )
		) {
			return '';
		}

		return esc_url_raw( $url );
	}

	/** off | pilot | on */
	public static function checkout_mode(): string {
		$mode = (string) self::get( 'checkout_mode', 'off' );

		return in_array( $mode, self::CHECKOUT_MODES, true ) ? $mode : 'off';
	}

	/**
	 * O checkout nativo pode rodar para este slug?
	 *
	 * Uma unidade fora da allowlist em modo pilot cai no comportamento atual —
	 * nao ve erro, ve o checkout externo, como sempre.
	 */
	public static function checkout_enabled_for( string $slug ): bool {
		if ( ! self::checkout_ready() ) {
			return false;
		}

		return match ( self::checkout_mode() ) {
			'on'    => true,
			'pilot' => in_array( sanitize_title( $slug ), (array) self::get( 'checkout_allowlist', array() ), true ),
			default => false,
		};
	}

	/**
	 * Pre-requisitos tecnicos do checkout nativo, independentes de unidade.
	 *
	 * Sem credencial nao ha como resolver preco na EVO; sem codigo de
	 * pagamento confirmado, a venda iria com `payment` chutado.
	 */
	public static function checkout_ready(): bool {
		return self::has_credentials()
			&& (bool) self::get( 'checkout_codes_confirmed', false )
			&& self::payment_code_card() > 0;
	}

	public static function payment_code_card(): int {
		$code = (int) self::get( 'checkout_payment_card', 0 );

		return in_array( $code, self::PAYMENT_CODES, true ) ? $code : 0;
	}

	/**
	 * Motivos de o checkout nativo nao estar pronto — texto da tela de
	 * configuracao, nunca do visitante.
	 *
	 * @return array<int,string>
	 */
	public static function checkout_blockers(): array {
		$blockers = array();

		if ( ! self::has_credentials() ) {
			$blockers[] = __( 'Credenciais EVO (DNS e token) não configuradas.', 'contorno-evo' );
		}

		if ( self::payment_code_card() <= 0 ) {
			$blockers[] = __( 'Código de pagamento "cartão de crédito" não informado (campo payment de POST /api/v2/sales).', 'contorno-evo' );
		}

		if ( ! (bool) self::get( 'checkout_codes_confirmed', false ) ) {
			$blockers[] = __( 'Os códigos de pagamento ainda não foram conferidos contra a EVO real.', 'contorno-evo' );
		}

		if ( '' === (string) self::get( 'checkout_evopay_script', '' ) ) {
			$blockers[] = __( 'URL do componente EVO Pay não informada — sem ela não há tokenização de cartão.', 'contorno-evo' );
		}

		return $blockers;
	}

	/**
	 * Hosts aceitos como API da EVO.
	 *
	 * O token da EVO viaja no cabecalho Authorization de toda chamada. Se a
	 * base URL pudesse apontar para qualquer lugar, bastaria salvar
	 * "https://coletor.exemplo" nas configuracoes para que o proximo
	 * "Testar conexao" ENTREGASSE o token ao destino — e, com um host
	 * interno (127.0.0.1, 10.x, 169.254.169.254), o WordPress viraria proxy
	 * para a rede do servidor (SSRF). Por isso o destino e uma lista fechada,
	 * e nao "qualquer https://".
	 *
	 * @return array<int,string>
	 */
	public static function allowed_hosts(): array {
		$default = (string) wp_parse_url( CONTORNO_EVO_DEFAULT_BASE_URL, PHP_URL_HOST );

		/**
		 * Hosts adicionais da EVO (sufixo de dominio ou host exato).
		 *
		 * @param array<int,string> $hosts
		 */
		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn ( $host ): string => strtolower( trim( (string) $host ) ),
						(array) apply_filters(
							'contorno_evo_allowed_hosts',
							array( $default, 'w12app.com.br', 'abcevo.com' )
						)
					)
				)
			)
		);
	}

	public static function host_is_allowed( string $host ): bool {
		$host = strtolower( trim( $host ) );

		if ( '' === $host ) {
			return false;
		}

		foreach ( self::allowed_hosts() as $allowed ) {
			if ( $host === $allowed || str_ends_with( $host, '.' . $allowed ) ) {
				return true;
			}
		}

		return false;
	}

	public static function sanitize_base_url( string $url ): string {
		$url = untrailingslashit( trim( $url ) );

		if ( '' === $url || ! preg_match( '#^https://#i', $url ) ) {
			return CONTORNO_EVO_DEFAULT_BASE_URL;
		}

		$parts = wp_parse_url( $url );

		// Porta fora do padrao, credenciais embutidas (user:pass@) ou host
		// fora da lista: volta para a base oficial em vez de obedecer.
		if ( ! is_array( $parts )
			|| ! self::host_is_allowed( (string) ( $parts['host'] ?? '' ) )
			|| isset( $parts['user'], $parts['pass'] )
			|| ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] )
		) {
			return CONTORNO_EVO_DEFAULT_BASE_URL;
		}

		return esc_url_raw( $url );
	}

	/* ---------------------------------------------------------------
	 * Credenciais
	 * ------------------------------------------------------------- */

	public static function dns(): string {
		if ( defined( 'CONTORNO_EVO_USERNAME' ) && '' !== (string) constant( 'CONTORNO_EVO_USERNAME' ) ) {
			return (string) constant( 'CONTORNO_EVO_USERNAME' );
		}

		return (string) self::get( 'dns', '' );
	}

	public static function base_url(): string {
		if ( defined( 'CONTORNO_EVO_BASE_URL' ) && '' !== (string) constant( 'CONTORNO_EVO_BASE_URL' ) ) {
			return self::sanitize_base_url( (string) constant( 'CONTORNO_EVO_BASE_URL' ) );
		}

		return self::sanitize_base_url( (string) self::get( 'base_url', CONTORNO_EVO_DEFAULT_BASE_URL ) );
	}

	/**
	 * Token em claro — usado SOMENTE para montar o header Authorization.
	 * Nunca chegue com isso perto de HTML, JS, log ou REST.
	 */
	public static function token(): string {
		if ( defined( 'CONTORNO_EVO_TOKEN' ) && '' !== (string) constant( 'CONTORNO_EVO_TOKEN' ) ) {
			return (string) constant( 'CONTORNO_EVO_TOKEN' );
		}

		$stored = (string) get_option( self::OPTION_TOKEN, '' );

		return $stored === '' ? '' : (string) ( Contorno_Evo_Crypto::decrypt( $stored ) ?? '' );
	}

	public static function credentials_from_constants(): bool {
		return defined( 'CONTORNO_EVO_USERNAME' ) && defined( 'CONTORNO_EVO_TOKEN' )
			&& '' !== (string) constant( 'CONTORNO_EVO_USERNAME' ) && '' !== (string) constant( 'CONTORNO_EVO_TOKEN' );
	}

	public static function has_token(): bool {
		return '' !== self::token();
	}

	/** Token existe no banco mas nao decifra (salts trocados). */
	public static function token_is_unreadable(): bool {
		if ( self::credentials_from_constants() ) {
			return false;
		}

		$stored = (string) get_option( self::OPTION_TOKEN, '' );

		return '' !== $stored && null === Contorno_Evo_Crypto::decrypt( $stored );
	}

	public static function has_credentials(): bool {
		return '' !== self::dns() && self::has_token();
	}

	/**
	 * Grava um token novo. String vazia = PRESERVA o atual (regra da tela).
	 */
	public static function save_token( string $plain ): bool {
		$plain = trim( $plain );

		if ( '' === $plain ) {
			return true;
		}

		$cipher = Contorno_Evo_Crypto::encrypt( $plain );

		if ( '' === $cipher ) {
			return false;
		}

		self::ensure_defaults();
		update_option( self::OPTION_TOKEN, $cipher, false );

		return true;
	}

	public static function clear_token(): void {
		update_option( self::OPTION_TOKEN, '', false );
	}

	/** Mascara de exibicao — nunca revela o valor. */
	public static function token_mask(): string {
		return self::has_token() ? str_repeat( '•', 16 ) : '';
	}

	/* ---------------------------------------------------------------
	 * Status (ultimo teste / ultima execucao)
	 * ------------------------------------------------------------- */

	/**
	 * @return array<string,mixed>
	 */
	public static function status(): array {
		$status = get_option( self::OPTION_STATUS, array() );

		return is_array( $status ) ? $status : array();
	}

	/**
	 * @param array<string,mixed> $patch
	 */
	public static function update_status( array $patch ): void {
		self::ensure_defaults();
		update_option( self::OPTION_STATUS, array_merge( self::status(), $patch ), false );
	}
}
