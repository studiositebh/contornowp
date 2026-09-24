<?php
/**
 * Cliente da EVO Integracao API.
 *
 * Contrato confirmado em api.abcevo.com (set/2026):
 *  - Base: https://evo-integracao-api.w12app.com.br
 *  - Auth: Basic base64(DNS:token)
 *  - GET /api/v3/membership  take<=50, skip, idBranch (so chave multi-filial),
 *    active, updateDate — devolve array de ContratosResumoApiViewModel
 *  - GET /api/v1/configuration  dados da filial (idBranch, name, ...)
 *  - GET /api/v1/configuration/group-branches  grupos + filiais (branchId, branchName)
 *  - Limites: 5 req/s e 40 req/min por IP, 10.000/h por chave; 429 ao estourar.
 *
 * Aqui: intervalo minimo de 1,6 s entre chamadas (<= 37/min), retry com
 * backoff em 429 e 5xx, timeout curto, validacao de JSON. O token NUNCA entra
 * em mensagens de erro nem em log.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Client {

	public const MIN_INTERVAL_US = 1600000; // 1,6 s
	public const TIMEOUT         = 20;
	public const MAX_RETRIES     = 3;
	public const PAGE_SIZE       = 50;

	private static float $last_call = 0.0;

	private string $dns;
	private string $token;
	private string $base;

	public function __construct( ?string $dns = null, ?string $token = null, ?string $base = null ) {
		$this->dns   = $dns ?? Contorno_Evo_Settings::dns();
		$this->token = $token ?? Contorno_Evo_Settings::token();
		$this->base  = Contorno_Evo_Settings::sanitize_base_url( $base ?? Contorno_Evo_Settings::base_url() );
	}

	public function is_configured(): bool {
		return '' !== $this->dns && '' !== $this->token;
	}

	/* ---------------------------------------------------------------
	 * Endpoints
	 * ------------------------------------------------------------- */

	/**
	 * Chamada minima para "Testar conexao": um membership qualquer.
	 *
	 * @return array{ok:bool,message:string,http:int,count?:int}
	 */
	public function test(): array {
		$result = $this->get( '/api/v3/membership', array( 'take' => 1 ) );

		if ( ! $result['ok'] ) {
			return array( 'ok' => false, 'message' => $result['message'], 'http' => $result['http'] );
		}

		$data = $result['data'];

		return array(
			'ok'      => true,
			'message' => __( 'Conexão realizada com sucesso.', 'contorno-evo' ),
			'http'    => $result['http'],
			'count'   => is_array( $data ) ? count( $data ) : 0,
		);
	}

	/**
	 * Todas as paginas de /api/v3/membership (opcionalmente de uma filial).
	 *
	 * @return array{ok:bool,message:string,http:int,items:array<int,array<string,mixed>>,pages:int}
	 */
	public function memberships( ?int $id_branch = null, ?string $update_since = null ): array {
		$items = array();
		$skip  = 0;
		$pages = 0;

		do {
			$query = array( 'take' => self::PAGE_SIZE, 'skip' => $skip );

			if ( null !== $id_branch ) {
				$query['idBranch'] = $id_branch;
			}
			if ( null !== $update_since && '' !== $update_since ) {
				$query['updateDate'] = $update_since;
			}

			$result = $this->get( '/api/v3/membership', $query );
			++$pages;

			if ( ! $result['ok'] ) {
				return array( 'ok' => false, 'message' => $result['message'], 'http' => $result['http'], 'items' => $items, 'pages' => $pages );
			}

			$batch = is_array( $result['data'] ) ? array_values( array_filter( $result['data'], 'is_array' ) ) : array();
			$items = array_merge( $items, $batch );
			$skip += self::PAGE_SIZE;

			// Guarda contra loop infinito (api devolvendo sempre pagina cheia).
			if ( $pages > 200 ) {
				break;
			}
		} while ( count( $batch ) >= self::PAGE_SIZE );

		return array( 'ok' => true, 'message' => '', 'http' => 200, 'items' => $items, 'pages' => $pages );
	}

	/**
	 * Filiais visiveis pela chave (grupos -> filiais). Funciona com chave de
	 * filial e com chave de ADM geral, segundo a documentacao.
	 *
	 * @return array{ok:bool,message:string,http:int,branches:array<int,array{id:int,name:string,group:string}>}
	 */
	public function branches(): array {
		$result   = $this->get( '/api/v1/configuration/group-branches', array() );
		$branches = array();

		if ( $result['ok'] && is_array( $result['data'] ) ) {
			foreach ( $result['data'] as $group ) {
				if ( ! is_array( $group ) ) {
					continue;
				}
				foreach ( (array) ( $group['branches'] ?? array() ) as $branch ) {
					if ( ! is_array( $branch ) || empty( $branch['branchId'] ) ) {
						continue;
					}
					$branches[ (int) $branch['branchId'] ] = array(
						'id'    => (int) $branch['branchId'],
						'name'  => (string) ( $branch['branchName'] ?? '' ),
						'group' => (string) ( $group['groupName'] ?? '' ),
					);
				}
			}
		}

		// Chave de filial unica: group-branches pode vir vazio; /configuration da a propria filial.
		if ( $result['ok'] && array() === $branches ) {
			$config = $this->get( '/api/v1/configuration', array() );

			if ( $config['ok'] && is_array( $config['data'] ) ) {
				foreach ( $config['data'] as $row ) {
					if ( is_array( $row ) && ! empty( $row['idBranch'] ) ) {
						$branches[ (int) $row['idBranch'] ] = array(
							'id'    => (int) $row['idBranch'],
							'name'  => (string) ( $row['name'] ?? $row['internalName'] ?? '' ),
							'group' => '',
						);
					}
				}
			}
		}

		ksort( $branches );

		return array( 'ok' => $result['ok'], 'message' => $result['message'], 'http' => $result['http'], 'branches' => array_values( $branches ) );
	}

	/* ---------------------------------------------------------------
	 * HTTP
	 * ------------------------------------------------------------- */

	/**
	 * @param array<string,scalar> $query
	 * @return array{ok:bool,message:string,http:int,data:mixed}
	 */
	public function get( string $path, array $query = array() ): array {
		if ( ! $this->is_configured() ) {
			return array( 'ok' => false, 'message' => __( 'Credenciais EVO não configuradas (DNS e token).', 'contorno-evo' ), 'http' => 0, 'data' => null );
		}

		$url = $this->base . '/' . ltrim( $path, '/' );
		if ( array() !== $query ) {
			$url = add_query_arg( array_map( 'strval', $query ), $url );
		}

		$attempt = 0;

		while ( true ) {
			++$attempt;
			$this->throttle();

			// wp_safe_remote_get (e nao wp_remote_get): valida a URL contra
			// IPs privados/loopback e portas fora do padrao. Segunda barreira
			// depois da lista de hosts de Contorno_Evo_Settings.
			// redirection = 0: o cabecalho Authorization leva o token, e um
			// 302 da EVO o reenviaria para o destino do redirecionamento.
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'     => self::TIMEOUT,
					'redirection' => 0,
					'user-agent'  => 'ContornoEvoSync/' . CONTORNO_EVO_VERSION . ' (WordPress; ' . home_url() . ')',
					'headers'     => array(
						'Accept'        => 'application/json',
						'Authorization' => 'Basic ' . base64_encode( $this->dns . ':' . $this->token ),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$code = (string) $response->get_error_code();
				$is_timeout = str_contains( strtolower( $response->get_error_message() ), 'timed out' ) || str_contains( $code, 'timeout' );

				if ( $attempt < self::MAX_RETRIES ) {
					$this->backoff( $attempt );
					continue;
				}

				return array(
					'ok'      => false,
					'message' => $is_timeout
						? __( 'API EVO não respondeu a tempo (timeout).', 'contorno-evo' )
						: sprintf( /* translators: %s: error */ __( 'Falha de rede ao acessar a API EVO: %s', 'contorno-evo' ), Contorno_Evo_Log::sanitize( $response->get_error_message() ) ),
					'http'    => 0,
					'data'    => null,
				);
			}

			$http = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );

			if ( 429 === $http || ( $http >= 500 && $http <= 599 ) ) {
				if ( $attempt < self::MAX_RETRIES ) {
					$this->backoff( $attempt, 429 === $http ? 15 : 2 );
					continue;
				}

				return array(
					'ok'      => false,
					'message' => 429 === $http
						? __( 'Limite de requisições da EVO atingido (HTTP 429). Tente novamente em alguns minutos.', 'contorno-evo' )
						: sprintf( /* translators: %d: http status */ __( 'API EVO indisponível. HTTP %d.', 'contorno-evo' ), $http ),
					'http'    => $http,
					'data'    => null,
				);
			}

			if ( 401 === $http || 403 === $http ) {
				return array( 'ok' => false, 'message' => __( 'Falha na autenticação. Verifique DNS e token.', 'contorno-evo' ), 'http' => $http, 'data' => null );
			}

			if ( $http < 200 || $http >= 300 ) {
				return array(
					'ok'      => false,
					'message' => sprintf( /* translators: 1: http status, 2: excerpt */ __( 'Resposta inesperada da EVO (HTTP %1$d): %2$s', 'contorno-evo' ), $http, Contorno_Evo_Log::sanitize( mb_substr( wp_strip_all_tags( $body ), 0, 160 ) ) ),
					'http'    => $http,
					'data'    => null,
				);
			}

			$data = json_decode( $body, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return array( 'ok' => false, 'message' => __( 'A EVO devolveu uma resposta que não é JSON válido.', 'contorno-evo' ), 'http' => $http, 'data' => null );
			}

			return array( 'ok' => true, 'message' => '', 'http' => $http, 'data' => $data );
		}
	}

	private function throttle(): void {
		$now     = microtime( true );
		$elapsed = ( $now - self::$last_call ) * 1000000;

		if ( self::$last_call > 0 && $elapsed < self::MIN_INTERVAL_US ) {
			usleep( (int) ( self::MIN_INTERVAL_US - $elapsed ) );
		}

		self::$last_call = microtime( true );
	}

	private function backoff( int $attempt, int $base_seconds = 2 ): void {
		sleep( min( 60, $base_seconds * ( 2 ** ( $attempt - 1 ) ) ) );
	}
}
