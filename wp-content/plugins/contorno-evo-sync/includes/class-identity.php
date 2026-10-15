<?php
/**
 * Resolver de identidade do checkout — FAIL CLOSED.
 *
 * Incidente 15/10/2026 (Lourdes, branch 21, membership 5809): a busca da
 * EVO devolveu o aluno idMember 325082 (filial 59), que NAO era a pessoa que
 * digitou os dados, e o codigo usava o primeiro resultado. A EVO recusou a
 * venda so por ser de outra filial; na mesma filial, a venda teria caido na
 * conta de outra pessoa.
 *
 * Regras (nenhuma excecao):
 *
 *  1. Busca na EVO (members/basic, prospects) e DISCOVERY. Um resultado
 *     nunca e identidade por ter vindo primeiro, sozinho, com HTTP 200 ou
 *     por ter sido pedido com aquele filtro.
 *  2. A identidade financeira so existe com CPF, comparado com o CPF que a
 *     PROPRIA EVO devolve para o registro:
 *       - aluno:    GET /api/v2/members/{id} -> document;
 *       - prospect: GET /api/v1/prospects     -> document.
 *     members/basic nao devolve CPF. Se o perfil completo nao puder ser lido
 *     (403), o candidato fica NAO VERIFICAVEL e a venda nao acontece.
 *  3. E-mail/WhatsApp (etapa 1) so servem para nao duplicar o LEAD; nunca
 *     vinculam idMember/idProspect para cobranca.
 *  4. 2+ registros com o mesmo CPF -> ambiguo -> nao vende.
 *  5. Cadastro confirmado de outra filial -> nao vende, nao cria duplicado,
 *     nao transfere (POST /prospects/transfer so com regra aprovada).
 *  6. Pessoa nova: prospect com CPF (validateCpfDuplication). EVO acusando
 *     CPF existente -> conflito -> atendimento, nunca outro cadastro.
 *
 * So VERIFIED_* autoriza POST /api/v2/sales — ver Contorno_Evo_Checkout_Rest::pay().
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Identity {

	public const VERIFIED_MEMBER   = 'VERIFIED_MEMBER_SAME_BRANCH';
	public const VERIFIED_PROSPECT = 'VERIFIED_PROSPECT_SAME_BRANCH';
	public const NEW_PROSPECT      = 'NEW_PROSPECT_VERIFIED';

	/** Unicos estados que podem chegar a POST /api/v2/sales. */
	public const VERIFIED = array( self::VERIFIED_MEMBER, self::VERIFIED_PROSPECT, self::NEW_PROSPECT );

	public const MEMBER_OTHER_BRANCH   = 'existing_member_other_branch';
	public const PROSPECT_OTHER_BRANCH = 'prospect_other_branch';
	public const AMBIGUOUS             = 'identity_ambiguous';
	public const UNVERIFIABLE          = 'identity_unverifiable';
	public const CONFLICT              = 'identity_conflict';
	public const UNAVAILABLE           = 'evo_indisponivel';

	/* ---------------------------------------------------------------
	 * Normalizacao (helpers unicos)
	 * ------------------------------------------------------------- */

	public static function email( string $value ): string {
		return strtolower( trim( $value ) );
	}

	public static function document( string $value ): string {
		return preg_replace( '/\D/', '', $value ) ?? '';
	}

	/** Nacional (DDD + numero), sem DDI. */
	public static function phone( string $value ): string {
		return Contorno_Evo_Checkout::normalize_phone( $value );
	}

	/**
	 * 'exact' = mesmo DDD + mesmo numero (ambos com DDD).
	 * 'weak'  = mesmo DDD e mesmos 8 ultimos digitos (cadastro antigo sem o
	 *           9). NUNCA confirma identidade sozinho.
	 * ''      = nao confere.
	 */
	public static function phone_match( string $a, string $b ): string {
		$a = self::phone( $a );
		$b = self::phone( $b );

		if ( strlen( $a ) < 10 || strlen( $b ) < 10 ) {
			return '';
		}

		if ( $a === $b ) {
			return 'exact';
		}

		return substr( $a, 0, 2 ) === substr( $b, 0, 2 ) && substr( $a, -8 ) === substr( $b, -8 ) ? 'weak' : '';
	}

	/**
	 * E-mails e telefones que a EVO devolveu para um registro.
	 *
	 * @param array<string,mixed> $item
	 * @return array{emails:array<int,string>,phones:array<int,string>}
	 */
	public static function contacts( array $item ): array {
		$emails = array();
		$phones = array();

		foreach ( (array) ( $item['contacts'] ?? array() ) as $contact ) {
			$desc = is_array( $contact ) ? trim( (string) ( $contact['description'] ?? '' ) ) : '';
			if ( str_contains( $desc, '@' ) ) {
				$emails[] = self::email( $desc );
			} elseif ( '' !== $desc ) {
				$phones[] = self::phone( (string) ( $contact['ddi'] ?? '' ) . $desc );
			}
		}

		if ( ! empty( $item['email'] ) ) {
			$emails[] = self::email( (string) $item['email'] );
		}

		foreach ( array( 'cellphone', 'phone' ) as $key ) {
			if ( ! empty( $item[ $key ] ) ) {
				$phones[] = self::phone( (string) $item[ $key ] );
			}
		}

		return array( 'emails' => array_values( array_unique( $emails ) ), 'phones' => array_values( array_unique( $phones ) ) );
	}

	/** O registro tem EXATAMENTE este e-mail ou este WhatsApp? (so para o lead) */
	public static function contact_match( array $item, string $email, string $phone ): bool {
		$c = self::contacts( $item );

		if ( '' !== $email && in_array( self::email( $email ), $c['emails'], true ) ) {
			return true;
		}

		foreach ( $c['phones'] as $candidate ) {
			if ( 'exact' === self::phone_match( $candidate, $phone ) ) {
				return true;
			}
		}

		return false;
	}

	/* ---------------------------------------------------------------
	 * Etapa 1 — lead (NAO vincula identidade)
	 * ------------------------------------------------------------- */

	/**
	 * Ja existe cadastro com EXATAMENTE este e-mail/WhatsApp?
	 *
	 * Serve so para nao criar um segundo lead. Nao devolve id para venda.
	 *
	 * @return array{ok:bool,existing:bool,rejected:int}
	 */
	public static function lead_exists( string $email, string $phone, array $state ): array {
		$client   = new Contorno_Evo_Client();
		$existing = false;
		$rejected = 0;
		$filters  = array_filter( array( array( 'email' => $email ), array( 'phone' => $phone ) ), static fn ( $f ) => '' !== (string) reset( $f ) );

		foreach ( array( 'members_basic', 'prospects' ) as $source ) {
			foreach ( $filters as $filter ) {
				$found = 'members_basic' === $source ? $client->members_basic( $filter ) : $client->prospects( $filter );

				if ( ! $found['ok'] ) {
					return array( 'ok' => false, 'existing' => false, 'rejected' => $rejected );
				}

				foreach ( $found['items'] as $item ) {
					$id = self::id_label( $item );

					if ( self::contact_match( $item, $email, $phone ) ) {
						$existing = true;
						self::log( 'info', sprintf( 'identity_candidate_returned (lead, %s, filial %s): contato confere; nenhum lead novo criado. NAO e identidade para venda', $id, self::branch_label( $item ) ), $state );
					} else {
						++$rejected;
						self::log( 'warning', sprintf( 'identity_candidate_rejected (lead, %s, filial %s): EVO devolveu cadastro sem este e-mail/WhatsApp', $id, self::branch_label( $item ) ), $state );
					}
				}
			}
		}

		return array( 'ok' => true, 'existing' => $existing, 'rejected' => $rejected );
	}

	/* ---------------------------------------------------------------
	 * Etapa 2 — identidade financeira (CPF)
	 * ------------------------------------------------------------- */

	/**
	 * Resolve a identidade pelo CPF. Unico caminho para um VERIFIED_*.
	 *
	 * @param array<string,mixed>  $state
	 * @param array<string,string> $person  document (11 digitos validos),
	 *                                      birthday?, first_name, last_name,
	 *                                      email, phone (para pessoa nova)
	 * @return array{status:string,id_member:int,id_prospect:int,branch:int}
	 */
	public static function resolve( array $state, array $person ): array {
		$client = new Contorno_Evo_Client();
		$cpf    = self::document( (string) ( $person['document'] ?? '' ) );
		$target = (int) $state['id_branch'];
		$result = static fn ( string $status, int $member = 0, int $prospect = 0, int $branch = 0 ): array => array( 'status' => $status, 'id_member' => $member, 'id_prospect' => $prospect, 'branch' => $branch );

		if ( 11 !== strlen( $cpf ) ) {
			return $result( self::CONFLICT );
		}

		// 1. Discovery por CPF (candidatos, nunca identidade).
		$members   = $client->members_basic( array( 'document' => $cpf ) );
		$prospects = $client->prospects( array( 'document' => $cpf ) );

		if ( ! $members['ok'] || ! $prospects['ok'] ) {
			return $result( self::UNAVAILABLE );
		}

		$member_ids = array();
		foreach ( $members['items'] as $item ) {
			if ( (int) ( $item['idMember'] ?? 0 ) > 0 ) {
				$member_ids[ (int) $item['idMember'] ] = true;
			}
		}

		$prospect_strong = array();
		foreach ( $prospects['items'] as $item ) {
			$id_prospect = (int) ( $item['idProspect'] ?? 0 );
			if ( $id_prospect <= 0 ) {
				continue;
			}

			// Prospect ja convertido: quem vale e o aluno — verificado pelo perfil.
			if ( (int) ( $item['idMember'] ?? 0 ) > 0 ) {
				$member_ids[ (int) $item['idMember'] ] = true;
				continue;
			}

			if ( self::document( (string) ( $item['document'] ?? '' ) ) === $cpf ) {
				$prospect_strong[ $id_prospect ] = (int) ( $item['idBranch'] ?? 0 );
				self::log( 'info', sprintf( 'identity_candidate_returned (prospect idProspect %d, filial %s): CPF do registro confere', $id_prospect, self::branch_label( $item ) ), $state );
			} else {
				self::log( 'warning', sprintf( 'identity_candidate_rejected (prospect idProspect %d, filial %s): CPF do registro %s', $id_prospect, self::branch_label( $item ), '' === (string) ( $item['document'] ?? '' ) ? 'ausente' : 'diferente' ), $state );
			}
		}

		// 2. Verificacao forte de cada aluno: CPF do PERFIL completo.
		$member_strong = array();
		$unverifiable  = 0;

		foreach ( array_keys( $member_ids ) as $id_member ) {
			$profile = $client->member_profile( $id_member );

			if ( ! $profile['ok'] ) {
				if ( in_array( $profile['http'], array( 401, 403 ), true ) ) {
					++$unverifiable;
					self::log( 'error', sprintf( 'identity_candidate_rejected (aluno idMember %d): perfil GET /api/v2/members/{id} sem permissao (HTTP %d) — CPF nao comprovado', $id_member, $profile['http'] ), $state + array( 'http' => $profile['http'] ) );
					continue;
				}

				return $result( self::UNAVAILABLE );
			}

			$data = is_array( $profile['data'] ) ? $profile['data'] : array();

			if ( (int) ( $data['idMember'] ?? 0 ) === $id_member && self::document( (string) ( $data['document'] ?? '' ) ) === $cpf ) {
				$member_strong[ $id_member ] = (int) ( $data['idBranch'] ?? 0 );
				self::log( 'info', sprintf( 'identity_candidate_returned (aluno idMember %d, filial %s): CPF do perfil confere', $id_member, (string) ( $data['idBranch'] ?? '?' ) ), $state );

				if ( ! self::contact_match( $data, (string) ( $person['email'] ?? '' ), (string) ( $person['phone'] ?? '' ) ) ) {
					// CPF manda; contato divergente so fica registrado.
					self::log( 'info', sprintf( 'identity_verified com divergencia de contato (aluno idMember %d): e-mail/WhatsApp do cadastro diferem do digitado', $id_member ), $state );
				}
			} else {
				self::log( 'warning', sprintf( 'identity_candidate_rejected (aluno idMember %d, filial %s): CPF do perfil %s', $id_member, (string) ( $data['idBranch'] ?? '?' ), '' === (string) ( $data['document'] ?? '' ) ? 'ausente' : 'diferente' ), $state );
			}
		}

		// 3. Decisao.
		// Algum aluno com este CPF nao pode ser comprovado: ele pode ser um
		// segundo cadastro do mesmo CPF (ambiguidade invisivel). Nao vende e
		// nao cria prospect para contornar.
		if ( $unverifiable > 0 ) {
			self::log( 'error', sprintf( 'identity_unverifiable: %d aluno(s) encontrado(s) pelo CPF sem perfil completo legivel — venda bloqueada', $unverifiable ), $state );

			return $result( self::UNVERIFIABLE );
		}

		if ( count( $member_strong ) > 1 || ( 0 === count( $member_strong ) && count( $prospect_strong ) > 1 ) ) {
			self::log( 'error', sprintf( 'identity_ambiguous: %d aluno(s) e %d prospect(s) com o mesmo CPF (%s) — venda bloqueada', count( $member_strong ), count( $prospect_strong ), implode( ', ', array_merge( array_map( static fn ( $id ) => 'idMember ' . $id, array_keys( $member_strong ) ), array_map( static fn ( $id ) => 'idProspect ' . $id, array_keys( $prospect_strong ) ) ) ) ), $state );

			return $result( self::AMBIGUOUS );
		}

		if ( 1 === count( $member_strong ) ) {
			$id_member = (int) array_key_first( $member_strong );
			$branch    = (int) $member_strong[ $id_member ];

			if ( $branch !== $target ) {
				self::log( 'warning', sprintf( 'existing_member_other_branch: aluno idMember %d, filial atual %s, filial destino %d — venda bloqueada (transferencia e decisao do atendimento)', $id_member, $branch > 0 ? (string) $branch : '?', $target ), $state );

				return $result( self::MEMBER_OTHER_BRANCH, $id_member, 0, $branch );
			}

			self::log( 'info', sprintf( 'identity_verified: aluno idMember %d, filial %d (CPF do perfil confere)', $id_member, $branch ), $state );

			return $result( self::VERIFIED_MEMBER, $id_member, 0, $branch );
		}

		if ( 1 === count( $prospect_strong ) ) {
			$id_prospect = (int) array_key_first( $prospect_strong );
			$branch      = (int) $prospect_strong[ $id_prospect ];

			if ( $branch !== $target ) {
				self::log( 'warning', sprintf( 'prospect_other_branch: prospect idProspect %d, filial atual %s, filial destino %d — venda bloqueada (sem transferencia automatica)', $id_prospect, $branch > 0 ? (string) $branch : '?', $target ), $state );

				return $result( self::PROSPECT_OTHER_BRANCH, 0, $id_prospect, $branch );
			}

			self::log( 'info', sprintf( 'identity_verified: prospect idProspect %d, filial %d (CPF do registro confere)', $id_prospect, $branch ), $state );

			return $result( self::VERIFIED_PROSPECT, 0, $id_prospect, $branch );
		}

		// 4. Ninguem com este CPF: pessoa nova.
		self::log( 'info', 'identity_not_found: nenhum aluno ou prospect com este CPF', $state );

		return self::new_prospect( $state, $person, $cpf, $client );
	}

	/**
	 * Pessoa nova. O lead criado POR ESTA SESSAO na etapa 1 recebe o CPF;
	 * sem ele, cria-se o prospect completo. CPF ja existente na EVO -> conflito.
	 *
	 * @return array{status:string,id_member:int,id_prospect:int,branch:int}
	 */
	private static function new_prospect( array $state, array $person, string $cpf, Contorno_Evo_Client $client ): array {
		$target = (int) $state['id_branch'];
		$own    = (int) ( $state['lead_prospect_id'] ?? 0 );

		if ( $own > 0 ) {
			$patch = array(
				'idProspect'  => $own,
				'cpf'         => $cpf,
				'currentStep' => Contorno_Evo_Checkout::STEP_PAYMENT,
			);
			if ( '' !== (string) ( $person['birthday'] ?? '' ) ) {
				$patch['birthday'] = (string) $person['birthday'];
			}
			if ( Contorno_Evo_Settings::get( 'checkout_require_address', false ) ) {
				$patch += Contorno_Evo_Checkout::address_payload( $person );
			}

			$updated = $client->update_prospect( $patch );

			if ( $updated['ok'] ) {
				self::log( 'info', sprintf( 'new_prospect_created: lead desta sessao (idProspect %d) recebeu o CPF', $own ), $state );

				return array( 'status' => self::NEW_PROSPECT, 'id_member' => 0, 'id_prospect' => $own, 'branch' => $target );
			}

			return self::refused( $state, $updated, sprintf( 'completar o lead idProspect %d', $own ) );
		}

		if ( '' === (string) ( $person['first_name'] ?? '' ) || '' === (string) ( $person['last_name'] ?? '' ) || '' === (string) ( $person['email'] ?? '' ) ) {
			return array( 'status' => self::CONFLICT, 'id_member' => 0, 'id_prospect' => 0, 'branch' => 0 );
		}

		$created = $client->create_prospect( Contorno_Evo_Checkout::prospect_payload( $person, $target ) );

		if ( $created['ok'] ) {
			self::log( 'info', sprintf( 'new_prospect_created: idProspect %d com CPF, filial %d', $created['id_prospect'], $target ), $state );

			return array( 'status' => self::NEW_PROSPECT, 'id_member' => 0, 'id_prospect' => (int) $created['id_prospect'], 'branch' => $target );
		}

		return self::refused( $state, $created, 'criar prospect' );
	}

	/** EVO recusou gravar o CPF: 4xx = conflito (atendimento), resto = indisponivel. */
	private static function refused( array $state, array $response, string $what ): array {
		$conflict = (int) $response['http'] >= 400 && (int) $response['http'] < 500;

		self::log( 'error', sprintf( '%s: EVO recusou %s — %s', $conflict ? 'identity_conflict' : 'evo_indisponivel', $what, (string) $response['message'] ), $state + array( 'http' => $response['http'] ) );

		return array( 'status' => $conflict ? self::CONFLICT : self::UNAVAILABLE, 'id_member' => 0, 'id_prospect' => 0, 'branch' => 0 );
	}

	/* ---------------------------------------------------------------
	 * Guarda final (imediatamente antes de POST /api/v2/sales)
	 * ------------------------------------------------------------- */

	public static function cpf_hash( string $cpf ): string {
		return hash_hmac( 'sha256', 'identity|' . self::document( $cpf ), wp_salt( 'nonce' ) );
	}

	/**
	 * A sessao pode vender para este CPF? Qualquer inconsistencia = nao.
	 *
	 * @param array<string,mixed> $state
	 */
	public static function sale_allowed( array $state, string $cpf ): bool {
		$identity = (array) ( $state['identity'] ?? array() );
		$status   = (string) ( $identity['status'] ?? '' );

		if ( ! in_array( $status, self::VERIFIED, true ) ) {
			return false;
		}

		if ( ! hash_equals( (string) ( $identity['cpf_hash'] ?? '' ), self::cpf_hash( $cpf ) ) ) {
			return false;
		}

		if ( (int) ( $identity['branch'] ?? 0 ) !== (int) $state['id_branch'] ) {
			return false;
		}

		if ( self::VERIFIED_MEMBER === $status ) {
			return (int) $state['id_member'] > 0 && (int) $state['id_member'] === (int) ( $identity['id_member'] ?? 0 ) && (int) $state['id_prospect'] <= 0;
		}

		return (int) $state['id_member'] <= 0 && (int) $state['id_prospect'] > 0 && (int) $state['id_prospect'] === (int) ( $identity['id_prospect'] ?? 0 );
	}

	/* ---------------------------------------------------------------
	 * Auxiliares
	 * ------------------------------------------------------------- */

	private static function id_label( array $item ): string {
		if ( (int) ( $item['idProspect'] ?? 0 ) > 0 ) {
			return 'prospect idProspect ' . (int) $item['idProspect'];
		}

		return 'aluno idMember ' . (int) ( $item['idMember'] ?? 0 );
	}

	private static function branch_label( array $item ): string {
		return (int) ( $item['idBranch'] ?? 0 ) > 0 ? (string) (int) $item['idBranch'] : '?';
	}

	private static function log( string $level, string $message, array $state ): void {
		Contorno_Evo_Checkout::log( $level, $message, $state );
	}
}
