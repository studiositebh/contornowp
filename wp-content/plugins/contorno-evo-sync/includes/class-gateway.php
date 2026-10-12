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
 * O QUE A RESPOSTA REAL MOSTROU (09/10/2026, filiais 1 e 8 da rede):
 *
 *   gatewayType 9, tokenizeBackend false, gatewayData com as chaves
 *   keyRecorrencia, publicKey, keyVindi, versaoFrameworkVindi, idVindi,
 *   origemFranquia, keyRecorrenciaFranquia, publicKeyFranquia,
 *   gerarFormToken, antifraude, idW12.
 *
 * E o componente oficial <evo-cartao> (w12evopay.com/evocartao/evo-pay.js,
 * o mesmo que o checkout da EVO usa) recebe exatamente este objeto como
 * propriedade `gateway` = { tipoGateway, dadosGateway } e, com
 * tokenizacao no navegador, faz:
 *
 *   tipo 9 -> POST https://api.pagar.me/core/v5/tokens?appId=<publicKey>
 *   origemFranquia == 1 -> repete com publicKeyFranquia (token da franquia)
 *
 * Ou seja: o gateway da rede e Pagar.me (numeracao do proprio componente:
 * 1 Vindi, 3 Mundipagg, 9 Pagar.me...). As chaves keyVindi/idVindi sao
 * campos genericos do objeto, nao usados pelo tipo 9.
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
	 * Criterio: so o que o <evo-cartao> le no fluxo de tokenizacao NO
	 * NAVEGADOR — chave publica do adquirente e a da franquia, e a flag que
	 * diz se ha franquia. E o mesmo conjunto que o checkout da propria EVO ja
	 * expoe ao navegador. Ficam no servidor: keyRecorrencia*, keyVindi,
	 * idVindi, idW12, antifraude, gerarFormToken e qualquer chave nova.
	 */
	public const PUBLIC_KEYS = array(
		'publicKey',
		'publicKeyFranquia',
		'origemFranquia',
	);

	/**
	 * gatewayType com tokenizacao no navegador VALIDADA para este site.
	 *
	 * So o 9 (Pagar.me), o da rede. O componente conhece outros, mas cada um
	 * le campos diferentes de gatewayData (merchantId, senhaTemporaria...)
	 * que hoje nao repassamos; liberar sem validar seria abrir um checkout
	 * que falha so na hora do cartao.
	 */
	public const FRONTEND_TYPES = array( 9 );

	/**
	 * Chaves ja vistas na resposta real que ficam no servidor. Conhecidas =
	 * nao entram no log de "campos novos" a cada consulta.
	 */
	public const KNOWN_SERVER_KEYS = array(
		'keyRecorrencia',
		'keyRecorrenciaFranquia',
		'keyVindi',
		'versaoFrameworkVindi',
		'idVindi',
		'gerarFormToken',
		'antifraude',
		'idW12',
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
			if ( ! isset( $data[ $allowed ] ) || ! is_scalar( $data[ $allowed ] ) || '' === (string) $data[ $allowed ] ) {
				continue;
			}

			// origemFranquia e flag: o componente compara com 1 (==).
			$public[ $allowed ] = 'origemFranquia' === $allowed
				? ( filter_var( $data[ $allowed ], FILTER_VALIDATE_BOOLEAN ) ? 1 : 0 )
				: sanitize_text_field( (string) $data[ $allowed ] );
		}

		return array(
			'id_branch'          => $id_branch,
			'gateway_type'       => (int) ( $raw['gatewayType'] ?? -1 ),
			'show_card_type'     => ! empty( $raw['showCardType'] ),
			'tokenize_backend'   => ! empty( $raw['tokenizeBackend'] ),
			'validation_enabled' => ! empty( $raw['validationEnabled'] ),
			// Antifraude ligado no tipo 9: o checkout da EVO passa a EXIGIR
			// endereco (flCadastroEnderecoObrigatorio). So o booleano sai daqui.
			'antifraud'          => isset( $data['antifraude'] ) && filter_var( $data['antifraude'], FILTER_VALIDATE_BOOLEAN ),
			// Subconjunto publico, ja filtrado. Nunca o gatewayData inteiro.
			'public_data'        => $public,
			'fetched'            => time(),
		);
	}

	/** Cache das bandeiras e rotulos do componente: mudam raramente. */
	public const ASSETS_TTL = 43200; // 12 h

	/**
	 * Bandeiras e traducoes para o <evo-cartao>, ja saneadas e com cache.
	 *
	 * Opcionais: se a EVO nao responder, o componente usa a propria lista
	 * padrao de bandeiras e os rotulos embutidos — o checkout NAO deixa de
	 * abrir por isso. Falha nao entra no cache (tenta de novo na proxima).
	 *
	 * @return array{bandeiras:array<int,array<string,string>>,traducoes:array<string,string>}
	 */
	public static function card_assets( bool $force = false ): array {
		$key    = 'contorno_evo_card_assets';
		$cached = $force ? false : get_transient( $key );

		if ( is_array( $cached ) && isset( $cached['bandeiras'], $cached['traducoes'] ) ) {
			return $cached;
		}

		$client = new Contorno_Evo_Client();
		$flags  = $client->card_flags();
		$texts  = $client->card_translation();
		$assets = array(
			'bandeiras' => $flags['ok'] ? self::clean_flags( $flags['data'] ) : array(),
			'traducoes' => $texts['ok'] ? self::clean_translations( $texts['data'] ) : array(),
		);

		if ( $flags['ok'] && $texts['ok'] ) {
			set_transient( $key, $assets, self::ASSETS_TTL );
		}

		return $assets;
	}

	/**
	 * [{ value, text, logoUrl }] -> so strings, logo so https.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function clean_flags( mixed $data ): array {
		$out = array();

		foreach ( is_array( $data ) ? array_slice( $data, 0, 40 ) : array() as $row ) {
			if ( ! is_array( $row ) || ! is_scalar( $row['value'] ?? null ) || '' === (string) $row['value'] ) {
				continue;
			}

			$flag = array(
				'value' => sanitize_text_field( (string) $row['value'] ),
				'text'  => sanitize_text_field( (string) ( $row['text'] ?? $row['value'] ) ),
			);

			$logo = is_string( $row['logoUrl'] ?? null ) ? trim( $row['logoUrl'] ) : '';
			if ( preg_match( '#^https://#i', $logo ) ) {
				$flag['logoUrl'] = esc_url_raw( $logo );
			}

			$out[] = $flag;
		}

		return $out;
	}

	/**
	 * { "Número do cartão": "Número do cartão", ... } -> string => string.
	 *
	 * @return array<string,string>
	 */
	public static function clean_translations( mixed $data ): array {
		$out = array();

		foreach ( is_array( $data ) && ! array_is_list( $data ) ? array_slice( $data, 0, 60, true ) : array() as $label => $text ) {
			if ( is_scalar( $text ) && '' !== trim( (string) $label ) ) {
				$out[ sanitize_text_field( (string) $label ) ] = sanitize_text_field( (string) $text );
			}
		}

		return $out;
	}

	/**
	 * O <evo-cartao> consegue tokenizar no navegador para esta filial?
	 *
	 * tokenizeBackend = true leva o componente ao fluxo com reCAPTCHA + JWT
	 * da sessao EVO, que este site nao tem. Tipo fora de FRONTEND_TYPES ou
	 * sem chave publica: nao ha como gerar token. Nos tres casos o checkout
	 * nativo NAO abre (fallback seguro, antes de qualquer venda).
	 *
	 * @param array<string,mixed> $config
	 */
	public static function supports_browser_tokenization( array $config ): bool {
		return empty( $config['tokenize_backend'] )
			&& in_array( (int) ( $config['gateway_type'] ?? -1 ), self::FRONTEND_TYPES, true )
			&& '' !== (string) ( $config['public_data']['publicKey'] ?? '' );
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
		$new  = array_diff( array_map( 'strval', array_keys( $data ) ), self::PUBLIC_KEYS, self::KNOWN_SERVER_KEYS );

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
	 * O que o navegador recebe: o objeto que o <evo-cartao> espera na
	 * propriedade `gateway` ({ tipoGateway, dadosGateway }), com
	 * dadosGateway ja reduzido a PUBLIC_KEYS, e a URL do componente.
	 *
	 * Bandeiras e traducoes (card_assets) vao junto, ja saneadas; vazias =
	 * o componente usa os padroes dele.
	 *
	 * @param array<string,mixed> $config
	 * @param array<string,mixed> $assets  saida de card_assets()
	 * @return array<string,mixed>
	 */
	public static function public_view( array $config, array $assets = array() ): array {
		$type = (int) ( $config['gateway_type'] ?? -1 );

		return array(
			'gatewayType'       => $type,
			'showCardType'      => (bool) ( $config['show_card_type'] ?? false ),
			'tokenizeBackend'   => (bool) ( $config['tokenize_backend'] ?? false ),
			'validationEnabled' => (bool) ( $config['validation_enabled'] ?? false ),
			'component'         => array(
				'tipoGateway'  => $type,
				'dadosGateway' => (object) ( $config['public_data'] ?? array() ),
			),
			'scriptUrl'         => Contorno_Evo_Settings::evopay_script(),
			'bandeiras'         => array_values( (array) ( $assets['bandeiras'] ?? array() ) ),
			// Objeto mesmo quando vazio: o componente espera dicionario.
			'traducoes'         => (object) ( $assets['traducoes'] ?? array() ),
		);
	}
}
