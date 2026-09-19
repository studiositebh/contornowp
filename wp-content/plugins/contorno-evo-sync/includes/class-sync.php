<?php
/**
 * Motor de sincronizacao.
 *
 * Por unidade vinculada:
 *   memberships do EVO (filial) -> normaliza -> compara com o que esta gravado
 *   (_contorno_evo_memberships, chave idMembership) -> upsert.
 *
 * Regras:
 *  - Nunca duplica: a identidade e idMembership.
 *  - Plano que sumiu da resposta NAO e apagado: status "missing-from-evo" e
 *    guarda "missing_since". Plano com inactive=true: status "inactive".
 *    Os dois deixam de aparecer publicamente (soft-disable).
 *  - Antes de gravar, a versao anterior vai para _contorno_evo_snapshot.
 *  - Se a busca de uma filial falhar, a unidade fica INTOCADA (fallback: o
 *    site continua com a ultima versao boa). Falha em uma unidade nao
 *    interrompe as outras.
 *  - Dry-run: calcula tudo e nao grava nada.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Sync {

	public const LOCK = 'contorno_evo_sync_lock';

	/** Campos comerciais comparados no diff. */
	private const COMPARE = array( 'name', 'display_name', 'value', 'description', 'url_sale', 'duration', 'duration_type', 'max_installments', 'inactive', 'promo_value', 'promo_months', 'promo_days', 'promo_installments', 'promo_type', 'differentials', 'external_sale' );

	/**
	 * @param array{dry_run?:bool,branch?:int,post_id?:int,trigger?:string} $args
	 * @return array<string,mixed> resumo + diffs
	 */
	public static function run( array $args = array() ): array {
		$dry_run = ! empty( $args['dry_run'] );
		$trigger = (string) ( $args['trigger'] ?? 'manual' );
		$only_branch = (int) ( $args['branch'] ?? 0 );
		$only_post   = (int) ( $args['post_id'] ?? 0 );

		$summary = array(
			'dry_run'    => $dry_run,
			'started'    => time(),
			'units'      => 0,
			'unmapped'   => 0,
			'received'   => 0,
			'created'    => 0,
			'updated'    => 0,
			'unchanged'  => 0,
			'disabled'   => 0,
			'restored'   => 0,
			'errors'     => 0,
			'requests'   => 0,
			'diffs'      => array(),
			'messages'   => array(),
		);

		if ( ! Contorno_Evo_Settings::has_credentials() ) {
			$summary['errors']++;
			$summary['messages'][] = __( 'Credenciais EVO não configuradas.', 'contorno-evo' );

			return $summary;
		}

		if ( ! $dry_run && ! self::lock() ) {
			$summary['errors']++;
			$summary['messages'][] = __( 'Outra sincronização está em andamento.', 'contorno-evo' );

			return $summary;
		}

		try {
			$client  = new Contorno_Evo_Client();
			$targets = array();

			foreach ( Contorno_Evo_Mapping::posts() as $post ) {
				if ( $only_post > 0 && $post->ID !== $only_post ) {
					continue;
				}

				$branch = Contorno_Evo_Mapping::branch( $post->ID );

				if ( $only_branch > 0 && $branch !== $only_branch ) {
					continue;
				}

				if ( $branch <= 0 ) {
					++$summary['unmapped'];
					continue;
				}

				$targets[] = array( 'post' => $post, 'branch' => $branch );
			}

			$summary['units'] = count( $targets );

			if ( array() === $targets ) {
				$summary['messages'][] = __( 'Nenhuma unidade vinculada a uma filial EVO.', 'contorno-evo' );

				return $summary;
			}

			// Busca: uma leitura global paginada (chave multi-filial devolve todas
			// as filiais; chave de filial devolve so a dela). Filiais que nao
			// vierem no global sao buscadas por idBranch quando o modo permitir.
			$by_branch = array();
			$mode      = (string) Contorno_Evo_Settings::get( 'fetch_mode', 'auto' );
			$global_ok = false;

			if ( 'per-branch' !== $mode ) {
				$global = $client->memberships( null );
				$summary['requests'] += $global['pages'];

				if ( $global['ok'] ) {
					$global_ok = true;
					foreach ( $global['items'] as $item ) {
						$by_branch[ (int) ( $item['idBranch'] ?? 0 ) ][] = $item;
					}
				} else {
					self::log( 'error', $global['message'], array( 'action' => 'fetch', 'http' => $global['http'] ), $dry_run );
					$summary['messages'][] = $global['message'];
					if ( 'global' === $mode ) {
						$summary['errors']++;

						return $summary;
					}
				}
			}

			foreach ( $targets as $target ) {
				/** @var WP_Post $post */
				$post   = $target['post'];
				$branch = (int) $target['branch'];
				$items  = $by_branch[ $branch ] ?? null;

				if ( null === $items && ( 'per-branch' === $mode || 'auto' === $mode ) ) {
					$fetch = $client->memberships( $branch );
					$summary['requests'] += $fetch['pages'];

					if ( ! $fetch['ok'] ) {
						++$summary['errors'];
						self::log( 'error', $fetch['message'], array( 'action' => 'fetch', 'unit' => $post->post_name, 'id_branch' => $branch, 'http' => $fetch['http'] ), $dry_run );
						$summary['messages'][] = sprintf( '%s: %s', $post->post_title, $fetch['message'] );
						continue; // unidade intocada
					}

					$items = $fetch['items'];
				}

				if ( null === $items ) {
					// Global OK mas a filial nao veio: sem planos nessa filial.
					$items = array();
				}

				// Chave multi-filial pode devolver planos de outras filiais no global;
				// garante que so os da filial vinculada entrem.
				$items = array_values( array_filter( $items, static fn ( array $i ): bool => (int) ( $i['idBranch'] ?? $branch ) === $branch ) );

				$result = self::reconcile( $post->ID, $branch, $items, $dry_run );

				$summary['received']  += $result['received'];
				$summary['created']   += $result['created'];
				$summary['updated']   += $result['updated'];
				$summary['unchanged'] += $result['unchanged'];
				$summary['disabled']  += $result['disabled'];
				$summary['restored']  += $result['restored'];

				if ( array() !== $result['diffs'] ) {
					$summary['diffs'][ $post->post_name ] = array(
						'title'  => $post->post_title,
						'branch' => $branch,
						'diffs'  => $result['diffs'],
					);
				}
			}

			$summary['finished'] = time();

			if ( ! $dry_run ) {
				Contorno_Evo_Settings::update_status(
					array(
						'last_sync'    => $summary['finished'],
						'last_trigger' => $trigger,
						'last_summary' => array_diff_key( $summary, array( 'diffs' => 1, 'messages' => 1 ) ),
						'connected'    => $global_ok || $summary['errors'] < $summary['units'],
					)
				);
			}

			self::log(
				$summary['errors'] > 0 ? 'warning' : 'info',
				sprintf(
					'%s %d unidades, %d planos recebidos, %d novos, %d atualizados, %d sem alteração, %d desativados, %d erros (%d requisições)',
					$dry_run ? '[dry-run]' : '[' . $trigger . ']',
					$summary['units'],
					$summary['received'],
					$summary['created'],
					$summary['updated'],
					$summary['unchanged'],
					$summary['disabled'],
					$summary['errors'],
					$summary['requests']
				),
				array( 'action' => $dry_run ? 'dry-run' : 'sync' ),
				false
			);

			return $summary;
		} finally {
			if ( ! $dry_run ) {
				self::unlock();
			}
		}
	}

	/**
	 * Reconcilia UMA unidade. Escrita atomica: monta a lista inteira em memoria
	 * e grava de uma vez (snapshot antes).
	 *
	 * @param array<int,array<string,mixed>> $items
	 * @return array<string,mixed>
	 */
	public static function reconcile( int $post_id, int $branch, array $items, bool $dry_run ): array {
		$stored   = self::stored( $post_id );
		$incoming = array();
		$now      = time();
		$counts   = array( 'received' => count( $items ), 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'disabled' => 0, 'restored' => 0, 'diffs' => array() );

		foreach ( $items as $item ) {
			$normalized = self::normalize( $item, $branch );
			if ( 0 === $normalized['id_membership'] ) {
				continue;
			}
			$incoming[ $normalized['id_membership'] ] = $normalized;
		}

		$next = array();

		foreach ( $incoming as $id => $plan ) {
			$plan['status']       = $plan['inactive'] ? 'inactive' : 'active';
			$plan['last_seen']    = $now;
			$plan['missing_since'] = 0;

			if ( ! isset( $stored[ $id ] ) ) {
				$plan['first_seen'] = $now;
				++$counts['created'];
				$counts['diffs'][] = array( 'type' => 'new', 'id_membership' => $id, 'name' => $plan['display_name'], 'changes' => array() );
				$next[ $id ] = $plan;
				continue;
			}

			$previous           = $stored[ $id ];
			$plan['first_seen'] = (int) ( $previous['first_seen'] ?? $now );
			$changes            = self::diff( $previous, $plan );

			if ( 'missing-from-evo' === ( $previous['status'] ?? '' ) ) {
				++$counts['restored'];
				$changes['status'] = array( 'missing-from-evo', $plan['status'] );
			} elseif ( ( $previous['status'] ?? '' ) !== $plan['status'] ) {
				$changes['status'] = array( $previous['status'] ?? '', $plan['status'] );
				if ( 'inactive' === $plan['status'] ) {
					++$counts['disabled'];
				}
			}

			if ( array() === $changes ) {
				++$counts['unchanged'];
			} else {
				++$counts['updated'];
				$counts['diffs'][] = array( 'type' => 'update', 'id_membership' => $id, 'name' => $plan['display_name'], 'changes' => $changes );
			}

			$next[ $id ] = $plan;
		}

		// Sumiram da resposta: soft-disable, mantendo historico.
		foreach ( $stored as $id => $previous ) {
			if ( isset( $next[ $id ] ) ) {
				continue;
			}

			$was_visible = 'active' === ( $previous['status'] ?? 'active' );
			$previous['status']        = 'missing-from-evo';
			$previous['missing_since'] = (int) ( $previous['missing_since'] ?? 0 ) ?: $now;
			$next[ $id ]               = $previous;

			if ( $was_visible ) {
				++$counts['disabled'];
				$counts['diffs'][] = array( 'type' => 'missing', 'id_membership' => $id, 'name' => (string) ( $previous['display_name'] ?? $id ), 'changes' => array( 'status' => array( 'active', 'missing-from-evo' ) ) );
			}
		}

		if ( $dry_run ) {
			return $counts;
		}

		$changed = $counts['created'] + $counts['updated'] + $counts['disabled'] + $counts['restored'] > 0;

		if ( $changed ) {
			update_post_meta( $post_id, CONTORNO_EVO_META_SNAPSHOT, wp_json_encode( array( 'time' => $now, 'memberships' => array_values( $stored ) ) ) );
			update_post_meta( $post_id, CONTORNO_EVO_META_MEMBERSHIPS, wp_json_encode( array_values( $next ) ) );
			self::learn_links( $post_id, $next );
		}

		update_post_meta(
			$post_id,
			CONTORNO_EVO_META_LAST_SYNC,
			array( 'time' => $now, 'branch' => $branch, 'received' => $counts['received'], 'created' => $counts['created'], 'updated' => $counts['updated'], 'disabled' => $counts['disabled'] )
		);

		foreach ( $counts['diffs'] as $diff ) {
			self::log( 'info', sprintf( '%s: %s (%s)', $diff['type'], $diff['name'], implode( ', ', array_keys( $diff['changes'] ) ) ), array( 'action' => 'upsert', 'unit' => (string) get_post_field( 'post_name', $post_id ), 'id_branch' => $branch, 'id_membership' => $diff['id_membership'] ), false );
		}

		return $counts;
	}

	/**
	 * Aprende o vinculo plano local -> idMembership pela URL de checkout, para
	 * sobreviver a re-importacoes do dataset. Nunca sobrescreve vinculo explicito.
	 *
	 * @param array<int,array<string,mixed>> $memberships
	 */
	private static function learn_links( int $post_id, array $memberships ): void {
		$links = Contorno_Evo_Mapping::links( $post_id );

		foreach ( Contorno_Evo_Mapping::local_plans( $post_id ) as $plan ) {
			$local_id = (string) ( $plan['id'] ?? '' );
			if ( '' === $local_id || isset( $links[ $local_id ] ) ) {
				continue;
			}
			$id = Contorno_Evo_Mapping::membership_for_plan( $plan, array() );
			if ( $id > 0 && isset( $memberships[ $id ] ) ) {
				$links[ $local_id ] = $id;
			}
		}

		if ( array() !== $links ) {
			Contorno_Evo_Mapping::save_links( $post_id, $links );
		}
	}

	/**
	 * Normaliza um ContratosResumoApiViewModel. Nomes de campo conforme docs.
	 *
	 * @param array<string,mixed> $item
	 * @return array<string,mixed>
	 */
	public static function normalize( array $item, int $branch ): array {
		$differentials = array();
		foreach ( (array) ( $item['differentials'] ?? array() ) as $d ) {
			if ( is_array( $d ) && ! empty( $d['title'] ) ) {
				$differentials[ (int) ( $d['order'] ?? count( $differentials ) ) ] = sanitize_text_field( (string) $d['title'] );
			}
		}
		ksort( $differentials );

		return array(
			'id_membership'      => (int) ( $item['idMembership'] ?? 0 ),
			'id_branch'          => (int) ( $item['idBranch'] ?? $branch ),
			'name'               => sanitize_text_field( (string) ( $item['nameMembership'] ?? '' ) ),
			'display_name'       => sanitize_text_field( (string) ( $item['displayName'] ?? $item['nameMembership'] ?? '' ) ),
			'membership_type'    => sanitize_text_field( (string) ( $item['membershipType'] ?? '' ) ),
			'value'              => round( (float) ( $item['value'] ?? 0 ), 2 ),
			'description'        => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
			'url_sale'           => esc_url_raw( (string) ( $item['urlSale'] ?? '' ) ),
			'duration'           => (int) ( $item['duration'] ?? 0 ),
			'duration_type'      => sanitize_text_field( (string) ( $item['durationType'] ?? '' ) ),
			'max_installments'   => (int) ( $item['maxAmountInstallments'] ?? 0 ),
			'inactive'           => ! empty( $item['inactive'] ),
			'external_sale'      => ! empty( $item['externalSaleAvailable'] ),
			'update_date'        => sanitize_text_field( (string) ( $item['updateDate'] ?? '' ) ),
			'promo_type'         => (int) ( $item['typePromotionalPeriod'] ?? 0 ),
			'promo_value'        => round( (float) ( $item['valuePromotionalPeriod'] ?? 0 ), 2 ),
			'promo_months'       => (int) ( $item['monthsPromotionalPeriod'] ?? 0 ),
			'promo_days'         => (int) ( $item['daysPromotionalPeriod'] ?? 0 ),
			'promo_installments' => (int) ( $item['installmentsPromotionalPeriod'] ?? 0 ),
			'min_stay'           => (int) ( $item['minPeriodStayMembership'] ?? 0 ),
			'differentials'      => array_values( $differentials ),
		);
	}

	/**
	 * @param array<string,mixed> $before
	 * @param array<string,mixed> $after
	 * @return array<string,array{0:mixed,1:mixed}>
	 */
	private static function diff( array $before, array $after ): array {
		$changes = array();

		foreach ( self::COMPARE as $key ) {
			$a = $before[ $key ] ?? null;
			$b = $after[ $key ] ?? null;

			if ( is_float( $a ) || is_float( $b ) ) {
				if ( abs( (float) $a - (float) $b ) > 0.004 ) {
					$changes[ $key ] = array( $a, $b );
				}
				continue;
			}

			if ( $a != $b ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- tipos normalizados variam entre versoes gravadas.
				$changes[ $key ] = array( $a, $b );
			}
		}

		return $changes;
	}

	/**
	 * Memberships gravados, indexados por idMembership.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function stored( int $post_id ): array {
		$raw = get_post_meta( $post_id, CONTORNO_EVO_META_MEMBERSHIPS, true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = json_decode( $raw, true );
		}

		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			if ( is_array( $row ) && ! empty( $row['id_membership'] ) ) {
				$out[ (int) $row['id_membership'] ] = $row;
			}
		}

		return $out;
	}

	/** Volta a unidade para a versao anterior (snapshot). */
	public static function rollback( int $post_id ): bool {
		$raw = get_post_meta( $post_id, CONTORNO_EVO_META_SNAPSHOT, true );
		$snap = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;

		if ( ! is_array( $snap ) || ! isset( $snap['memberships'] ) ) {
			return false;
		}

		update_post_meta( $post_id, CONTORNO_EVO_META_MEMBERSHIPS, wp_json_encode( array_values( (array) $snap['memberships'] ) ) );
		self::log( 'warning', 'rollback para snapshot de ' . wp_date( 'd/m/Y H:i', (int) ( $snap['time'] ?? 0 ) ), array( 'action' => 'rollback', 'unit' => (string) get_post_field( 'post_name', $post_id ) ), false );

		return true;
	}

	private static function lock(): bool {
		if ( get_transient( self::LOCK ) ) {
			return false;
		}
		set_transient( self::LOCK, time(), 15 * MINUTE_IN_SECONDS );

		return true;
	}

	private static function unlock(): void {
		delete_transient( self::LOCK );
	}

	/**
	 * @param array<string,mixed> $context
	 */
	private static function log( string $level, string $message, array $context, bool $dry_run ): void {
		if ( $dry_run ) {
			return;
		}
		Contorno_Evo_Log::add( $level, $message, $context );
	}
}
