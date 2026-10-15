<?php
/**
 * Checkout nativo — orquestracao da venda pela API da EVO.
 *
 * REGRA CENTRAL: o navegador escolhe, o servidor decide.
 *
 * O que chega do navegador e so a SELECAO: slug da unidade e id LOCAL do
 * plano (o mesmo "black"/"premium"/"fit" do repeater). Nada mais da venda
 * vem de fora. Preco, idBranch, idMembership, condicao e parcelamento sao
 * resolvidos aqui a partir do CPT e conferidos na EVO. Por consequencia:
 *
 *   ?price=1          nao faz efeito — o valor nunca e lido da requisicao
 *   ?idMembership=999 nao faz efeito — o id sai do plano cadastrado
 *   ?checkout=http... nao faz efeito — nao existe destino vindo do cliente
 *
 * O estado do checkout mora num transient de vida curta, indexado por um
 * token opaco. O token nao e adivinhavel e nao carrega informacao; o que o
 * navegador guarda nao diz nem qual unidade e.
 *
 * PII: o transient guarda IDENTIFICADORES (post_id, idBranch, idMembership,
 * idProspect, idMember, idSale) e o orcamento. Nome, e-mail, telefone, CPF e
 * endereco NAO sao gravados em lugar nenhum do WordPress — ficam no
 * navegador ate o envio final e, dali, seguem direto para a EVO. Cartao
 * nunca passa por aqui: o backend recebe token, nunca PAN nem CVV.
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Checkout {

	/** Vida do checkout. Curta de proposito: e uma sessao de compra. */
	public const TTL = 2700; // 45 min

	private const PREFIX = 'contorno_evo_ck_';

	/* ---------------------------------------------------------------
	 * Resolucao server-side da selecao
	 * ------------------------------------------------------------- */

	/**
	 * Unidade/CTN + plano a partir da selecao do navegador.
	 *
	 * @return array{ok:bool,error:string,post:?WP_Post,plan:array<string,mixed>,id_branch:int,id_membership:int}
	 */
	public static function resolve( string $slug, string $plan_id ): array {
		$fail = static fn ( string $code ): array => array(
			'ok'            => false,
			'error'         => $code,
			'post'          => null,
			'plan'          => array(),
			'id_branch'     => 0,
			'id_membership' => 0,
		);

		$slug    = sanitize_title( $slug );
		$plan_id = sanitize_text_field( $plan_id );

		if ( '' === $slug || '' === $plan_id ) {
			return $fail( 'selecao_invalida' );
		}

		$post = contorno_get_unit_by_slug( $slug );

		if ( ! $post instanceof WP_Post ) {
			$post = contorno_get_ctn_by_slug( $slug );
		}

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return $fail( 'unidade_invalida' );
		}

		// Planos LOCAIS, sem a sobreposicao do frontend: o que vale para
		// achar o idMembership e o que o editor cadastrou, nao o que o filtro
		// de exibicao montou.
		$plan = null;

		foreach ( Contorno_Evo_Mapping::local_plans( $post->ID ) as $candidate ) {
			if ( (string) ( $candidate['id'] ?? '' ) === $plan_id ) {
				$plan = $candidate;
				break;
			}
		}

		if ( null === $plan ) {
			return $fail( 'plano_invalido' );
		}

		$id_branch     = Contorno_Evo_Mapping::branch( $post->ID );
		$id_membership = Contorno_Evo_Mapping::membership_for_plan( $plan, Contorno_Evo_Mapping::links( $post->ID ) );

		if ( $id_branch <= 0 ) {
			return $fail( 'unidade_sem_filial' );
		}

		if ( $id_membership <= 0 ) {
			return $fail( 'plano_sem_membership' );
		}

		return array(
			'ok'            => true,
			'error'         => '',
			'post'          => $post,
			'plan'          => $plan,
			'id_branch'     => $id_branch,
			'id_membership' => $id_membership,
		);
	}

	/**
	 * Orcamento oficial: o que a EVO diz sobre este contrato, agora.
	 *
	 * Nao usamos o preco do CPT para vender. Ele existe para a vitrine; aqui
	 * vale o valor da EVO. E nao mandamos membershipValue na venda — a EVO
	 * aplica o valor oficial dela (defaultSaleValue / promocao). Este
	 * orcamento serve para EXIBIR e para detectar mudanca de preco entre a
	 * abertura do checkout e o pagamento.
	 *
	 * @return array{ok:bool,error:string,message:string,quote:array<string,mixed>}
	 */
	public static function quote( int $id_membership, int $id_branch ): array {
		$client = new Contorno_Evo_Client();
		$result = $client->membership( $id_membership, $id_branch );

		if ( ! $result['ok'] ) {
			return array( 'ok' => false, 'error' => 'evo_indisponivel', 'message' => $result['message'], 'quote' => array() );
		}

		$item = $result['item'];

		if ( ! is_array( $item ) ) {
			// O plano nao existe nessa filial. Vender assim seria vender outra
			// coisa — ou nada. Cai para o checkout externo.
			return array( 'ok' => false, 'error' => 'membership_inexistente', 'message' => '', 'quote' => array() );
		}

		if ( ! empty( $item['inactive'] ) ) {
			return array( 'ok' => false, 'error' => 'membership_inativo', 'message' => '', 'quote' => array() );
		}

		$value = round( (float) ( $item['value'] ?? 0 ), 2 );
		$promo = round( (float) ( $item['valuePromotionalPeriod'] ?? 0 ), 2 );
		$has_promo = $promo > 0 && $promo < $value
			&& ( (int) ( $item['monthsPromotionalPeriod'] ?? 0 ) > 0 || (int) ( $item['daysPromotionalPeriod'] ?? 0 ) > 0 );

		return array(
			'ok'      => true,
			'error'   => '',
			'message' => '',
			'quote'   => array(
				'id_membership'      => (int) ( $item['idMembership'] ?? $id_membership ),
				'id_branch'          => (int) ( $item['idBranch'] ?? $id_branch ),
				'name'               => sanitize_text_field( (string) ( $item['displayName'] ?? $item['nameMembership'] ?? '' ) ),
				'value'              => $value,
				'recurrent_value'    => $value,
				'promo_value'        => $has_promo ? $promo : 0.0,
				'promo_months'       => (int) ( $item['monthsPromotionalPeriod'] ?? 0 ),
				'promo_days'         => (int) ( $item['daysPromotionalPeriod'] ?? 0 ),
				'first_value'        => $has_promo ? $promo : $value,
				'duration'           => (int) ( $item['duration'] ?? 0 ),
				'duration_type'      => sanitize_text_field( (string) ( $item['durationType'] ?? '' ) ),
				'min_stay'           => (int) ( $item['minPeriodStayMembership'] ?? 0 ),
				'max_installments'   => max( 1, (int) ( $item['maxAmountInstallments'] ?? 1 ) ),
				// Taxa de matricula: a EVO informa SE existe exigencia
				// (enrollmentRequired/acceptEnrollment) mas nao devolve o valor
				// nesta rota. Exibimos a existencia, nunca um numero inventado.
				'enrollment_required' => ! empty( $item['enrollmentRequired'] ),
				'accept_enrollment'   => ! empty( $item['acceptEnrollment'] ),
				'external_sale'       => ! empty( $item['externalSaleAvailable'] ),
				'url_sale'            => esc_url_raw( (string) ( $item['urlSale'] ?? '' ) ),
				'fetched'             => time(),
			),
		);
	}

	/* ---------------------------------------------------------------
	 * Sessao de checkout
	 * ------------------------------------------------------------- */

	public static function key( string $token ): string {
		return self::PREFIX . $token;
	}

	/** Token opaco, 32 hex. Nao carrega informacao e nao e adivinhavel. */
	public static function new_token(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Abre a sessao. Devolve token + orcamento, ou o motivo de nao abrir.
	 *
	 * @return array{ok:bool,error:string,token:string,state:array<string,mixed>}
	 */
	public static function open( string $slug, string $plan_id ): array {
		$resolved = self::resolve( $slug, $plan_id );

		if ( ! $resolved['ok'] ) {
			return array( 'ok' => false, 'error' => $resolved['error'], 'token' => '', 'state' => array() );
		}

		$post = $resolved['post'];

		if ( ! Contorno_Evo_Settings::checkout_enabled_for( (string) $post->post_name ) ) {
			return array( 'ok' => false, 'error' => 'nativo_desligado', 'token' => '', 'state' => array() );
		}

		$quote = self::quote( $resolved['id_membership'], $resolved['id_branch'] );

		if ( ! $quote['ok'] ) {
			self::log( 'warning', 'checkout nao abriu: ' . $quote['error'], $resolved );

			return array( 'ok' => false, 'error' => $quote['error'], 'token' => '', 'state' => array() );
		}

		$token = self::new_token();
		$state = array(
			'token'         => $token,
			'created'       => time(),
			'status'        => 'draft',
			'post_id'       => (int) $post->ID,
			'slug'          => (string) $post->post_name,
			'plan_id'       => (string) ( $resolved['plan']['id'] ?? '' ),
			'id_branch'     => $resolved['id_branch'],
			'id_membership' => $resolved['id_membership'],
			'quote'         => $quote['quote'],
			// sessionId da EVO: fixo por sessao de checkout. E o que permite
			// perguntar depois "essa tentativa virou venda?" sem arriscar
			// criar uma segunda (GET /api/v1/sales/by-session-id).
			'session_id'    => 'ctn-' . $token,
			'id_prospect'   => 0,
			'id_member'     => 0,
			'id_sale'       => 0,
			'attempts'      => 0,
		);

		self::save( $state );

		return array( 'ok' => true, 'error' => '', 'token' => $token, 'state' => $state );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function load( string $token ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}

		$state = get_transient( self::key( $token ) );

		return is_array( $state ) && ! empty( $state['post_id'] ) ? $state : null;
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public static function save( array $state ): void {
		$token = (string) ( $state['token'] ?? '' );

		if ( '' === $token ) {
			return;
		}

		set_transient( self::key( $token ), $state, self::TTL );
	}

	public static function forget( string $token ): void {
		delete_transient( self::key( $token ) );
	}

	/* ---------------------------------------------------------------
	 * Prospect x membro existente
	 * ------------------------------------------------------------- */

	/**
	 * Quem e esta pessoa para a EVO, antes de criar qualquer cadastro.
	 *
	 * Ordem: membro > prospect > ninguem. Primeiro por documento (o unico
	 * identificador que a EVO trata como unico), depois por e-mail, depois
	 * por telefone. Consultamos /api/v1/members/basic — a rota SEM dados
	 * sensiveis — justamente para nao precisar de permissao maior.
	 *
	 * Comportamento definido para cada caso:
	 *   A) ja e membro   -> nao cria prospect; a venda usa memberData.idMember
	 *   B) ja e prospect -> reusa o idProspect; nao cria outro
	 *   C) nao existe    -> cria prospect no momento da venda, nao antes
	 *
	 * @param array<string,string> $person  document, email, phone
	 * @return array{ok:bool,error:string,kind:string,id_member:int,id_prospect:int}
	 */
	/**
	 * O registro devolvido pela EVO TEM o dado que foi buscado?
	 *
	 * Em 15/10/2026 a busca devolveu o mesmo aluno (idMember 325082, filial
	 * 59) para pessoas sem cadastro: o filtro da EVO nao e confiavel o
	 * bastante para usar o primeiro resultado as cegas. Aceitar um cadastro
	 * errado vende o plano na conta de outra pessoa.
	 *
	 * - e-mail: igual (sem caixa) a item.email ou a um contato com "@";
	 * - telefone: mesmo numero nacional em item.cellphone ou nos contatos;
	 * - CPF: igual a item.document; members/basic NAO devolve CPF, entao
	 *   so vale se a EVO devolveu exatamente um registro.
	 *
	 * @param array<string,mixed> $item
	 * @param array<string,mixed> $filter  um so: email | phone | document
	 */
	public static function matches( array $item, array $filter, int $total ): bool {
		$emails = array();
		$phones = array();

		foreach ( (array) ( $item['contacts'] ?? array() ) as $contact ) {
			$desc = is_array( $contact ) ? trim( (string) ( $contact['description'] ?? '' ) ) : '';
			if ( str_contains( $desc, '@' ) ) {
				$emails[] = strtolower( $desc );
			} elseif ( '' !== $desc ) {
				$phones[] = self::normalize_phone( (string) ( $contact['ddi'] ?? '' ) . $desc );
				$phones[] = self::normalize_phone( $desc );
			}
		}

		if ( ! empty( $item['email'] ) ) {
			$emails[] = strtolower( trim( (string) $item['email'] ) );
		}
		if ( ! empty( $item['cellphone'] ) ) {
			$phones[] = self::normalize_phone( (string) $item['cellphone'] );
		}

		if ( isset( $filter['email'] ) ) {
			return in_array( strtolower( trim( (string) $filter['email'] ) ), $emails, true );
		}

		if ( isset( $filter['phone'] ) ) {
			$wanted = self::normalize_phone( (string) $filter['phone'] );

			foreach ( $phones as $phone ) {
				if ( '' === $phone || strlen( $phone ) < 8 || strlen( $wanted ) < 8 ) {
					continue;
				}

				if ( $phone === $wanted ) {
					return true;
				}

				// Cadastro antigo sem o 9 ou sem DDD: mesmos 8 ultimos digitos
				// e, quando os dois tem DDD, o mesmo DDD.
				$same_tail = substr( $phone, -8 ) === substr( $wanted, -8 );
				$same_ddd  = strlen( $phone ) < 10 || strlen( $wanted ) < 10 || substr( $phone, 0, 2 ) === substr( $wanted, 0, 2 );

				if ( $same_tail && $same_ddd ) {
					return true;
				}
			}

			return false;
		}

		if ( isset( $filter['document'] ) ) {
			if ( array_key_exists( 'document', $item ) && '' !== (string) $item['document'] ) {
				return preg_replace( '/\D/', '', (string) $item['document'] ) === preg_replace( '/\D/', '', (string) $filter['document'] );
			}

			return 1 === $total;
		}

		return false;
	}

	public static function identify( array $person, int $id_branch ): array {
		$client   = new Contorno_Evo_Client();
		$document = preg_replace( '/\D/', '', (string) ( $person['document'] ?? '' ) ) ?? '';
		$email    = sanitize_email( (string) ( $person['email'] ?? '' ) );
		$phone    = self::normalize_phone( (string) ( $person['phone'] ?? '' ) );

		$filters = array();
		if ( '' !== $document ) {
			$filters[] = array( 'document' => $document );
		}
		if ( '' !== $email ) {
			$filters[] = array( 'email' => $email );
		}
		if ( '' !== $phone ) {
			$filters[] = array( 'phone' => $phone );
		}

		if ( array() === $filters ) {
			return array( 'ok' => false, 'error' => 'dados_insuficientes', 'kind' => '', 'id_member' => 0, 'id_prospect' => 0 );
		}

		// Registros devolvidos que NAO conferem com o dado buscado (ver matches()).
		$ignored = 0;

		// 1. Membro. Sem idBranch: a pessoa pode estar cadastrada em outra
		// unidade da rede, e nesse caso ela CONTINUA sendo a mesma pessoa —
		// criar prospect novo duplicaria o cadastro dela na base.
		foreach ( $filters as $filter ) {
			$found = $client->members_basic( $filter );

			if ( ! $found['ok'] ) {
				// Falha de consulta nao pode virar "nao existe": isso levaria
				// a criar cadastro duplicado. Para o fluxo e deixa o front
				// oferecer o checkout externo.
				return array( 'ok' => false, 'error' => 'evo_indisponivel', 'kind' => '', 'id_member' => 0, 'id_prospect' => 0 );
			}

			foreach ( $found['items'] as $item ) {
				if ( ! self::matches( $item, $filter, count( $found['items'] ) ) ) {
					++$ignored;
					continue;
				}

				if ( (int) ( $item['idMember'] ?? 0 ) > 0 ) {
					return array( 'ok' => true, 'error' => '', 'kind' => 'member', 'id_member' => (int) $item['idMember'], 'id_prospect' => 0, 'branch' => (int) ( $item['idBranch'] ?? 0 ), 'ignored' => $ignored );
				}
			}
		}

		// 2. Prospect.
		foreach ( $filters as $filter ) {
			$found = $client->prospects( $filter );

			if ( ! $found['ok'] ) {
				return array( 'ok' => false, 'error' => 'evo_indisponivel', 'kind' => '', 'id_member' => 0, 'id_prospect' => 0 );
			}

			foreach ( $found['items'] as $item ) {
				if ( (int) ( $item['idProspect'] ?? 0 ) <= 0 ) {
					continue;
				}

				if ( ! self::matches( $item, $filter, count( $found['items'] ) ) ) {
					++$ignored;
					continue;
				}

				// Prospect ja convertido em membro: vale o membro.
				if ( (int) ( $item['idMember'] ?? 0 ) > 0 ) {
					return array( 'ok' => true, 'error' => '', 'kind' => 'member', 'id_member' => (int) $item['idMember'], 'id_prospect' => (int) $item['idProspect'], 'branch' => (int) ( $item['idBranch'] ?? 0 ), 'ignored' => $ignored );
				}

				return array( 'ok' => true, 'error' => '', 'kind' => 'prospect', 'id_member' => 0, 'id_prospect' => (int) $item['idProspect'], 'branch' => (int) ( $item['idBranch'] ?? 0 ), 'ignored' => $ignored );
			}
		}

		return array( 'ok' => true, 'error' => '', 'kind' => 'new', 'id_member' => 0, 'id_prospect' => 0, 'branch' => 0, 'ignored' => $ignored );
	}

	/**
	 * Monta o ProspectApiIntegracaoViewModel.
	 *
	 * Somente o que o fluxo real exige. A especificacao declara TODOS os
	 * campos de prospect como nullable, entao o minimo defensavel e o que
	 * identifica a pessoa: nome, sobrenome, e-mail, telefone, CPF e a filial.
	 * Endereco entra apenas quando a configuracao pedir — ver
	 * checkout_require_address em Contorno_Evo_Settings.
	 *
	 * @param array<string,string> $person
	 * @return array<string,mixed>
	 */
	public static function prospect_payload( array $person, int $id_branch ): array {
		$document = preg_replace( '/\D/', '', (string) ( $person['document'] ?? '' ) ) ?? '';
		$phone    = self::normalize_phone( (string) ( $person['phone'] ?? '' ) );

		$payload = array(
			'name'      => sanitize_text_field( (string) ( $person['first_name'] ?? '' ) ),
			'lastName'  => sanitize_text_field( (string) ( $person['last_name'] ?? '' ) ),
			'email'     => sanitize_email( (string) ( $person['email'] ?? '' ) ),
			'idBranch'  => $id_branch,
			// A origem do cadastro precisa ficar registrada na EVO: sem isso a
			// equipe comercial nao distingue quem se matriculou pelo site.
			'marketingType' => 'Site',
		);

		if ( '' !== $phone ) {
			$payload['ddi']       = '55';
			$payload['cellphone'] = $phone;
		}

		if ( 11 === strlen( $document ) ) {
			$payload['cpf'] = $document;
			// A EVO valida o CPF e avisa quando ja existe. Nao silenciamos
			// esse aviso: duplicar cadastro e pior que recusar a tentativa.
			$payload['validateCpf']            = true;
			$payload['validateCpfDuplication'] = true;
		}

		if ( '' !== (string) ( $person['birthday'] ?? '' ) ) {
			$payload['birthday'] = (string) $person['birthday'];
		}

		if ( Contorno_Evo_Settings::get( 'checkout_require_address', false ) ) {
			$payload += self::address_payload( $person );
		}

		return array_filter( $payload, static fn ( $v ): bool => '' !== $v && null !== $v );
	}

	/**
	 * Endereco, quando exigido. idState vem da tabela da EVO, nunca da UF crua.
	 *
	 * @param array<string,string> $person
	 * @return array<string,mixed>
	 */
	/* ---------------------------------------------------------------
	 * Lead (primeira etapa)
	 * ------------------------------------------------------------- */

	/**
	 * Por que a EVO recusou a venda (HTTP 400), pela mensagem dela.
	 *
	 * Antes todo 400 virava "cartao recusado" — em 15/10/2026 o motivo real
	 * era "Este contrato nao pode ser vendido para clientes de outra filial",
	 * e o visitante tentou 8 vezes trocando de cartao.
	 */
	public static function sale_refusal_reason( string $message ): string {
		$text = mb_strtolower( $message );

		if ( str_contains( $text, 'outra filial' ) || str_contains( $text, 'outras filiais' ) ) {
			return 'outra_filial';
		}

		foreach ( array( 'cartão', 'cartao', 'transação', 'transacao', 'autoriz', 'recusad', 'negad', 'saldo', 'limite', 'cvv', 'venciment', 'emissor', 'bandeira' ) as $needle ) {
			if ( str_contains( $text, $needle ) ) {
				return 'cartao_recusado';
			}
		}

		return 'venda_recusada';
	}

	/** Etapas do funil gravadas em currentStep do prospect na EVO. */
	public const STEP_LEAD    = 'Lead site';
	public const STEP_PAYMENT = 'Pagamento site';

	/**
	 * Telefone nacional (DDD + numero), sem DDI — defesa no servidor.
	 *
	 * O front ja normaliza, mas um cliente que mande "5531975248061" (12/13
	 * digitos comecando por 55) nao pode virar DDD 55 na EVO. Com 10/11
	 * digitos, "55" e DDD (RS) e fica. O DDI vai a EVO uma unica vez, no
	 * campo ddi = "55" (lead_payload/prospect_payload).
	 */
	public static function normalize_phone( string $value ): string {
		$digits = preg_replace( '/\D/', '', $value ) ?? '';

		if ( str_starts_with( $digits, '55' ) && ( 12 === strlen( $digits ) || 13 === strlen( $digits ) ) ) {
			$digits = substr( $digits, 2 );
		}

		return $digits;
	}

	/** Mesma regra do front: 10/11 digitos, DDD sem zero, celular comeca por 9. */
	public static function valid_phone( string $national ): bool {
		$len = strlen( $national );

		return ( 10 === $len || 11 === $len )
			&& 1 === preg_match( '/^[1-9][1-9]\d+$/', $national )
			&& ( 10 === $len || '9' === $national[2] );
	}

	/**
	 * "Maria de Souza Lima" -> ["Maria", "de Souza Lima"].
	 *
	 * A EVO exige name E lastName no prospect; o site pede um campo so.
	 * Menos de duas palavras = nao da para separar = recusa (o front avisa).
	 *
	 * @return array{0:string,1:string}|null
	 */
	public static function split_name( string $full ): ?array {
		$full  = trim( (string) preg_replace( '/\s+/u', ' ', sanitize_text_field( $full ) ) );
		$parts = '' === $full ? array() : explode( ' ', $full, 2 );

		if ( 2 !== count( $parts ) || mb_strlen( $parts[0] ) < 2 || mb_strlen( $parts[1] ) < 1 || mb_strlen( $full ) > 140 ) {
			return null;
		}

		return array( $parts[0], $parts[1] );
	}

	/**
	 * Prospect da primeira etapa: nome, e-mail e WhatsApp, sem CPF.
	 * name, lastName e email sao os obrigatorios de POST /api/v1/prospects.
	 *
	 * @param array{first_name:string,last_name:string,email:string,phone:string} $lead
	 * @return array<string,mixed>
	 */
	public static function lead_payload( array $lead, int $id_branch, string $plan_name ): array {
		$payload = array(
			'name'          => $lead['first_name'],
			'lastName'      => $lead['last_name'],
			'email'         => $lead['email'],
			'ddi'           => '55',
			'cellphone'     => $lead['phone'],
			'idBranch'      => $id_branch,
			'marketingType' => 'Site',
			'currentStep'   => self::STEP_LEAD,
			// O que a pessoa estava comprando: a unidade retoma o contato
			// sabendo o plano. Nenhum dado pessoal aqui.
			'notes'         => '' !== $plan_name ? mb_substr( 'Matrícula online iniciada no site. Plano: ' . $plan_name, 0, 250 ) : 'Matrícula online iniciada no site.',
		);

		return array_filter( $payload, static fn ( $v ): bool => '' !== $v && null !== $v );
	}

	/**
	 * Segunda etapa: o lead ja existe; o CPF chega agora.
	 *
	 * 1. CPF de aluno existente -> a venda vai para o MEMBRO (o lead fica
	 *    como historico na EVO; nao se cria nada novo).
	 * 2. Senao, completa o MESMO prospect (PATCH: cpf, nascimento, etapa).
	 * 3. PATCH recusado (ex.: CPF ja usado por outro prospect) -> procura o
	 *    prospect dono do CPF e usa esse. Nao achou -> dados_invalidos.
	 *
	 * Qualquer falha de consulta para o fluxo ANTES da venda (fallback
	 * seguro): "nao consegui verificar" nunca vira "nao existe".
	 *
	 * @param array<string,mixed>  $state
	 * @param array<string,string> $person  saida validada de person()
	 * @return array{ok:bool,error:string,id_member:int,id_prospect:int}
	 */
	public static function complete_lead( array $state, array $person ): array {
		$client   = new Contorno_Evo_Client();
		$document = (string) $person['document'];
		$fail     = static fn ( string $error ): array => array( 'ok' => false, 'error' => $error, 'id_member' => 0, 'id_prospect' => 0 );

		$members = $client->members_basic( array( 'document' => $document ) );

		if ( ! $members['ok'] ) {
			return $fail( 'evo_indisponivel' );
		}

		foreach ( $members['items'] as $item ) {
			if ( ! self::matches( $item, array( 'document' => $document ), count( $members['items'] ) ) ) {
				continue;
			}
			if ( (int) ( $item['idMember'] ?? 0 ) > 0 ) {
				return array( 'ok' => true, 'error' => '', 'id_member' => (int) $item['idMember'], 'id_prospect' => 0 );
			}
		}

		$id_prospect = (int) $state['id_prospect'];
		$patch       = array(
			'idProspect'  => $id_prospect,
			'cpf'         => $document,
			'currentStep' => self::STEP_PAYMENT,
		);

		if ( '' !== (string) ( $person['birthday'] ?? '' ) ) {
			$patch['birthday'] = (string) $person['birthday'];
		}

		if ( Contorno_Evo_Settings::get( 'checkout_require_address', false ) ) {
			$patch += self::address_payload( $person );
		}

		$updated = $client->update_prospect( $patch );

		if ( $updated['ok'] ) {
			return array( 'ok' => true, 'error' => '', 'id_member' => 0, 'id_prospect' => $id_prospect );
		}

		if ( 400 !== $updated['http'] ) {
			self::log( 'error', 'falha ao completar o lead: ' . $updated['message'], $state + array( 'http' => $updated['http'] ) );

			return $fail( 'evo_indisponivel' );
		}

		// 400: provavelmente o CPF ja pertence a outro prospect.
		$owner = $client->prospects( array( 'document' => $document ) );

		if ( ! $owner['ok'] ) {
			return $fail( 'evo_indisponivel' );
		}

		foreach ( $owner['items'] as $item ) {
			if ( ! self::matches( $item, array( 'document' => $document ), count( $owner['items'] ) ) ) {
				continue;
			}
			if ( (int) ( $item['idMember'] ?? 0 ) > 0 ) {
				return array( 'ok' => true, 'error' => '', 'id_member' => (int) $item['idMember'], 'id_prospect' => 0 );
			}
			if ( (int) ( $item['idProspect'] ?? 0 ) > 0 ) {
				self::log( 'warning', 'CPF ja era de outro prospect; venda usa o cadastro existente', $state );

				return array( 'ok' => true, 'error' => '', 'id_member' => 0, 'id_prospect' => (int) $item['idProspect'] );
			}
		}

		self::log( 'warning', 'EVO recusou completar o lead: ' . $updated['message'], $state + array( 'http' => 400 ) );

		return $fail( 'dados_invalidos' );
	}

	public static function address_payload( array $person ): array {
		$out = array(
			'zipCode'      => preg_replace( '/\D/', '', (string) ( $person['zip_code'] ?? '' ) ) ?? '',
			'address'      => sanitize_text_field( (string) ( $person['address'] ?? '' ) ),
			'number'       => sanitize_text_field( (string) ( $person['number'] ?? '' ) ),
			'complement'   => sanitize_text_field( (string) ( $person['complement'] ?? '' ) ),
			'neighborhood' => sanitize_text_field( (string) ( $person['neighborhood'] ?? '' ) ),
			'city'         => sanitize_text_field( (string) ( $person['city'] ?? '' ) ),
		);

		$uf = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) ( $person['state'] ?? '' ) ) ?? '', 0, 2 ) );

		if ( 2 === strlen( $uf ) ) {
			$states = ( new Contorno_Evo_Client() )->states();

			if ( isset( $states['states'][ $uf ] ) ) {
				$out['idState'] = (int) $states['states'][ $uf ];
			}
		}

		return array_filter( $out, static fn ( $v ): bool => '' !== $v && null !== $v );
	}

	/* ---------------------------------------------------------------
	 * Venda
	 * ------------------------------------------------------------- */

	/**
	 * Dados de cartao aceitos do navegador — LISTA FECHADA.
	 *
	 * O que entra e so o que a tokenizacao do EVO Pay devolve. PAN, CVV,
	 * numero completo e senha nao estao aqui e, se vierem, sao descartados
	 * antes de qualquer log — ver reject_raw_card().
	 */
	public const CARD_FIELDS = array(
		'token',
		'temporaryToken',
		'branchToken',
		'truncatedCardNumber',
		'brand',
		'cardHolderName',
		'cardExpirationYear',
		'cardExpirationMonth',
	);

	/**
	 * O payload de cartao contem dado bruto que nao deveria existir aqui?
	 *
	 * Barreira de ultima instancia. Se alguem (ou alguma versao futura do
	 * front) mandar PAN ou CVV, a requisicao morre ANTES de virar log,
	 * transient ou chamada externa. Nada do valor suspeito e registrado — so
	 * o fato de ter acontecido.
	 *
	 * @param array<string,mixed> $card
	 */
	public static function rejects_raw_card( array $card ): bool {
		foreach ( $card as $key => $value ) {
			$name = strtolower( (string) $key );

			if ( preg_match( '/(cvv|cvc|securitycode|cardnumber|pan|cardpassword|holdercpf)/', str_replace( '_', '', $name ) )
				&& ! in_array( (string) $key, self::CARD_FIELDS, true )
			) {
				return true;
			}

			// 13 a 19 digitos seguidos em qualquer campo: e um numero de
			// cartao, venha com o nome que vier. truncatedCardNumber legitimo
			// tem mascara e no maximo 6 digitos visiveis.
			if ( is_scalar( $value ) ) {
				$digits = preg_replace( '/\D/', '', (string) $value ) ?? '';

				if ( strlen( $digits ) >= 13 && strlen( $digits ) <= 19 && 'truncatedCardNumber' !== (string) $key ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Monta o NewSaleViewModel.
	 *
	 * membershipValue fica FORA de proposito: sem ele a EVO aplica o valor
	 * oficial do contrato (e a promocao vigente). Manda-lo seria deixar o
	 * preco final a cargo de quem montou o payload — exatamente o que nao
	 * queremos.
	 *
	 * @param array<string,mixed>  $state
	 * @param array<string,mixed>  $card
	 * @param array<string,string> $person
	 * @return array<string,mixed>
	 */
	public static function sale_payload( array $state, array $card, array $person, int $installments ): array {
		$payload = array(
			'idBranch'          => (int) $state['id_branch'],
			'idMembership'      => (int) $state['id_membership'],
			'payment'           => Contorno_Evo_Settings::payment_code_card(),
			'totalInstallments' => $installments,
			'sessionId'         => (string) $state['session_id'],
		);

		// memberData e OBRIGATORIO em POST /api/v2/sales, inclusive para
		// prospect: { "idMember": 0 } + idProspect (api.abcevo.com, "Create a
		// new sale" e guia "Realizar venda online via API", caso 3).
		$member = array( 'idMember' => max( 0, (int) $state['id_member'] ) );

		if ( Contorno_Evo_Settings::get( 'checkout_require_address', false ) ) {
			$member += self::address_payload( $person );
		}

		$payload['memberData'] = $member;

		if ( $member['idMember'] <= 0 && (int) $state['id_prospect'] > 0 ) {
			$payload['idProspect'] = (int) $state['id_prospect'];
		}

		$card_data = array( 'totalInstallments' => $installments );

		foreach ( self::CARD_FIELDS as $field ) {
			if ( isset( $card[ $field ] ) && '' !== (string) $card[ $field ] ) {
				$card_data[ $field ] = in_array( $field, array( 'cardExpirationYear', 'cardExpirationMonth' ), true )
					? (int) $card[ $field ]
					: sanitize_text_field( (string) $card[ $field ] );
			}
		}

		$payload['cardData'] = $card_data;

		return $payload;
	}

	/* ---------------------------------------------------------------
	 * Idempotencia
	 * ------------------------------------------------------------- */

	/**
	 * Trava de tentativa. Duplo clique, refresh e retry batem aqui.
	 *
	 * Vive num transient separado do estado para nao ser perdida junto com
	 * uma gravacao concorrente do estado.
	 */
	public static function acquire_lock( string $token ): bool {
		$key = self::PREFIX . 'lock_' . $token;

		// add_option/transient nao sao atomicos em todo backend de cache;
		// o par get+set aqui e o mesmo compromisso que Contorno_Evo_Sync::lock
		// ja faz, e a janela e de milissegundos contra um clique humano.
		if ( false !== get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, time(), 180 );

		return true;
	}

	public static function release_lock( string $token ): void {
		delete_transient( self::PREFIX . 'lock_' . $token );
	}

	/**
	 * Vida da marca de "esta pessoa acabou de comprar este plano".
	 *
	 * 30 min cobre o caso real (a pessoa recarrega, volta, tenta de novo) sem
	 * impedir uma segunda compra legitima mais tarde — por exemplo alguem
	 * matriculando o filho no mesmo plano usa OUTRO CPF, e quem troca de plano
	 * gera outra impressao digital.
	 */
	public const DONE_TTL = 1800;

	/**
	 * Impressao digital da compra, SEM guardar dado pessoal.
	 *
	 * Fecha um furo que a trava por sessao nao cobre: a trava vale para um
	 * token, e um F5 na pagina abre sessao NOVA, com sessionId novo. Sem esta
	 * marca, pagar -> recarregar -> pagar de novo seriam duas vendas
	 * legitimas aos olhos do servidor.
	 *
	 * O que vai para o banco e um HMAC-SHA256 de CPF + filial + contrato,
	 * com o salt do WordPress. Nao da para voltar ao CPF a partir dele, nao
	 * serve para procurar alguem, e some em 30 min. Guardar o CPF em claro
	 * resolveria o mesmo problema criando outro pior.
	 */
	public static function fingerprint( string $document, int $id_branch, int $id_membership ): string {
		$digits = preg_replace( '/\D/', '', $document ) ?? '';

		if ( '' === $digits ) {
			return '';
		}

		return self::PREFIX . 'done_' . hash_hmac(
			'sha256',
			$digits . '|' . $id_branch . '|' . $id_membership,
			wp_salt( 'nonce' )
		);
	}

	/** Token da sessao que concluiu esta compra, ou '' se nao houve. */
	public static function done_by( string $fingerprint ): string {
		if ( '' === $fingerprint ) {
			return '';
		}

		$stored = get_transient( $fingerprint );

		return is_string( $stored ) ? $stored : '';
	}

	public static function mark_done( string $fingerprint, string $token ): void {
		if ( '' !== $fingerprint ) {
			set_transient( $fingerprint, $token, self::DONE_TTL );
		}
	}

	/* ---------------------------------------------------------------
	 * Log
	 * ------------------------------------------------------------- */

	/**
	 * Log tecnico sem PII.
	 *
	 * Passa so identificadores. Nome, e-mail, CPF, telefone e qualquer coisa
	 * de cartao nao entram — nem truncados: um CPF parcial em log ainda e
	 * dado pessoal, e nao ajuda a depurar nada que o idProspect nao resolva.
	 *
	 * @param array<string,mixed> $context
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		Contorno_Evo_Log::add(
			$level,
			$message,
			array(
				'action'        => 'checkout',
				'unit'          => (string) ( $context['slug'] ?? ( isset( $context['post'] ) && $context['post'] instanceof WP_Post ? $context['post']->post_name : '' ) ),
				'id_branch'     => (string) ( $context['id_branch'] ?? '' ),
				'id_membership' => (string) ( $context['id_membership'] ?? '' ),
				'http'          => (int) ( $context['http'] ?? 0 ),
			)
		);
	}

	/* ---------------------------------------------------------------
	 * Mensagens publicas
	 * ------------------------------------------------------------- */

	/**
	 * Codigo interno -> texto para o visitante.
	 *
	 * Nenhuma resposta tecnica, status HTTP, token ou mensagem crua da EVO
	 * chega ao cliente. O detalhe fica no log administrativo.
	 */
	public static function public_message( string $code ): string {
		$map = array(
			'selecao_invalida'       => __( 'Não conseguimos identificar o plano escolhido. Volte aos planos e tente de novo.', 'contorno-evo' ),
			'unidade_invalida'       => __( 'Não conseguimos identificar a unidade escolhida.', 'contorno-evo' ),
			'plano_invalido'         => __( 'Este plano não está mais disponível nesta unidade.', 'contorno-evo' ),
			'unidade_sem_filial'     => __( 'Esta unidade ainda não aceita matrícula online.', 'contorno-evo' ),
			'plano_sem_membership'   => __( 'Este plano ainda não aceita matrícula online.', 'contorno-evo' ),
			'membership_inexistente' => __( 'Este plano não está mais disponível nesta unidade.', 'contorno-evo' ),
			'membership_inativo'     => __( 'Este plano não está mais disponível nesta unidade.', 'contorno-evo' ),
			'nativo_desligado'       => __( 'Não foi possível iniciar sua matrícula online no momento. A integração com o sistema da academia ainda não está disponível. Tente novamente em alguns instantes ou entre em contato com a unidade.', 'contorno-evo' ),
			'evo_indisponivel'       => __( 'Não conseguimos falar com o sistema da academia agora. Tente novamente em alguns instantes.', 'contorno-evo' ),
			'preco_mudou'            => __( 'O valor deste plano mudou enquanto você preenchia. Confira o novo valor antes de continuar.', 'contorno-evo' ),
			'sessao_expirada'        => __( 'Sua sessão expirou. Recomece a matrícula para continuar.', 'contorno-evo' ),
			'em_andamento'           => __( 'Já estamos processando seu pagamento. Aguarde alguns instantes sem fechar esta página.', 'contorno-evo' ),
			'ja_concluida'           => __( 'Sua matrícula já foi concluída.', 'contorno-evo' ),
			'cartao_recusado'        => __( 'O pagamento não foi aprovado. Confira os dados do cartão ou tente outro.', 'contorno-evo' ),
			// Nada foi cobrado; trocar de cartao nao resolve.
			'outra_filial'           => __( 'Você já tem cadastro em outra unidade da Contorno do Corpo, e este plano só pode ser contratado por alunos desta unidade. Nenhuma cobrança foi feita. Fale com a unidade para concluir sua matrícula.', 'contorno-evo' ),
			'cadastro_outra_filial'  => __( 'Encontramos seu cadastro em outra unidade da Contorno do Corpo. Para contratar um plano nesta unidade, fale com a equipe — ela conclui sua matrícula por lá.', 'contorno-evo' ),
			'venda_recusada'         => __( 'Não foi possível concluir sua matrícula online. Nenhuma cobrança foi feita. Fale com a unidade para concluir.', 'contorno-evo' ),
			'cartao_invalido'        => __( 'Não conseguimos validar os dados do cartão. Tente novamente.', 'contorno-evo' ),
			'dados_insuficientes'    => __( 'Precisamos de mais alguns dados para continuar.', 'contorno-evo' ),
			'dados_invalidos'        => __( 'Confira os dados informados e tente novamente.', 'contorno-evo' ),
			'limite'                 => __( 'Muitas tentativas em pouco tempo. Aguarde um instante e tente de novo.', 'contorno-evo' ),
			'indeterminado'          => __( 'Não conseguimos confirmar o resultado do pagamento. Nossa equipe vai verificar e entrar em contato — não tente pagar de novo.', 'contorno-evo' ),
		);

		return $map[ $code ] ?? __( 'Não foi possível concluir sua matrícula agora. Tente novamente em alguns instantes.', 'contorno-evo' );
	}
}
