<?php
/**
 * Vinculo persistente Unidade/CTN <-> filial EVO (idBranch).
 *
 * Fonte de verdade: meta _contorno_evo_branch_id (sobrevive ao importador do
 * dataset). Fallbacks, nesta ordem, apenas para SUGERIR/inicializar:
 *   1. campo evo_branch_id do registro (contorno-core / dataset);
 *   2. idBranch embutido nas URLs de checkout ja cadastradas
 *      (/contornodocorpo/{idBranch}/site/landing-page/checkout/{idMembership}/0);
 *   3. mapa por slug do contorno-core (contorno_evo_branch_for_slug).
 *
 * Nenhuma associacao definitiva e feita por semelhanca de nome.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Mapping {

	public const CHECKOUT_PATTERN = '#/contornodocorpo/(\d+)/site/landing-page/checkout/(\d+)/#i';

	/**
	 * Todas as unidades e CTNs publicadas.
	 *
	 * @return WP_Post[]
	 */
	public static function posts(): array {
		return get_posts(
			array(
				'post_type'      => array( CONTORNO_CPT_UNIT, CONTORNO_CPT_CTN ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/** idBranch definitivo (0 = nao vinculada). */
	public static function branch( int $post_id ): int {
		$explicit = (int) get_post_meta( $post_id, CONTORNO_EVO_META_BRANCH, true );

		if ( $explicit > 0 ) {
			return $explicit;
		}

		// Sem vinculo explicito ainda: usa a sugestao mais confiavel, se houver.
		return self::suggest( $post_id );
	}

	public static function is_explicit( int $post_id ): bool {
		return (int) get_post_meta( $post_id, CONTORNO_EVO_META_BRANCH, true ) > 0;
	}

	public static function set_branch( int $post_id, int $id_branch ): void {
		if ( $id_branch > 0 ) {
			update_post_meta( $post_id, CONTORNO_EVO_META_BRANCH, $id_branch );
		} else {
			delete_post_meta( $post_id, CONTORNO_EVO_META_BRANCH );
		}
	}

	/**
	 * Sugestao de idBranch a partir do que ja existe no cadastro (nunca por nome).
	 */
	public static function suggest( int $post_id ): int {
		$field = (int) contorno_field_text( 'evo_branch_id', $post_id );
		if ( $field > 0 ) {
			return $field;
		}

		$from_urls = self::branches_from_checkout_urls( $post_id );
		if ( 1 === count( $from_urls ) ) {
			return (int) array_key_first( $from_urls );
		}

		if ( function_exists( 'contorno_evo_branch_for_slug' ) ) {
			$slug = (int) contorno_evo_branch_for_slug( (string) get_post_field( 'post_name', $post_id ) );
			if ( $slug > 0 ) {
				return $slug;
			}
		}

		return 0;
	}

	/** Origem da sugestao, para a tela de mapeamento. */
	public static function suggestion_source( int $post_id ): string {
		if ( self::is_explicit( $post_id ) ) {
			return 'explicit';
		}
		if ( (int) contorno_field_text( 'evo_branch_id', $post_id ) > 0 ) {
			return 'field';
		}
		if ( 1 === count( self::branches_from_checkout_urls( $post_id ) ) ) {
			return 'checkout';
		}
		if ( function_exists( 'contorno_evo_branch_for_slug' ) && '' !== contorno_evo_branch_for_slug( (string) get_post_field( 'post_name', $post_id ) ) ) {
			return 'slug';
		}

		return 'none';
	}

	/**
	 * idBranch => quantidade de URLs de checkout que apontam para ele.
	 *
	 * @return array<int,int>
	 */
	public static function branches_from_checkout_urls( int $post_id ): array {
		$found = array();
		$urls  = array( contorno_field_text( 'checkout_url', $post_id ) );

		foreach ( self::local_plans( $post_id ) as $plan ) {
			$urls[] = (string) ( $plan['checkout_url'] ?? '' );
		}

		foreach ( $urls as $url ) {
			if ( preg_match( self::CHECKOUT_PATTERN, $url, $m ) ) {
				$found[ (int) $m[1] ] = ( $found[ (int) $m[1] ] ?? 0 ) + 1;
			}
		}

		return $found;
	}

	/**
	 * Planos LOCAIS (o que o editor cadastrou), sem a sobreposicao do EVO.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function local_plans( int $post_id ): array {
		$raw = get_post_meta( $post_id, contorno_meta_key( 'plans' ), true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = json_decode( $raw, true );
		}

		return is_array( $raw ) ? array_values( array_filter( $raw, 'is_array' ) ) : array();
	}

	/**
	 * idMembership de um plano local: subcampo explicito > mapa de vinculos > URL de checkout.
	 *
	 * @param array<string,mixed> $plan
	 * @param array<string,int>   $links  id local => idMembership (meta _contorno_evo_links)
	 */
	public static function membership_for_plan( array $plan, array $links ): int {
		$explicit = (int) ( $plan['evo_membership_id'] ?? 0 );
		if ( $explicit > 0 ) {
			return $explicit;
		}

		$local_id = (string) ( $plan['id'] ?? '' );
		if ( '' !== $local_id && ! empty( $links[ $local_id ] ) ) {
			return (int) $links[ $local_id ];
		}

		if ( preg_match( self::CHECKOUT_PATTERN, (string) ( $plan['checkout_url'] ?? '' ), $m ) ) {
			return (int) $m[2];
		}

		return 0;
	}

	/**
	 * @return array<string,int>
	 */
	public static function links( int $post_id ): array {
		$links = get_post_meta( $post_id, CONTORNO_EVO_META_LINKS, true );

		return is_array( $links ) ? array_map( 'intval', $links ) : array();
	}

	/**
	 * @param array<string,int> $links
	 */
	public static function save_links( int $post_id, array $links ): void {
		update_post_meta( $post_id, CONTORNO_EVO_META_LINKS, array_map( 'intval', $links ) );
	}
}
