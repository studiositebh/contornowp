<?php
/**
 * Consultas e normalizacao de Unidades.
 *
 * Porte de src/lib/contorno/units.ts + search.ts. As unidades vivem no CPT
 * `unidade`; este arquivo e a unica camada que os templates/shortcodes usam
 * para le-las.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Busca unidades.
 *
 * @param array<string,mixed> $args
 * @return WP_Post[]
 */
function contorno_get_units( array $args = array() ): array {
	$defaults = array(
		'post_type'              => CONTORNO_CPT_UNIT,
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'ignore_sticky_posts'    => true,
	);

	$query_args = array_merge( $defaults, $args );

	// Filtro por destaque na Home — respeita a ordem definida em menu_order.
	if ( ! empty( $args['featured_only'] ) ) {
		unset( $query_args['featured_only'] );
		$query_args['meta_query'] = array(
			array(
				'key'     => contorno_meta_key( 'featured' ),
				'value'   => '1',
				'compare' => '=',
			),
		);
		$query_args['orderby'] = 'menu_order title';
	}

	// Filtro por cidade (slug de termo).
	if ( ! empty( $args['city'] ) ) {
		unset( $query_args['city'] );
		$query_args['tax_query'] = array(
			array(
				'taxonomy' => CONTORNO_TAX_CITY,
				'field'    => 'slug',
				'terms'    => (array) $args['city'],
			),
		);
	}

	$query = new WP_Query( $query_args );

	return $query->posts;
}

function contorno_get_unit_by_slug( string $slug ): ?WP_Post {
	$posts = get_posts(
		array(
			'post_type'      => CONTORNO_CPT_UNIT,
			'name'           => sanitize_title( $slug ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		)
	);

	return $posts[0] ?? null;
}

/**
 * Normaliza texto para busca — porte de normalize() em search.ts
 * (remove acentos, minusculas, colapsa espacos).
 */
function contorno_normalize_search( string $value ): string {
	$value = remove_accents( $value );
	$value = strtolower( $value );
	$value = (string) preg_replace( '/\s+/', ' ', $value );

	return trim( $value );
}

/**
 * Payload de busca de uma unidade — usado pelo filtro client-side, que
 * reproduz filterUnits() do React (nome, cidade, bairro, endereco, CEP, tipo).
 */
function contorno_unit_search_haystack( int $post_id ): string {
	$parts = array(
		get_the_title( $post_id ),
		contorno_field_text( 'city', $post_id ),
		contorno_field_text( 'neighborhood', $post_id ),
		contorno_field_text( 'address', $post_id ),
		contorno_field_text( 'postal_code', $post_id ),
		contorno_field_text( 'kind', $post_id ),
	);

	return contorno_normalize_search( implode( ' ', $parts ) );
}

function contorno_unit_postal_digits( int $post_id ): string {
	$digits = preg_replace( '/\D/', '', contorno_field_text( 'postal_code', $post_id ) );

	return is_string( $digits ) ? $digits : '';
}

/**
 * Preco formatado em BRL — espelha toLocaleString('pt-BR') do React.
 */
function contorno_format_price( float $value ): string {
	return 'R$ ' . number_format( $value, 2, ',', '.' );
}

/**
 * Preco "a partir de" da unidade: campo explicito, senao o menor preco de plano.
 */
function contorno_unit_starting_price( ?int $post_id = null ): float {
	$post_id = $post_id ?: (int) get_the_ID();

	$explicit = (float) contorno_field( 'starting_price', $post_id, 0 );
	if ( $explicit > 0 ) {
		return $explicit;
	}

	$prices = array();
	foreach ( contorno_field_list( 'plans', $post_id ) as $plan ) {
		if ( is_array( $plan ) && ! empty( $plan['price'] ) ) {
			$prices[] = (float) $plan['price'];
		}
	}

	return array() !== $prices ? min( $prices ) : 0.0;
}

/**
 * URL de checkout efetiva de um plano, com fallback para a unidade.
 *
 * @param array<string,mixed> $plan
 */
function contorno_plan_checkout_url( array $plan, ?int $post_id = null ): string {
	$url = isset( $plan['checkout_url'] ) ? trim( (string) $plan['checkout_url'] ) : '';

	if ( '' !== $url ) {
		return $url;
	}

	return contorno_field_text( 'checkout_url', $post_id );
}

/**
 * Link do Google Maps a partir do maps_query ou do endereco.
 */
function contorno_maps_url( ?int $post_id = null ): string {
	$query = contorno_field_text( 'maps_query', $post_id );

	if ( '' === $query ) {
		$query = trim(
			implode(
				' ',
				array_filter(
					array(
						get_the_title( $post_id ),
						contorno_field_text( 'address', $post_id ),
						contorno_field_text( 'city', $post_id ),
						contorno_field_text( 'state', $post_id ),
					)
				)
			)
		);
	}

	if ( '' === $query ) {
		return '';
	}

	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query );
}

/**
 * URL do mapa embutido (iframe), sem chave de API.
 */
function contorno_maps_embed_url( ?int $post_id = null ): string {
	$explicit = contorno_field_text( 'map_embed_url', $post_id );
	if ( '' !== $explicit ) {
		return $explicit;
	}

	$query = contorno_field_text( 'maps_query', $post_id );
	if ( '' === $query ) {
		$query = contorno_field_text( 'address', $post_id );
	}

	if ( '' === $query ) {
		return '';
	}

	return 'https://www.google.com/maps?q=' . rawurlencode( $query ) . '&output=embed';
}
