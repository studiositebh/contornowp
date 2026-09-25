<?php
/**
 * Configuracao do gateway da filial — GET /api/v2/configuration/gateway.
 *
 * O que a especificacao oficial garante (BranchGatewayViewModel):
 *
 *   gatewayType        ETipoGateway, enum [0..19] — SEM nomes na spec
 *   gatewayData        objeto LIVRE, sem schema declarado
 *   showCardType       boolean
 *   tokenizeBackend    boolean
 *   validationEnabled  boolean
 *
 * Duas consequencias que mandam no desenho desta classe:
 *
 * 1. NADA de hardcodar gateway. `gatewayType` e lido e repassado como
 *    numero; o significado de cada valor so sera conhecido com a resposta
 *    real da filial. Por isso nao existe `if ( 3 === $type )` em lugar
 *    nenhum do plugin.
 *
 * 2. `gatewayData` NAO tem schema, e um objeto livre vindo de um sistema que
 *    guarda credenciais de adquirente. Expor esse objeto ao navegador
 *    "porque o EVO Pay talvez precise" seria entregar o que estiver lá
 *    dentro — inclusive chave privada, se estiver. Por isso o que vai para o
 *    JS passa por LISTA FECHADA de chaves (self::PUBLIC_KEYS), e nao por
 *    lista de exclusao: chave desconhecida fica fora por padrao, e apenas o
 *    NOME dela entra no log, para a homologacao saber o que pedir.
 *
 * Os campos que o pedido desta rodada esperava (publicKey, gerarFormToken,
 * antifraude) nao existem em BranchGatewayViewModel. Se existirem, virao
 * dentro de gatewayData — e so a resposta real dira. Nada aqui os presume.
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Gateway {

	/** Cache por filial. Curto: mudanca de gateway precisa aparecer no mesmo dia. */
	public const TTL = 900; // 15 min

	/**
	 * Chaves de gatewayData que podem chegar ao navegador.
	 *
	 * Criterio: so entra o que e publico por definicao no fluxo de
	 * tokenizacao (identificador de loja, chave publica, ambiente, URL do
	 * componente). Qualquer outra coisa fica no servidor.
	 */
	public const PUBLIC_KEYS = array(
		'publicKey',
		'publicToken',
		'merchantId',
		'merchantCode',
		'establishmentCode',
		'environment',
		'scriptUrl',
		'checkoutUrl',
		'tokenizeUrl',
	);

	/**
	 * Configuracao crua da filial, com cache.
	 *
	 * @return array{ok:bool,message:string,http:int,config:array<string,mixed>}
	 */
	public static function fetch( int $id_branch, bool $force = false ): array {
		$key = 'contorno_evo_gw_' . $id_branch;

		if ( ! $force ) {
			$cached = get_transient( $key );

			if ( is_array( $cached ) ) {
				return array( 'ok' => true, 'message' => '', 'http' => 200, 'config' => $cached );
			}
		}

		$client = new Contorno_Evo_Client();
		$result = $client->gateway( $id_branch > 0 ? $id_branch : null );

		if ( ! $result['ok'] ) {
			return array( 'ok' => false, 'message' => $result['message'], 'http' => $result['http'], 'config' => array() );
		}

		$raw = $result['data'];

		// A rota pode devolver o objeto ou uma lista de um item; com chave
		// multi-filial, conferimos que o item e da filial pedida quando ele
		// informa idBranch.
		if ( is_array( $raw ) && ! self::looks_like_config( $raw ) ) {
			$raw = self::pick_branch( $raw, $id_branch );
		}

		if ( ! is_array( $raw ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'A EVO não devolveu a configuração de gateway desta filial.', 'contorno-evo' ),
				'http'    => $result['http'],
				'config'  => array(),
			);
		}

		$config = self::normalize( $raw, $id_branch );

		set_transient( $key, $config, self::TTL );
		self::log_unknown_keys( $raw, $id_branch );

		return array( 'ok' => true, 'message' => '', 'http' => $result['http'], 'config' => $config );
	}

	public static function flush( int $id_branch ): void {
		delete_transient( 'contorno_evo_gw_' . $id_branch );
	}

	/**
	 * @param array<mixed> $row
	 */
	private static function looks_like_config( array $row ): bool {
		return array_key_exists( 'gatewayType', $row )
			|| array_key_exists( 'tokenizeBackend', $row )
			|| array_key_exists( 'validationEnabled', $row );
	}

	/**
	 * @param array<mixed> $list
	 * @return array<string,mixed>|null
	 */
	private static function pick_branch( array $list, int $id_branch ): ?array {
		$first = null;

		foreach ( $list as $row ) {
			if ( ! is_array( $row ) || ! self::looks_like_config( $row ) ) {
				continue;
			}

			$first ??= $row;

			if ( isset( $row['idBranch'] ) && (int) $row['idBranch'] === $id_branch ) {
				return $row;
			}
		}

		// Nenhum item declara idBranch: com filial unica isso e normal, e o
		// unico item e o certo. Com varios itens sem idBranch nao ha como
		// escolher, e adivinhar seria cobrar pelo gateway de outra filial.
		return $first;
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	private static function normalize( array $raw, int $id_branch ): array {
		$data   = is_array( $raw['gatewayData'] ?? null ) ? $raw['gatewayData'] : array();
		$public = array();

		foreach ( self::PUBLIC_KEYS as $allowed ) {
			if ( isset( $data[ $allowed ] ) && is_scalar( $data[ $allowed ] ) && '' !== (string) $data[ $allowed ] ) {
				$public[ $allowed ] = sanitize_text_field( (string) $data[ $allowed ] );
			}
		}

		return array(
			'id_branch'          => $id_branch,
			'gateway_type'       => (int) ( $raw['gatewayType'] ?? -1 ),
			'show_card_type'     => ! empty( $raw['showCardType'] ),
			'tokenize_backend'   => ! empty( $raw['tokenizeBackend'] ),
			'validation_enabled' => ! empty( $raw['validationEnabled'] ),
			// Subconjunto publico, ja filtrado. Nunca o gatewayData inteiro.
			'public_data'        => $public,
			'fetched'            => time(),
		);
	}

	/**
	 * Registra os NOMES das chaves desconhecidas de gatewayData.
	 *
	 * So o nome: o valor pode ser segredo. Serve para a homologacao saber o
	 * que a EVO realmente manda e decidir, com evidencia, se alguma delas
	 * precisa entrar em PUBLIC_KEYS.
	 *
	 * @param array<string,mixed> $raw
	 */
	private static function log_unknown_keys( array $raw, int $id_branch ): void {
		$data = is_array( $raw['gatewayData'] ?? null ) ? $raw['gatewayData'] : array();
		$new  = array_diff( array_map( 'strval', array_keys( $data ) ), self::PUBLIC_KEYS );

		if ( array() === $new ) {
			return;
		}

		Contorno_Evo_Log::add(
			'info',
			sprintf(
				/* translators: %s: comma separated key names */
				__( 'gatewayData trouxe campos não mapeados (só os nomes): %s', 'contorno-evo' ),
				implode( ', ', array_map( 'sanitize_key', array_slice( $new, 0, 20 ) ) )
			),
			array( 'action' => 'gateway', 'id_branch' => (string) $id_branch )
		);
	}

	/**
	 * O que o navegador recebe. Nada de gatewayType interpretado, nada de
	 * segredo — so o suficiente para o componente EVO Pay se montar.
	 *
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	public static function public_view( array $config ): array {
		return array(
			'gatewayType'       => (int) ( $config['gateway_type'] ?? -1 ),
			'showCardType'      => (bool) ( $config['show_card_type'] ?? false ),
			'tokenizeBackend'   => (bool) ( $config['tokenize_backend'] ?? false ),
			'validationEnabled' => (bool) ( $config['validation_enabled'] ?? false ),
			'data'              => (array) ( $config['public_data'] ?? array() ),
			'scriptUrl'         => Contorno_Evo_Settings::sanitize_script_url(
				(string) ( $config['public_data']['scriptUrl'] ?? Contorno_Evo_Settings::get( 'checkout_evopay_script', '' ) )
			),
		);
	}
}
