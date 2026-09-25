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
 *
 * RETRY E METODO: somente GET e reenviado automaticamente. POST /api/v2/sales
 * e POST /api/v1/prospects criam estado do outro lado; um retry cego
 * duplicaria venda ou cadastro. Quem decide reenviar e
 * Contorno_Evo_Checkout, depois de perguntar a EVO o que de fato aconteceu
 * (GET /api/v1/sales/by-session-id).
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Client {

	public const MIN_INTERVAL_US = 1600000; // 1,6 s
	public const TIMEOUT         = 20;
	public const TIMEOUT_WRITE   = 45;      // venda: o gateway entra no caminho
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

	/**
	 * UM membership especifico — a fonte de verdade do preco na hora da venda.
	 *
	 * idMembership e idBranch juntos: com chave multi-filial o mesmo nome de
	 * plano existe em varias filiais, e so o par identifica o contrato certo.
	 *
	 * @return array{ok:bool,message:string,http:int,item:?array<string,mixed>}
	 */
	public function membership( int $id_membership, ?int $id_branch = null ): array {
		$query = array( 'idMembership' => $id_membership, 'take' => 5 );

		if ( null !== $id_branch && $id_branch > 0 ) {
			$query['idBranch'] = $id_branch;
		}

		$result = $this->get( '/api/v3/membership', $query );

		if ( ! $result['ok'] ) {
			return array( 'ok' => false, 'message' => $result['message'], 'http' => $result['http'], 'item' => null );
		}

		$rows = is_array( $result['data'] ) ? array_values( array_filter( $result['data'], 'is_array' ) ) : array();
		$item = null;

		foreach ( $rows as $row ) {
			if ( (int) ( $row['idMembership'] ?? 0 ) !== $id_membership ) {
				continue;
			}

			// O filtro idBranch e ignorado em chave de filial unica; conferimos
			// de novo aqui para nao aceitar o plano homonimo de outra filial.
			if ( null !== $id_branch && $id_branch > 0 && (int) ( $row['idBranch'] ?? 0 ) !== $id_branch ) {
				continue;
			}

			$item = $row;
			break;
		}

		return array( 'ok' => true, 'message' => '', 'http' => $result['http'], 'item' => $item );
	}

	/**
	 * Configuracao REAL do gateway da filial (BranchGatewayViewModel).
	 *
	 * @return array{ok:bool,message:string,http:int,data:mixed}
	 */
	public function gateway( ?int $id_branch = null ): array {
		$query = array();

		if ( null !== $id_branch && $id_branch > 0 ) {
			$query['idBranch'] = $id_branch;
		}

		return $this->get( '/api/v2/configuration/gateway', $query );
	}

	/**
	 * Membro existente por e-mail, documento ou telefone.
	 *
	 * "basic" e a consulta SEM dados sensiveis — o que a permissao
	 * "Cliente - Consulta sem dados sensiveis" cobre. Nao usamos
	 * /api/v2/members justamente para nao precisar de permissao maior.
	 *
	 * @param array<string,mixed> $filters  email|document|phone|idMember|idBranch
	 * @return array{ok:bool,message:string,http:int,items:array<int,array<string,mixed>>}
	 */
	public function members_basic( array $filters ): array {
		$query = $this->pick( $filters, array( 'email', 'document', 'phone', 'idMember', 'idBranch' ) );

		if ( array() === $query ) {
			return array( 'ok' => false, 'message' => __( 'Consulta de membro sem filtro.', 'contorno-evo' ), 'http' => 0, 'items' => array() );
		}

		$query['take'] = 10;
		$result        = $this->get( '/api/v1/members/basic', $query );

		return array(
			'ok'      => $result['ok'],
			'message' => $result['message'],
			'http'    => $result['http'],
			'items'   => $result['ok'] && is_array( $result['data'] ) ? array_values( array_filter( $result['data'], 'is_array' ) ) : array(),
		);
	}

	/**
	 * Prospect existente por documento, e-mail ou telefone.
	 *
	 * @param array<string,mixed> $filters
	 * @return array{ok:bool,message:string,http:int,items:array<int,array<string,mixed>>}
	 */
	public function prospects( array $filters ): array {
		$query = $this->pick( $filters, array( 'idProspect', 'document', 'email', 'phone', 'idBranch' ) );

		if ( array() === $query ) {
			return array( 'ok' => false, 'message' => __( 'Consulta de prospect sem filtro.', 'contorno-evo' ), 'http' => 0, 'items' => array() );
		}

		$query['take'] = 10;
		$result        = $this->get( '/api/v1/prospects', $query );

		return array(
			'ok'      => $result['ok'],
			'message' => $result['message'],
			'http'    => $result['http'],
			'items'   => $result['ok'] && is_array( $result['data'] ) ? array_values( array_filter( $result['data'], 'is_array' ) ) : array(),
		);
	}

	/**
	 * idState da EVO por UF, cacheado — a tabela nao muda.
	 *
	 * A EVO nao documenta os nomes dos campos de /api/v2/states, entao
	 * aceitamos as grafias plausiveis em vez de apostar numa.
	 *
	 * @return array{ok:bool,message:string,http:int,states:array<string,int>}
	 */
	public function states(): array {
		$cached = get_transient( 'contorno_evo_states' );

		if ( is_array( $cached ) && array() !== $cached ) {
			return array( 'ok' => true, 'message' => '', 'http' => 200, 'states' => $cached );
		}

		$result = $this->get( '/api/v2/states', array() );
		$map    = array();

		if ( $result['ok'] && is_array( $result['data'] ) ) {
			foreach ( $result['data'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$id = (int) ( $row['idState'] ?? $row['id'] ?? 0 );
				$uf = strtoupper( trim( (string) ( $row['initials'] ?? $row['uf'] ?? $row['sigla'] ?? $row['acronym'] ?? '' ) ) );

				if ( $id > 0 && 2 === strlen( $uf ) ) {
					$map[ $uf ] = $id;
				}
			}
		}

		if ( array() !== $map ) {
			set_transient( 'contorno_evo_states', $map, WEEK_IN_SECONDS );
		}

		return array( 'ok' => $result['ok'], 'message' => $result['message'], 'http' => $result['http'], 'states' => $map );
	}

	/**
	 * Atividades da filial (FASE 24 — base para trocar o iframe da grade).
	 *
	 * @return array{ok:bool,message:string,http:int,items:array<int,array<string,mixed>>}
	 */
	public function activities( int $id_branch, int $take = 50, int $skip = 0, string $search = '' ): array {
		$query = array( 'idBranch' => $id_branch, 'take' => max( 1, min( 100, $take ) ), 'skip' => max( 0, $skip ) );

		if ( '' !== $search ) {
			$query['search'] = $search;
		}

		$result = $this->get( '/api/v1/activities', $query );

		return array(
			'ok'      => $result['ok'],
			'message' => $result['message'],
			'http'    => $result['http'],
			'items'   => $result['ok'] && is_array( $result['data'] ) ? array_values( array_filter( $result['data'], 'is_array' ) ) : array(),
		);
	}

	/**
	 * Grade horaria da filial.
	 *
	 * @param array<string,mixed> $extra  date, showFullWeek, onlyAvailables...
	 * @return array{ok:bool,message:string,http:int,items:array<int,array<string,mixed>>}
	 */
	public function activities_schedule( int $id_branch, array $extra = array() ): array {
		$query = array_merge(
			array( 'idBranch' => $id_branch ),
			$this->pick( $extra, array( 'date', 'showFullWeek', 'onlyAvailables', 'take', 'idActivities', 'idAudiences', 'experimentalClass' ) )
		);

		$result = $this->get( '/api/v1/activities/schedule', $query );

		return array(
			'ok'      => $result['ok'],
			'message' => $result['message'],
			'http'    => $result['http'],
			'items'   => $result['ok'] && is_array( $result['data'] ) ? array_values( array_filter( $result['data'], 'is_array' ) ) : array(),
		);
	}

	/* ---------------------------------------------------------------
	 * Endpoints — escrita
	 * ------------------------------------------------------------- */

	/**
	 * Cria prospect. POST /api/v1/prospects -> idProspect.
	 *
	 * @param array<string,mixed> $payload  ProspectApiIntegracaoViewModel
	 * @return array{ok:bool,message:string,http:int,id_prospect:int,data:mixed}
	 */
	public function create_prospect( array $payload ): array {
		$result = $this->post( '/api/v1/prospects', $payload );
		$data   = $result['data'];

		// A rota devolve o id como inteiro cru ou dentro de um objeto,
		// conforme a versao. Aceitamos as duas formas.
		$id = 0;
		if ( is_int( $data ) || ( is_string( $data ) && ctype_digit( $data ) ) ) {
			$id = (int) $data;
		} elseif ( is_array( $data ) ) {
			$id = (int) ( $data['idProspect'] ?? 0 );
		}

		return array(
			'ok'          => $result['ok'] && $id > 0,
			'message'     => $result['ok'] && $id <= 0
				? __( 'A EVO aceitou o cadastro mas não devolveu o idProspect.', 'contorno-evo' )
				: $result['message'],
			'http'        => $result['http'],
			'id_prospect' => $id,
			'data'        => $data,
		);
	}

	/**
	 * Cria a venda. POST /api/v2/sales.
	 *
	 * @param array<string,mixed> $payload  NewSaleViewModel
	 * @return array{ok:bool,message:string,http:int,data:mixed,ambiguous:bool}
	 */
	public function create_sale( array $payload ): array {
		$result = $this->post( '/api/v2/sales', $payload, self::TIMEOUT_WRITE );

		return array(
			'ok'        => $result['ok'],
			'message'   => $result['message'],
			'http'      => $result['http'],
			'data'      => $result['data'],
			// Timeout, 5xx e JSON invalido: a EVO PODE ter criado a venda.
			// Quem chamou nao deve reenviar nem cair para o checkout externo
			// antes de consultar sale_by_session().
			'ambiguous' => ! $result['ok'] && ( 0 === $result['http'] || $result['http'] >= 500 ),
		);
	}

	/**
	 * idSale de uma tentativa, pelo sessionId que NOS geramos.
	 *
	 * Unico caminho documentado para descobrir se um POST /sales que "falhou"
	 * na verdade criou a venda. 0 = nao existe venda para esta sessao.
	 *
	 * @return array{ok:bool,message:string,http:int,id_sale:int}
	 */
	public function sale_by_session( string $session_id ): array {
		$result = $this->get( '/api/v1/sales/by-session-id', array( 'sessionId' => $session_id ) );
		$id     = 0;

		if ( $result['ok'] ) {
			$data = $result['data'];

			if ( is_int( $data ) || ( is_string( $data ) && ctype_digit( $data ) ) ) {
				$id = (int) $data;
			} elseif ( is_array( $data ) ) {
				$id = (int) ( $data['idSale'] ?? 0 );
			}
		}

		return array( 'ok' => $result['ok'], 'message' => $result['message'], 'http' => $result['http'], 'id_sale' => $id );
	}

	/**
	 * Detalhe de uma venda (SalesViewModelV2).
	 *
	 * @return array{ok:bool,message:string,http:int,data:mixed}
	 */
	public function sale( int $id_sale ): array {
		return $this->get( '/api/v2/sales/' . $id_sale, array() );
	}

	/* ---------------------------------------------------------------
	 * HTTP
	 * ------------------------------------------------------------- */

	/**
	 * @param array<string,scalar> $query
	 * @return array{ok:bool,message:string,http:int,data:mixed}
	 */
	public function get( string $path, array $query = array() ): array {
		return $this->request( 'GET', $path, $query, null, self::TIMEOUT );
	}

	/**
	 * @param array<string,mixed> $body
	 * @return array{ok:bool,message:string,http:int,data:mixed}
	 */
	public function post( string $path, array $body, ?int $timeout = null ): array {
		return $this->request( 'POST', $path, array(), $body, $timeout ?? self::TIMEOUT );
	}

	/**
	 * @param array<string,scalar>     $query
	 * @param array<string,mixed>|null $body
	 * @return array{ok:bool,message:string,http:int,data:mixed}
	 */
	private function request( string $method, string $path, array $query, ?array $body, int $timeout ): array {
		if ( ! $this->is_configured() ) {
			return array( 'ok' => false, 'message' => __( 'Credenciais EVO não configuradas (DNS e token).', 'contorno-evo' ), 'http' => 0, 'data' => null );
		}

		$url = $this->base . '/' . ltrim( $path, '/' );
		if ( array() !== $query ) {
			$url = add_query_arg( array_map( 'strval', $query ), $url );
		}

		// Somente GET pode ser reenviado sem risco de duplicar estado.
		$idempotent = 'GET' === $method;
		$attempt    = 0;

		while ( true ) {
			++$attempt;
			$this->throttle();

			$args = array(
				'method'      => $method,
				'timeout'     => $timeout,
				'redirection' => 0,
				'user-agent'  => 'ContornoEvoSync/' . CONTORNO_EVO_VERSION . ' (WordPress; ' . home_url() . ')',
				'headers'     => array(
					'Accept'        => 'application/json',
					// Exigido pela documentacao da venda: define como a EVO
					// interpreta numero e data no payload.
					'culture'       => 'pt-BR',
					'Authorization' => 'Basic ' . base64_encode( $this->dns . ':' . $this->token ),
				),
			);

			if ( null !== $body ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = (string) wp_json_encode( $body );
			}

			// wp_safe_remote_request (e nao wp_remote_request): valida a URL
			// contra IPs privados/loopback e portas fora do padrao. Segunda
			// barreira depois da lista de hosts de Contorno_Evo_Settings.
			// redirection = 0: o cabecalho Authorization leva o token, e um
			// 302 da EVO o reenviaria para o destino do redirecionamento.
			$response = wp_safe_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				$code = (string) $response->get_error_code();
				$is_timeout = str_contains( strtolower( $response->get_error_message() ), 'timed out' ) || str_contains( $code, 'timeout' );

				if ( $idempotent && $attempt < self::MAX_RETRIES ) {
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

			$http     = (int) wp_remote_retrieve_response_code( $response );
			$body_raw = (string) wp_remote_retrieve_body( $response );

			if ( 429 === $http || ( $http >= 500 && $http <= 599 ) ) {
				if ( $idempotent && $attempt < self::MAX_RETRIES ) {
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
				return array(
					'ok'      => false,
					// 403 com credencial valida = permissao faltando na chave.
					// Separar os dois casos evita que a homologacao perca tempo
					// procurando token errado quando o que falta e permissao.
					'message' => 403 === $http
						? __( 'A EVO recusou a operação (HTTP 403). A chave autenticou, mas falta permissão para este endpoint.', 'contorno-evo' )
						: __( 'Falha na autenticação. Verifique DNS e token.', 'contorno-evo' ),
					'http'    => $http,
					'data'    => null,
				);
			}

			if ( $http < 200 || $http >= 300 ) {
				return array(
					'ok'      => false,
					'message' => $this->error_message( $http, $body_raw ),
					'http'    => $http,
					'data'    => null,
				);
			}

			// 204 / corpo vazio e sucesso sem payload.
			if ( '' === trim( $body_raw ) ) {
				return array( 'ok' => true, 'message' => '', 'http' => $http, 'data' => null );
			}

			$data = json_decode( $body_raw, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return array( 'ok' => false, 'message' => __( 'A EVO devolveu uma resposta que não é JSON válido.', 'contorno-evo' ), 'http' => $http, 'data' => null );
			}

			return array( 'ok' => true, 'message' => '', 'http' => $http, 'data' => $data );
		}
	}

	/**
	 * Mensagem de erro 4xx.
	 *
	 * A EVO responde HttpResponseError { mensagens: string[] } nos 400. O
	 * conteudo e tecnico e pode citar dados do payload, entao passa por
	 * Contorno_Evo_Log::sanitize e e truncado. O que o visitante ve e texto
	 * proprio de Contorno_Evo_Checkout — isto aqui e para o log.
	 */
	private function error_message( int $http, string $body ): string {
		$decoded = json_decode( $body, true );
		$detail  = '';

		if ( is_array( $decoded ) && ! empty( $decoded['mensagens'] ) && is_array( $decoded['mensagens'] ) ) {
			$detail = implode( '; ', array_map( 'strval', array_slice( $decoded['mensagens'], 0, 4 ) ) );
		}

		if ( '' === $detail ) {
			$detail = wp_strip_all_tags( $body );
		}

		return sprintf(
			/* translators: 1: http status, 2: excerpt */
			__( 'Resposta inesperada da EVO (HTTP %1$d): %2$s', 'contorno-evo' ),
			$http,
			Contorno_Evo_Log::sanitize( mb_substr( $detail, 0, 200 ) )
		);
	}

	/**
	 * Mantem so as chaves esperadas, descartando o resto.
	 *
	 * Barreira contra parameter pollution: nada que venha do navegador vira
	 * query string da EVO sem passar por uma lista fechada.
	 *
	 * @param array<string,mixed> $source
	 * @param array<int,string>   $allowed
	 * @return array<string,scalar>
	 */
	private function pick( array $source, array $allowed ): array {
		$out = array();

		foreach ( $allowed as $key ) {
			if ( ! isset( $source[ $key ] ) ) {
				continue;
			}

			$value = $source[ $key ];

			if ( is_bool( $value ) ) {
				$out[ $key ] = $value ? 'true' : 'false';
			} elseif ( is_scalar( $value ) && '' !== (string) $value ) {
				$out[ $key ] = is_int( $value ) ? $value : (string) $value;
			}
		}

		return $out;
	}

	/**
	 * Multiplicador do ritmo das chamadas.
	 *
	 * 1.0 e o ritmo real, calibrado nos limites publicados pela EVO (5 req/s,
	 * 40 req/min por IP). O filtro existe por dois motivos concretos:
	 *
	 *  - se a EVO afrouxar ou apertar os limites, muda-se aqui e nao no meio
	 *    da logica de retry;
	 *  - a suite de testes roda com 0 e nao gasta minutos dormindo. Um teste
	 *    que leva 3 minutos por causa de usleep deixa de ser rodado, e teste
	 *    que ninguem roda nao protege nada.
	 *
	 * Zero NUNCA deve ser usado em producao: sem intervalo, um sync grande
	 * toma 429 da EVO.
	 */
	private function pacing(): float {
		$factor = (float) apply_filters( 'contorno_evo_pacing', 1.0 );

		return $factor > 0 ? min( 10.0, $factor ) : 0.0;
	}

	private function throttle(): void {
		$factor   = $this->pacing();
		$interval = self::MIN_INTERVAL_US * $factor;

		if ( $interval <= 0 ) {
			return;
		}

		$elapsed = ( microtime( true ) - self::$last_call ) * 1000000;

		if ( self::$last_call > 0 && $elapsed < $interval ) {
			usleep( (int) ( $interval - $elapsed ) );
		}

		self::$last_call = microtime( true );
	}

	private function backoff( int $attempt, int $base_seconds = 2 ): void {
		$seconds = min( 60, $base_seconds * ( 2 ** ( $attempt - 1 ) ) ) * $this->pacing();

		if ( $seconds >= 1 ) {
			sleep( (int) $seconds );
		}
	}
}
