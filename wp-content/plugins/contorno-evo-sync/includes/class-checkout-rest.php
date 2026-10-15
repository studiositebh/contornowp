<?php
/**
 * API interna do checkout nativo.
 *
 * O contorno-core desenha as etapas; toda conversa com a EVO acontece aqui.
 * O tema e o plugin de apresentacao nunca montam uma chamada a EVO, nunca
 * veem DNS nem token, e nunca recebem preco pelo navegador para reenviar.
 *
 * Rotas (namespace contorno-evo/v1):
 *
 *   POST /checkout/open      selecao -> token de sessao + resumo oficial
 *   POST /checkout/identify  e-mail/CPF/telefone -> membro | prospect | novo
 *   POST /checkout/lead      nome completo, e-mail, WhatsApp, aceite -> prospect na EVO (1a etapa)
 *   POST /checkout/pay       token + dados + cartao tokenizado -> venda
 *   POST /checkout/status    token -> situacao (para reabrir a aba)
 *
 * DEFESAS, e por que cada uma existe:
 *
 *  - Mesma origem obrigatoria. Para visitante deslogado o nonce do
 *    WordPress e calculado com uid 0 e sem token de sessao: ele e o MESMO
 *    para todo mundo e qualquer pessoa consegue um carregando a pagina. Ou
 *    seja, nonce sozinho nao e protecao de CSRF aqui. A barreira real e
 *    Origin/Referer da propria casa + o token opaco de sessao, que so existe
 *    no navegador que abriu o checkout.
 *  - Nonce mantido de todo modo: derruba requisicao repetida de link antigo
 *    e mantem o formulario coerente com o resto do site.
 *  - Honeypot herdado do formulario atual.
 *  - Rate limit por IP, com janela diferente por rota: consultar quem a
 *    pessoa e e barato, criar prospect e tentar venda nao sao.
 *  - Nenhuma rota aceita preco, idBranch, idMembership, checkout ou URL.
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Checkout_Rest {

	public const NAMESPACE  = 'contorno-evo/v1';
	public const NONCE      = 'contorno_evo_checkout';

	/** Limites por IP: [ tentativas, janela em segundos ]. */
	private const LIMITS = array(
		'open'     => array( 30, 300 ),
		'identify' => array( 20, 300 ),
		// Lead: cada envio cria cadastro na EVO. Folga para corrigir o e-mail,
		// aperto para nao virar gerador de prospect.
		'lead'     => array( 8, 900 ),
		// Venda: generoso o bastante para quem errou o cartao duas vezes e
		// apertado o bastante para nao servir de teste de cartao roubado.
		'pay'      => array( 6, 900 ),
		'status'   => array( 60, 300 ),
	);

	public static function boot(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes(): void {
		$common = array(
			'permission_callback' => array( __CLASS__, 'allow' ),
			'methods'             => 'POST',
		);

		register_rest_route( self::NAMESPACE, '/checkout/open', $common + array( 'callback' => array( __CLASS__, 'open' ) ) );
		register_rest_route( self::NAMESPACE, '/checkout/identify', $common + array( 'callback' => array( __CLASS__, 'identify' ) ) );
		register_rest_route( self::NAMESPACE, '/checkout/lead', $common + array( 'callback' => array( __CLASS__, 'lead' ) ) );
		register_rest_route( self::NAMESPACE, '/checkout/pay', $common + array( 'callback' => array( __CLASS__, 'pay' ) ) );
		register_rest_route( self::NAMESPACE, '/checkout/status', $common + array( 'callback' => array( __CLASS__, 'status' ) ) );
	}

	/**
	 * Rota publica: o visitante nao tem login. O controle de acesso real e
	 * same_origin() + nonce + token de sessao, verificados em cada callback.
	 */
	public static function allow(): bool {
		return true;
	}

	/* ---------------------------------------------------------------
	 * Barreiras
	 * ------------------------------------------------------------- */

	private static function same_origin(): bool {
		$home   = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$origin = strtolower( (string) wp_parse_url( (string) ( $_SERVER['HTTP_ORIGIN'] ?? '' ), PHP_URL_HOST ) );

		if ( '' !== $origin ) {
			return $origin === $home;
		}

		// Sem Origin (navegador antigo, alguns proxies): cai para o Referer.
		// Ausencia dos dois e recusa — o fluxo legitimo sempre manda um.
		$referer = strtolower( (string) wp_parse_url( (string) wp_get_raw_referer(), PHP_URL_HOST ) );

		return '' !== $referer && $referer === $home;
	}

	/**
	 * @return array{ok:bool,code:string}
	 */
	/**
	 * Nonce do checkout, INDEPENDENTE de login.
	 *
	 * wp_create_nonce() amarra o valor ao usuario logado. A pagina e
	 * renderizada com o cookie de login (admin testando: usuario 1), mas a
	 * rota REST e chamada sem o cabecalho X-WP-Nonce, e o WordPress a trata
	 * como anonima (usuario 0): o nonce nunca conferia para quem estava
	 * logado, e o checkout respondia "sessao expirou" (12/10/2026).
	 *
	 * Mesmo algoritmo e mesma validade do nonce do WordPress para visitante
	 * anonimo (uid 0, sem token de sessao), so que fixo nesse contexto.
	 */
	public static function create_nonce(): string {
		return self::nonce_for( (int) wp_nonce_tick( self::NONCE ) );
	}

	public static function verify_nonce( string $nonce ): bool {
		$tick = (int) wp_nonce_tick( self::NONCE );

		// Tick atual ou o anterior: mesma janela (12-24 h) do wp_verify_nonce.
		foreach ( array( $tick, $tick - 1 ) as $candidate ) {
			if ( hash_equals( self::nonce_for( $candidate ), $nonce ) ) {
				return true;
			}
		}

		return false;
	}

	private static function nonce_for( int $tick ): string {
		return substr( wp_hash( $tick . '|' . self::NONCE . '|0|', 'nonce' ), -12, 10 );
	}

	private static function guard( WP_REST_Request $request, string $scope ): array {
		if ( ! self::same_origin() ) {
			return array( 'ok' => false, 'code' => 'dados_invalidos' );
		}

		$nonce = sanitize_text_field( (string) $request->get_param( 'nonce' ) );

		if ( '' === $nonce || ! self::verify_nonce( $nonce ) ) {
			return array( 'ok' => false, 'code' => 'sessao_expirada' );
		}

		// Honeypot: o campo existe escondido no formulario; humano nao o
		// preenche. Recusa generica, sem dizer o motivo.
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			return array( 'ok' => false, 'code' => 'dados_invalidos' );
		}

		if ( ! self::within_limit( $scope ) ) {
			return array( 'ok' => false, 'code' => 'limite' );
		}

		return array( 'ok' => true, 'code' => '' );
	}

	/**
	 * Contador por IP e por rota.
	 *
	 * Conta TENTATIVAS, nao bloqueia no primeiro erro: quem digitou o numero
	 * do cartao errado uma vez e cliente, nao atacante.
	 */
	private static function within_limit( string $scope ): bool {
		list( $max, $window ) = self::LIMITS[ $scope ] ?? array( 20, 300 );

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( '' === $ip ) {
			return true; // sem IP identificavel nao ha o que limitar
		}

		$key   = 'contorno_evo_rl_' . $scope . '_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return false;
		}

		set_transient( $key, $count + 1, $window );

		return true;
	}

	/**
	 * @param array<string,mixed> $extra
	 */
	private static function fail( string $code, array $extra = array() ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'error'   => $code,
				'message' => Contorno_Evo_Checkout::public_message( $code ),
			) + $extra,
			200 // erro de negocio, nao de protocolo: o front trata pelo campo `error`
		);
	}

	/* ---------------------------------------------------------------
	 * Rotas
	 * ------------------------------------------------------------- */

	public static function open( WP_REST_Request $request ): WP_REST_Response {
		$guard = self::guard( $request, 'open' );

		if ( ! $guard['ok'] ) {
			return self::fail( $guard['code'] );
		}

		$result = Contorno_Evo_Checkout::open(
			(string) $request->get_param( 'slug' ),
			(string) $request->get_param( 'plan' )
		);

		if ( ! $result['ok'] ) {
			// Toda recusa AQUI e antes de existir qualquer venda, entao o
			// checkout externo continua sendo um destino seguro.
			return self::fail( $result['error'], array( 'fallback' => true ) );
		}

		$state   = $result['state'];
		$gateway = Contorno_Evo_Gateway::fetch( (int) $state['id_branch'] );

		if ( ! $gateway['ok'] ) {
			Contorno_Evo_Checkout::forget( $result['token'] );
			Contorno_Evo_Checkout::log( 'warning', 'gateway indisponivel; checkout nativo nao abriu', $state );

			return self::fail( 'evo_indisponivel', array( 'fallback' => true ) );
		}

		// Sem tokenizacao no navegador nao existe caminho seguro de cartao.
		if ( ! Contorno_Evo_Gateway::supports_browser_tokenization( $gateway['config'] ) ) {
			Contorno_Evo_Checkout::forget( $result['token'] );
			Contorno_Evo_Checkout::log(
				'warning',
				sprintf(
					'gateway sem tokenizacao no navegador (tipo %d, tokenizeBackend %s); checkout nativo nao abriu',
					(int) ( $gateway['config']['gateway_type'] ?? -1 ),
					empty( $gateway['config']['tokenize_backend'] ) ? 'false' : 'true'
				),
				$state
			);

			return self::fail( 'evo_indisponivel', array( 'fallback' => true ) );
		}

		// Antifraude ligado: o checkout da EVO torna o endereco obrigatorio.
		// Sem o passo de endereco aqui, a venda seria recusada (ou pior,
		// aprovada sem os dados que o antifraude pede). Nao abre.
		if ( ! empty( $gateway['config']['antifraud'] ) && ! Contorno_Evo_Settings::get( 'checkout_require_address', false ) ) {
			Contorno_Evo_Checkout::forget( $result['token'] );
			Contorno_Evo_Checkout::log( 'warning', 'gateway com antifraude exige endereco; ligue "Pedir CEP e endereco" nas configuracoes', $state );

			return self::fail( 'evo_indisponivel', array( 'fallback' => true ) );
		}

		return new WP_REST_Response(
			array(
				'ok'             => true,
				'token'          => $result['token'],
				'summary'        => self::summary( $state ),
				'gateway'        => Contorno_Evo_Gateway::public_view( $gateway['config'], Contorno_Evo_Gateway::card_assets() ),
				'requiresAddress' => (bool) Contorno_Evo_Settings::get( 'checkout_require_address', false ),
			),
			200
		);
	}

	public static function identify( WP_REST_Request $request ): WP_REST_Response {
		$guard = self::guard( $request, 'identify' );

		if ( ! $guard['ok'] ) {
			return self::fail( $guard['code'] );
		}

		$state = Contorno_Evo_Checkout::load( (string) $request->get_param( 'token' ) );

		if ( null === $state ) {
			return self::fail( 'sessao_expirada', array( 'fallback' => true ) );
		}

		$found = Contorno_Evo_Checkout::identify(
			array(
				'document' => (string) $request->get_param( 'document' ),
				'email'    => (string) $request->get_param( 'email' ),
				'phone'    => (string) $request->get_param( 'phone' ),
			),
			(int) $state['id_branch']
		);

		if ( ! $found['ok'] ) {
			return self::fail( $found['error'], array( 'fallback' => 'evo_indisponivel' === $found['error'] ) );
		}

		// So os IDENTIFICADORES ficam na sessao. O e-mail e o CPF usados na
		// consulta nao sao gravados.
		$state['id_member']   = $found['id_member'];
		$state['id_prospect'] = $found['id_prospect'];
		$state['status']      = 'identified';
		Contorno_Evo_Checkout::save( $state );

		return new WP_REST_Response(
			array(
				'ok'   => true,
				// "member" so informa que o cadastro ja existe; nao devolvemos
				// nada do cadastro da pessoa para quem digitou o CPF.
				'kind' => $found['kind'],
			),
			200
		);
	}

	/**
	 * Primeira etapa: registra o LEAD na EVO antes de qualquer pagamento.
	 *
	 * Quem desiste no meio da matricula continua na base da unidade
	 * (prospect com marketingType "Site" e currentStep "Lead site"). Nada
	 * fica guardado no WordPress: na sessao entram so os IDs da EVO.
	 *
	 * Ja e aluno ou ja e prospect (por e-mail/WhatsApp): reaproveita, nao
	 * cria outro. Reenvio da mesma sessao: nao cria de novo.
	 */
	public static function lead( WP_REST_Request $request ): WP_REST_Response {
		$guard = self::guard( $request, 'lead' );

		if ( ! $guard['ok'] ) {
			return self::fail( $guard['code'] );
		}

		$state = Contorno_Evo_Checkout::load( (string) $request->get_param( 'token' ) );

		if ( null === $state ) {
			return self::fail( 'sessao_expirada', array( 'fallback' => true ) );
		}

		if ( ! empty( $state['lead'] ) && ( (int) $state['id_prospect'] > 0 || (int) $state['id_member'] > 0 ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'kind' => (int) $state['id_member'] > 0 ? 'member' : 'prospect' ), 200 );
		}

		$name  = Contorno_Evo_Checkout::split_name( (string) $request->get_param( 'fullName' ) );
		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		$phone = Contorno_Evo_Checkout::normalize_phone( (string) $request->get_param( 'phone' ) );

		if ( null === $name
			|| '' === $email || ! is_email( $email ) || mb_strlen( $email ) > 254
			|| ! Contorno_Evo_Checkout::valid_phone( $phone )
			// LGPD: o contato da unidade depende deste aceite.
			|| empty( $request->get_param( 'consent' ) )
		) {
			return self::fail( 'dados_invalidos' );
		}

		$found = Contorno_Evo_Checkout::identify( array( 'email' => $email, 'phone' => $phone ), (int) $state['id_branch'] );

		if ( ! $found['ok'] ) {
			return self::fail( $found['error'], array( 'fallback' => 'evo_indisponivel' === $found['error'] ) );
		}

		$kind = $found['kind'];

		if ( 'new' === $kind ) {
			$created = ( new Contorno_Evo_Client() )->create_prospect(
				Contorno_Evo_Checkout::lead_payload(
					array( 'first_name' => $name[0], 'last_name' => $name[1], 'email' => $email, 'phone' => $phone ),
					(int) $state['id_branch'],
					(string) ( $state['quote']['name'] ?? '' )
				)
			);

			if ( ! $created['ok'] ) {
				Contorno_Evo_Checkout::log( 'error', 'falha ao registrar lead: ' . $created['message'], $state + array( 'http' => $created['http'] ) );

				return self::fail( 400 === $created['http'] ? 'dados_invalidos' : 'evo_indisponivel', array( 'fallback' => 400 !== $created['http'] ) );
			}

			$found['id_prospect'] = $created['id_prospect'];
			$found['branch']      = (int) $state['id_branch'];
			$kind                 = 'prospect';
			$ignored = (int) ( $found['ignored'] ?? 0 );
			Contorno_Evo_Checkout::log(
				$ignored > 0 ? 'warning' : 'info',
				sprintf( 'lead registrado na EVO (prospect novo %d)', (int) $created['id_prospect'] )
					. ( $ignored > 0 ? sprintf( ' — a busca da EVO devolveu %d cadastro(s) que NAO tem este e-mail/WhatsApp; ignorados', $ignored ) : '' ),
				$state
			);
		} else {
			// Cadastro ja existia (busca por e-mail/WhatsApp na rede toda):
			// nenhum prospect novo e criado. O log precisa dizer qual e de
			// que filial — venda para cadastro de outra filial a EVO recusa.
			$branch = (int) ( $found['branch'] ?? 0 );
			Contorno_Evo_Checkout::log(
				$branch > 0 && $branch !== (int) $state['id_branch'] ? 'warning' : 'info',
				sprintf(
					'lead: cadastro existente reaproveitado, nenhum prospect novo criado (%s %d, filial %s)%s',
					'member' === $kind ? 'aluno idMember' : 'prospect idProspect',
					'member' === $kind ? (int) $found['id_member'] : (int) $found['id_prospect'],
					$branch > 0 ? (string) $branch : '?',
					$branch > 0 && $branch !== (int) $state['id_branch'] ? ' — OUTRA FILIAL: a EVO recusa vender este contrato para cadastro de outra filial' : ''
				),
				$state
			);

			// Decisao de 15/10/2026: cadastro de OUTRA filial para aqui, ja na
			// 1a etapa — a EVO recusa a venda deste contrato e a pessoa so
			// descobriria depois de digitar o cartao. Nada e alterado na EVO
			// (sem transferencia automatica); o contato da unidade resolve.
			if ( $branch > 0 && $branch !== (int) $state['id_branch'] ) {
				return self::fail( 'cadastro_outra_filial', array( 'fallback' => true ) );
			}
		}

		$state['person_kind']   = 'member' === $kind ? 'member' : 'prospect';
		$state['person_branch'] = (int) ( $found['branch'] ?? 0 );
		$state['lead']        = true;
		$state['id_member']   = (int) $found['id_member'];
		$state['id_prospect'] = (int) $found['id_prospect'];
		$state['status']      = 'identified';
		Contorno_Evo_Checkout::save( $state );

		// Nenhum dado do cadastro volta para o navegador, so o tipo.
		return new WP_REST_Response( array( 'ok' => true, 'kind' => 'member' === $kind ? 'member' : 'prospect' ), 200 );
	}

	public static function status( WP_REST_Request $request ): WP_REST_Response {
		$guard = self::guard( $request, 'status' );

		if ( ! $guard['ok'] ) {
			return self::fail( $guard['code'] );
		}

		$state = Contorno_Evo_Checkout::load( (string) $request->get_param( 'token' ) );

		if ( null === $state ) {
			return self::fail( 'sessao_expirada' );
		}

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'status'  => (string) $state['status'],
				'summary' => self::summary( $state ),
			),
			200
		);
	}

	/**
	 * Fecha a venda.
	 *
	 * Ordem deliberada: trava -> reconferir preco -> validar dados ->
	 * recusar cartao bruto -> identificar/criar prospect -> POST /sales.
	 * A criacao de prospect acontece o mais tarde possivel: se o pagamento
	 * nao vai nem ser tentado, nao ha por que deixar cadastro pela metade na
	 * base da Contorno.
	 */
	public static function pay( WP_REST_Request $request ): WP_REST_Response {
		$guard = self::guard( $request, 'pay' );

		if ( ! $guard['ok'] ) {
			return self::fail( $guard['code'] );
		}

		$token = (string) $request->get_param( 'token' );
		$state = Contorno_Evo_Checkout::load( $token );

		if ( null === $state ) {
			return self::fail( 'sessao_expirada', array( 'fallback' => true ) );
		}

		// Reentrada: venda ja concluida nesta sessao nao vira segunda venda.
		if ( 'success' === $state['status'] ) {
			return self::done( $state, 'ja_concluida' );
		}

		if ( 'unknown' === $state['status'] ) {
			return self::fail( 'indeterminado' );
		}

		if ( ! Contorno_Evo_Settings::checkout_enabled_for( (string) $state['slug'] ) ) {
			return self::fail( 'nativo_desligado', array( 'fallback' => true ) );
		}

		if ( ! Contorno_Evo_Checkout::acquire_lock( $token ) ) {
			return self::fail( 'em_andamento' );
		}

		try {
			// 1. Preco de novo, agora. Entre abrir o checkout e pagar a
			// promocao pode ter acabado; cobrar o valor antigo seria cobrar o
			// que a EVO nao autoriza mais.
			$fresh = Contorno_Evo_Checkout::quote( (int) $state['id_membership'], (int) $state['id_branch'] );

			if ( ! $fresh['ok'] ) {
				Contorno_Evo_Checkout::log( 'warning', 'reconferencia de preco falhou: ' . $fresh['error'], $state );

				return self::fail( $fresh['error'], array( 'fallback' => true ) );
			}

			$before = (float) ( $state['quote']['first_value'] ?? 0 );
			$after  = (float) $fresh['quote']['first_value'];

			if ( abs( $before - $after ) > 0.004 ) {
				$state['quote']  = $fresh['quote'];
				$state['status'] = 'identified';
				Contorno_Evo_Checkout::save( $state );
				Contorno_Evo_Checkout::log( 'warning', 'preco mudou durante o checkout; venda nao enviada', $state );

				return self::fail( 'preco_mudou', array( 'summary' => self::summary( $state ) ) );
			}

			$state['quote'] = $fresh['quote'];

			// 2. Dados da pessoa.
			// Sessao com lead (1a etapa): nome, e-mail e WhatsApp ja estao na EVO.
			$person = self::person( $request, ! empty( $state['lead'] ) );

			if ( ! $person['ok'] ) {
				return self::fail( $person['code'] );
			}

			// 2b. Esta pessoa ja comprou este plano nos ultimos minutos?
			//
			// A trava acima protege UMA sessao. Recarregar a pagina abre outra,
			// com sessionId novo, e a EVO aceitaria a segunda venda sem
			// reclamar. A impressao digital (CPF + filial + contrato, em HMAC)
			// e o que impede pagar duas vezes por caminhos diferentes.
			$fingerprint = Contorno_Evo_Checkout::fingerprint(
				$person['data']['document'],
				(int) $state['id_branch'],
				(int) $state['id_membership']
			);

			$already = Contorno_Evo_Checkout::done_by( $fingerprint );

			if ( '' !== $already && $already !== $token ) {
				$previous = Contorno_Evo_Checkout::load( $already );
				Contorno_Evo_Checkout::log( 'warning', 'segunda tentativa de compra do mesmo plano pela mesma pessoa; venda nao repetida', $state );

				return self::done( null !== $previous ? $previous : $state, 'ja_concluida' );
			}

			// Guardado na sessao para succeed() poder marcar a compra. E um
			// HMAC, nao o CPF — a sessao continua sem dado pessoal.
			$state['fingerprint'] = $fingerprint;

			// 3. Cartao: so token. Nada bruto entra.
			$card = (array) $request->get_param( 'card' );

			if ( Contorno_Evo_Checkout::rejects_raw_card( $card ) ) {
				// Nao registramos NADA do conteudo — so que aconteceu.
				Contorno_Evo_Checkout::log( 'error', 'payload de cartao recusado: dado bruto onde so deveria vir token', $state );

				return self::fail( 'dados_invalidos' );
			}

			if ( '' === trim( (string) ( $card['token'] ?? '' ) ) && '' === trim( (string) ( $card['temporaryToken'] ?? '' ) ) ) {
				return self::fail( 'cartao_invalido' );
			}

			$installments = max( 1, min( (int) $state['quote']['max_installments'], (int) $request->get_param( 'installments' ) ) );

			// 4a. Lead da 1a etapa: completa o MESMO cadastro com o CPF (ou
			// descobre que o CPF e de um aluno). Nada de prospect novo.
			if ( ! empty( $state['lead'] ) && (int) $state['id_member'] <= 0 && (int) $state['id_prospect'] > 0 ) {
				$completed = Contorno_Evo_Checkout::complete_lead( $state, $person['data'] );

				if ( ! $completed['ok'] ) {
					return self::fail( $completed['error'], array( 'fallback' => 'evo_indisponivel' === $completed['error'] ) );
				}

				$state['id_member']   = $completed['id_member'];
				$state['id_prospect'] = $completed['id_prospect'];
			}

			// 4. Quem e a pessoa, se ainda nao sabemos.
			if ( (int) $state['id_member'] <= 0 && (int) $state['id_prospect'] <= 0 ) {
				$found = Contorno_Evo_Checkout::identify( $person['data'], (int) $state['id_branch'] );

				if ( ! $found['ok'] ) {
					return self::fail( $found['error'], array( 'fallback' => true ) );
				}

				$state['id_member']   = $found['id_member'];
				$state['id_prospect'] = $found['id_prospect'];
			}

			// 5. Prospect novo, so agora.
			if ( (int) $state['id_member'] <= 0 && (int) $state['id_prospect'] <= 0 ) {
				$client  = new Contorno_Evo_Client();
				$created = $client->create_prospect(
					Contorno_Evo_Checkout::prospect_payload( $person['data'], (int) $state['id_branch'] )
				);

				if ( ! $created['ok'] ) {
					Contorno_Evo_Checkout::log( 'error', 'falha ao criar prospect: ' . $created['message'], $state + array( 'http' => $created['http'] ) );

					// Prospect nao criado = nenhuma venda criada. Fallback seguro.
					return self::fail( 400 === $created['http'] ? 'dados_invalidos' : 'evo_indisponivel', array( 'fallback' => true ) );
				}

				$state['id_prospect'] = $created['id_prospect'];
			}

			// 6. Venda. Daqui em diante NAO existe fallback automatico.
			$state['status']    = 'processing';
			$state['attempts']  = (int) $state['attempts'] + 1;
			Contorno_Evo_Checkout::save( $state );

			$client = new Contorno_Evo_Client();
			$sale   = $client->create_sale(
				Contorno_Evo_Checkout::sale_payload( $state, $card, $person['data'], $installments )
			);

			if ( $sale['ok'] ) {
				return self::succeed( $state, $sale['data'], $client );
			}

			// 7. Resposta ambigua: a venda PODE ter sido criada. Perguntamos a
			// EVO pelo sessionId que nos geramos, em vez de reenviar ou de
			// jogar a pessoa no checkout externo — as duas coisas cobrariam
			// duas vezes.
			if ( $sale['ambiguous'] ) {
				$probe = $client->sale_by_session( (string) $state['session_id'] );

				if ( $probe['ok'] && $probe['id_sale'] > 0 ) {
					Contorno_Evo_Checkout::log( 'warning', 'venda confirmada por by-session-id apos resposta ambigua', $state + array( 'http' => $sale['http'] ) );

					return self::succeed( $state, array( 'idSale' => $probe['id_sale'] ), $client );
				}

				if ( ! $probe['ok'] ) {
					// Nao sabemos e nao conseguimos descobrir. Sem fallback e
					// sem retry: melhor um atendimento humano que uma cobranca
					// duplicada.
					$state['status'] = 'unknown';
					Contorno_Evo_Checkout::save( $state );
					Contorno_Evo_Checkout::log( 'error', 'venda indeterminada: ' . $sale['message'], $state + array( 'http' => $sale['http'] ) );

					return self::fail( 'indeterminado' );
				}

				// by-session-id respondeu e nao ha venda: a tentativa morreu
				// mesmo. Pode tentar de novo.
				$state['status'] = 'identified';
				Contorno_Evo_Checkout::save( $state );

				return self::fail( 'evo_indisponivel' );
			}

			// 8. Recusa limpa (4xx): nada foi criado, a pessoa pode corrigir.
			$state['status'] = 'identified';
			Contorno_Evo_Checkout::save( $state );

			$reason = 400 === $sale['http'] ? Contorno_Evo_Checkout::sale_refusal_reason( (string) $sale['message'] ) : 'evo_indisponivel';

			Contorno_Evo_Checkout::log(
				'warning',
				sprintf(
					'venda recusada [%s] (venda para %s %d, filial do cadastro %s): %s',
					$reason,
					(int) $state['id_member'] > 0 ? 'aluno idMember' : 'prospect idProspect',
					(int) $state['id_member'] > 0 ? (int) $state['id_member'] : (int) $state['id_prospect'],
					(int) ( $state['person_branch'] ?? 0 ) > 0 ? (string) $state['person_branch'] : '?',
					$sale['message']
				),
				$state + array( 'http' => $sale['http'] )
			);

			// Recusa por cadastro/configuracao nao e "cartao recusado": a
			// pessoa nao resolve trocando de cartao. Nada foi criado, entao
			// o contato com a unidade e um destino seguro.
			return self::fail( $reason, array( 'fallback' => 'cartao_recusado' !== $reason ) );
		} finally {
			Contorno_Evo_Checkout::release_lock( $token );
		}
	}

	/* ---------------------------------------------------------------
	 * Auxiliares
	 * ------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $state
	 * @param mixed               $data
	 */
	private static function succeed( array $state, mixed $data, Contorno_Evo_Client $client ): WP_REST_Response {
		// Mesma leitura tolerante da reconciliacao (idVenda/idSale/inteiro):
		// a resposta real de by-session-id usa idVenda, e nada garante que a
		// de POST /sales use outro nome.
		$id_sale = Contorno_Evo_Client::sale_id( $data );

		// NewSaleViewModel, o schema de resposta documentado, nao declara
		// idSale. Quando ele nao vem, o id sai do sessionId — que e nosso.
		if ( $id_sale <= 0 ) {
			$probe = $client->sale_by_session( (string) $state['session_id'] );
			$id_sale = $probe['ok'] ? $probe['id_sale'] : 0;
		}

		$state['status']  = 'success';
		$state['id_sale'] = $id_sale;
		Contorno_Evo_Checkout::save( $state );
		Contorno_Evo_Checkout::mark_done( (string) ( $state['fingerprint'] ?? '' ), (string) $state['token'] );
		Contorno_Evo_Checkout::log( 'info', 'venda concluida pelo checkout nativo', $state );

		return self::done( $state, '' );
	}

	/**
	 * @param array<string,mixed> $state
	 */
	private static function done( array $state, string $code ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'status'   => 'success',
				'message'  => '' !== $code ? Contorno_Evo_Checkout::public_message( $code ) : '',
				// Somente o token opaco vai na URL. Nem nome, nem e-mail, nem
				// idProspect, nem idMember, nem idSale.
				'redirect' => add_query_arg( 'ck', (string) $state['token'], self::confirmation_url() ),
			),
			200
		);
	}

	public static function confirmation_url(): string {
		$page = get_page_by_path( 'matricula/confirmacao' );

		return $page instanceof WP_Post ? (string) get_permalink( $page ) : home_url( '/matricula/confirmacao/' );
	}

	/**
	 * Dados pessoais do pedido, validados. Nao sao gravados em lugar nenhum.
	 *
	 * $lead = true: a sessao ja registrou o lead (nome, e-mail, WhatsApp
	 * estao na EVO); aqui so CPF, nascimento, termos e endereco.
	 *
	 * @return array{ok:bool,code:string,data:array<string,string>}
	 */
	private static function person( WP_REST_Request $request, bool $lead = false ): array {
		$raw = (array) $request->get_param( 'person' );

		$first = trim( sanitize_text_field( (string) ( $raw['firstName'] ?? '' ) ) );
		$last  = trim( sanitize_text_field( (string) ( $raw['lastName'] ?? '' ) ) );
		$email = sanitize_email( (string) ( $raw['email'] ?? '' ) );
		$phone = Contorno_Evo_Checkout::normalize_phone( (string) ( $raw['phone'] ?? '' ) );
		$doc   = preg_replace( '/\D/', '', (string) ( $raw['document'] ?? '' ) ) ?? '';

		$fail = static fn (): array => array( 'ok' => false, 'code' => 'dados_invalidos', 'data' => array() );

		if ( ! $lead ) {
			if ( mb_strlen( $first ) < 2 || mb_strlen( $first ) > 60 ) {
				return $fail();
			}

			if ( mb_strlen( $last ) < 2 || mb_strlen( $last ) > 80 ) {
				return $fail();
			}

			if ( '' === $email || ! is_email( $email ) || mb_strlen( $email ) > 254 ) {
				return $fail();
			}

			if ( ! Contorno_Evo_Checkout::valid_phone( $phone ) ) {
				return $fail();
			}
		}

		if ( 11 !== strlen( $doc ) || ! self::valid_cpf( $doc ) ) {
			return $fail();
		}

		if ( empty( $raw['acceptedTerms'] ) ) {
			return $fail();
		}

		$data = array(
			'first_name' => $first,
			'last_name'  => $last,
			'email'      => $email,
			'phone'      => $phone,
			'document'   => $doc,
		);

		$birthday = trim( (string) ( $raw['birthday'] ?? '' ) );

		if ( '' !== $birthday && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birthday ) ) {
			$data['birthday'] = $birthday;
		}

		if ( Contorno_Evo_Settings::get( 'checkout_require_address', false ) ) {
			foreach ( array( 'zip_code' => 'zipCode', 'address' => 'address', 'number' => 'number', 'complement' => 'complement', 'neighborhood' => 'neighborhood', 'city' => 'city', 'state' => 'state' ) as $key => $param ) {
				$data[ $key ] = sanitize_text_field( (string) ( $raw[ $param ] ?? '' ) );
			}

			if ( 8 !== strlen( preg_replace( '/\D/', '', $data['zip_code'] ) ?? '' ) || '' === $data['address'] || '' === $data['city'] ) {
				return array( 'ok' => false, 'code' => 'dados_insuficientes', 'data' => array() );
			}
		}

		return array( 'ok' => true, 'code' => '', 'data' => $data );
	}

	/**
	 * Digito verificador do CPF.
	 *
	 * Validamos aqui porque o CPF e o identificador que impede cadastro
	 * duplicado na EVO: um digito trocado cria um prospect novo para uma
	 * pessoa que ja existe. A EVO tambem valida (validateCpf), e continuar
	 * mandando a flag e proposital — esta checagem so poupa a ida.
	 */
	public static function valid_cpf( string $cpf ): bool {
		if ( 11 !== strlen( $cpf ) || 1 === count( array_unique( str_split( $cpf ) ) ) ) {
			return false;
		}

		for ( $position = 9; $position < 11; $position++ ) {
			$sum = 0;

			for ( $index = 0; $index < $position; $index++ ) {
				$sum += (int) $cpf[ $index ] * ( $position + 1 - $index );
			}

			$digit = ( ( 10 * $sum ) % 11 ) % 10;

			if ( (int) $cpf[ $position ] !== $digit ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resumo fixo do checkout. Vem SEMPRE do orcamento gravado no servidor.
	 *
	 * @param array<string,mixed> $state
	 * @return array<string,mixed>
	 */
	public static function summary( array $state ): array {
		$quote = (array) ( $state['quote'] ?? array() );
		$post  = get_post( (int) $state['post_id'] );

		return array(
			'unit'              => $post instanceof WP_Post ? (string) get_the_title( $post ) : '',
			'plan'              => (string) ( $quote['name'] ?? '' ),
			'value'             => (float) ( $quote['value'] ?? 0 ),
			'firstValue'        => (float) ( $quote['first_value'] ?? 0 ),
			'promoValue'        => (float) ( $quote['promo_value'] ?? 0 ),
			'promoMonths'       => (int) ( $quote['promo_months'] ?? 0 ),
			'promoDays'         => (int) ( $quote['promo_days'] ?? 0 ),
			'duration'          => (int) ( $quote['duration'] ?? 0 ),
			'durationType'      => (string) ( $quote['duration_type'] ?? '' ),
			'minStay'           => (int) ( $quote['min_stay'] ?? 0 ),
			'maxInstallments'   => (int) ( $quote['max_installments'] ?? 1 ),
			'enrollmentRequired' => (bool) ( $quote['enrollment_required'] ?? false ),
		);
	}
}
