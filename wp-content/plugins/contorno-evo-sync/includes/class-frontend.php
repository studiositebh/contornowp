<?php
/**
 * Sobreposicao dos dados comerciais na leitura do repeater "plans".
 *
 * O contorno-core continua renderizando os mesmos cards com o mesmo
 * componente. Aqui, via filtro contorno_field_list, cada plano local ganha os
 * dados do EVO (quando vinculado por idMembership):
 *
 *   EVO manda:       name, price (+ price_from/price_note em promocao),
 *                    description, checkout_url, fidelity/card_note derivados
 *                    de duracao e parcelas (so se o editor deixou vazio).
 *   WordPress manda: ordem, id, badge, featured, benefits, price_label,
 *                    classes/design — intocados.
 *
 * Plano vinculado a membership inativo/ausente some do site (soft-disable).
 * Membership novo sem card local e acrescentado no fim, com apresentacao
 * padrao (opcional). Sem dados do EVO gravados (ex.: primeira ativacao ou
 * EVO fora do ar), a lista local passa intacta — nunca "pagina sem plano".
 *
 * So no frontend: no admin o editor ve e salva o que ele proprio cadastrou.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Frontend {

	public static function boot(): void {
		add_filter( 'contorno_field_list', array( __CLASS__, 'filter_plans' ), 10, 3 );
	}

	/**
	 * @param array<int,mixed> $list
	 * @return array<int,mixed>
	 */
	public static function filter_plans( array $list, string $name, int $post_id ): array {
		if ( 'plans' !== $name || $post_id <= 0 || is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return $list;
		}

		return self::merge( $list, $post_id );
	}

	/**
	 * @param array<int,mixed> $local
	 * @return array<int,array<string,mixed>>
	 */
	public static function merge( array $local, int $post_id ): array {
		$memberships = Contorno_Evo_Sync::stored( $post_id );

		if ( array() === $memberships ) {
			return $local;
		}

		$links         = Contorno_Evo_Mapping::links( $post_id );
		$hide_inactive = (bool) Contorno_Evo_Settings::get( 'hide_inactive', true );
		$add_new       = (bool) Contorno_Evo_Settings::get( 'show_unlisted_plans', false );
		$used          = array();
		$out           = array();

		foreach ( $local as $plan ) {
			if ( ! is_array( $plan ) ) {
				continue;
			}

			$id = Contorno_Evo_Mapping::membership_for_plan( $plan, $links );

			if ( $id <= 0 || ! isset( $memberships[ $id ] ) ) {
				$out[] = $plan; // sem vinculo: fica como o editor cadastrou
				continue;
			}

			$used[ $id ] = true;
			$m           = $memberships[ $id ];

			if ( $hide_inactive && 'active' !== ( $m['status'] ?? 'active' ) ) {
				continue; // soft-disable
			}

			$out[] = self::apply( $plan, $m );
		}

		// Unidade sem nenhum card cadastrado (unidade nova): mostra os planos
		// padrao da rede que estiverem ativos na filial, no lugar de pagina
		// sem plano. Contratos internos nunca entram.
		if ( array() === $out && array() === $local ) {
			foreach ( self::standard( $memberships ) as $key => $m ) {
				$out[] = self::apply(
					array(
						'id'       => 'evo-' . $key,
						'name'     => '',
						'benefits' => (array) ( $m['differentials'] ?? array() ),
						'badge'    => '',
						'featured' => 'black' === $key ? '1' : '',
					),
					$m
				);
			}
		}

		if ( $add_new ) {
			foreach ( $memberships as $id => $m ) {
				if ( isset( $used[ $id ] ) || 'active' !== ( $m['status'] ?? 'active' ) ) {
					continue;
				}
				$out[] = self::apply(
					array(
						'id'       => 'evo-' . $id,
						'name'     => '',
						'benefits' => (array) ( $m['differentials'] ?? array() ),
						'badge'    => '',
						'featured' => '',
					),
					$m
				);
			}
		}

		return $out;
	}

	/** Planos padrao da rede, na ordem dos cards. Nome da EVO normalizado. */
	public const STANDARD = array(
		'black' => 'black',
		'slim'  => 'slim',
		'fit'   => 'fit',
	);

	/**
	 * O contrato ativo mais recente de cada plano padrao presente na filial.
	 *
	 * @param array<int,array<string,mixed>> $memberships
	 * @return array<string,array<string,mixed>>
	 */
	public static function standard( array $memberships ): array {
		$out = array();

		foreach ( self::STANDARD as $key => $family ) {
			foreach ( $memberships as $id => $m ) {
				$names = array( Contorno_Evo_Sync::family( (string) ( $m['name'] ?? '' ) ), Contorno_Evo_Sync::family( (string) ( $m['display_name'] ?? '' ) ) );
				if ( 'active' !== ( $m['status'] ?? '' ) || (float) ( $m['value'] ?? 0 ) <= 0 || ! in_array( $family, $names, true ) ) {
					continue;
				}
				$best = $out[ $key ] ?? null;
				if ( null === $best || array( (string) ( $m['update_date'] ?? '' ), (int) $id ) > array( (string) ( $best['update_date'] ?? '' ), (int) ( $best['id_membership'] ?? 0 ) ) ) {
					$out[ $key ] = $m;
				}
			}
		}

		return $out;
	}

	/** "ASSINATURA CTN SLIM " -> "CTN Slim". */
	public static function pretty_name( string $name ): string {
		$name = trim( (string) preg_replace( '/^\s*assinatura\s+/iu', '', $name ) );
		$name = mb_convert_case( mb_strtolower( $name ), MB_CASE_TITLE );

		return (string) preg_replace_callback( '/\b(ctn|cdc|vip)\b/iu', static fn ( array $m ): string => mb_strtoupper( $m[1] ), $name );
	}

	/**
	 * Aplica os dados comerciais do EVO sobre um card local.
	 *
	 * @param array<string,mixed> $plan
	 * @param array<string,mixed> $m
	 * @return array<string,mixed>
	 */
	public static function apply( array $plan, array $m ): array {
		// stored() so exige id_membership; um registro gravado por outra versao
		// (ou restaurado de backup) pode nao ter estas chaves. Sem as guardas,
		// cada render do card emitia warning do PHP 8 no log.
		// Nome: o do card do site ("Black") vale; o da EVO e interno
		// ("ASSINATURA BLACK"). So um card sem nome (plano novo) usa o da EVO.
		$display = (string) ( ( $m['display_name'] ?? '' ) ?: ( $m['name'] ?? '' ) );
		if ( '' !== $display && '' === trim( (string) ( $plan['name'] ?? '' ) ) ) {
			$plan['name'] = self::pretty_name( $display );
		}

		$value = (float) ( $m['value'] ?? 0 );
		$promo = (float) ( $m['promo_value'] ?? 0 );
		$has_promo = $promo > 0 && $promo < $value && ( (int) ( $m['promo_months'] ?? 0 ) > 0 || (int) ( $m['promo_days'] ?? 0 ) > 0 );

		if ( $has_promo ) {
			$plan['price']      = $promo;
			$plan['price_from'] = $value;
			if ( empty( $plan['price_note'] ) ) {
				$months = (int) ( $m['promo_months'] ?? 0 );
				$plan['price_note'] = $months > 0
					? sprintf( _n( 'no 1º mês, depois %s', 'nos %2$d primeiros meses, depois %1$s', $months, 'contorno-evo' ), contorno_format_price( $value ), $months )
					: sprintf( /* translators: 1: days, 2: price */ __( 'nos primeiros %1$d dias, depois %2$s', 'contorno-evo' ), (int) ( $m['promo_days'] ?? 0 ), contorno_format_price( $value ) );
			}
		} elseif ( $value > 0 ) {
			$plan['price'] = $value;
		}

		if ( '' !== (string) ( $m['description'] ?? '' ) ) {
			$plan['description'] = (string) $m['description'];
		}

		if ( '' !== (string) ( $m['url_sale'] ?? '' ) ) {
			$plan['checkout_url'] = (string) $m['url_sale'];
		}

		// Duracao e parcelas: so preenchem campos que o editor deixou vazios.
		$duration = (int) ( $m['duration'] ?? 0 );
		if ( $duration > 0 && empty( $plan['fidelity'] ) ) {
			$type = strtolower( (string) ( $m['duration_type'] ?? '' ) );
			$plan['fidelity'] = str_contains( $type, 'dia' ) || str_contains( $type, 'day' )
				? sprintf( _n( '%d dia', '%d dias', $duration, 'contorno-evo' ), $duration )
				: sprintf( _n( 'Fidelidade %d mês', 'Fidelidade %d meses', $duration, 'contorno-evo' ), $duration );
		}

		$installments = (int) ( $m['max_installments'] ?? 0 );
		if ( $installments > 1 && empty( $plan['card_note'] ) ) {
			$plan['card_note'] = sprintf( /* translators: %d: installments */ __( 'Em até %dx no cartão', 'contorno-evo' ), $installments );
		}

		$plan['evo_membership_id'] = (int) ( $m['id_membership'] ?? 0 );
		$plan['evo']               = array(
			'duration'         => $duration,
			'duration_type'    => (string) ( $m['duration_type'] ?? '' ),
			'max_installments' => $installments,
			'status'           => (string) ( $m['status'] ?? 'active' ),
			'update_date'      => (string) ( $m['update_date'] ?? '' ),
		);

		return $plan;
	}
}
