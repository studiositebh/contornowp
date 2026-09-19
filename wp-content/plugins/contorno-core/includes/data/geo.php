<?php
/**
 * Geolocalizacao: busca de unidades por CEP e raio.
 *
 * O visitante digita um CEP; ele vira lat/lng (BrasilAPI -> ViaCEP ->
 * Nominatim, com cache em transient) e as unidades sao ordenadas pela
 * distancia em linha reta (Haversine) usando os campos latitude/longitude
 * de cada `unidade`. Nenhuma chave de API e necessaria.
 *
 * Tambem expoe contorno_geocode_address(), usada pelo `wp contorno geocode`
 * para preencher coordenadas de unidades cadastradas sem lat/lng.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Raios (km) oferecidos ao visitante. O primeiro que casar com CONTORNO_GEO_DEFAULT_RADIUS e o padrao. */
const CONTORNO_GEO_RADIUS_OPTIONS = array( 10, 25, 50, 100 );
const CONTORNO_GEO_DEFAULT_RADIUS = 25;

/** Quantas unidades mostrar quando nenhuma cai dentro do raio. */
const CONTORNO_GEO_NEAREST_FALLBACK = 3;

/** Nome do parametro de raio na URL (/unidades/?q=30140000&raio=50). */
const CONTORNO_GEO_RADIUS_PARAM = 'raio';

/** Identificacao exigida pela politica de uso do Nominatim. */
const CONTORNO_GEO_USER_AGENT = 'ContornoDoCorpo-WP/1.0 (contato@contornodocorpo.com.br)';

/**
 * Extrai os 8 digitos de um CEP. Retorna '' se o termo nao for um CEP.
 *
 * Aceita "30140-000", "30140000", "30.140-000". Um termo com letras ou com
 * quantidade diferente de 8 digitos nao e tratado como CEP — cai na busca
 * textual normal (nome, bairro, cidade).
 */
function contorno_cep_digits( string $value ): string {
	$trimmed = trim( $value );

	if ( '' === $trimmed || 1 !== preg_match( '/^[\d.\-\s]+$/', $trimmed ) ) {
		return '';
	}

	$digits = (string) preg_replace( '/\D/', '', $trimmed );

	return 8 === strlen( $digits ) ? $digits : '';
}

function contorno_format_cep( string $digits ): string {
	return 8 === strlen( $digits ) ? substr( $digits, 0, 5 ) . '-' . substr( $digits, 5 ) : $digits;
}

/**
 * Coordenadas da unidade ou null quando nao cadastradas.
 *
 * @return array{0: float, 1: float}|null
 */
function contorno_unit_coords( int $post_id ): ?array {
	$lat = contorno_field_text( 'latitude', $post_id );
	$lng = contorno_field_text( 'longitude', $post_id );

	if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
		return null;
	}

	$lat = (float) $lat;
	$lng = (float) $lng;

	if ( 0.0 === $lat && 0.0 === $lng ) {
		return null;
	}

	return array( $lat, $lng );
}

/**
 * Distancia em km em linha reta (Haversine).
 */
function contorno_distance_km( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
	$earth = 6371.0;
	$d_lat = deg2rad( $lat2 - $lat1 );
	$d_lng = deg2rad( $lng2 - $lng1 );

	$a = sin( $d_lat / 2 ) ** 2
		+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lng / 2 ) ** 2;

	return $earth * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
}

/**
 * "850 m", "3,2 km", "48 km".
 */
function contorno_format_distance( float $km ): string {
	if ( $km < 1 ) {
		return sprintf( /* translators: %d: meters */ __( '%d m', 'contorno' ), (int) round( $km * 1000, -1 ) );
	}

	$decimals = $km < 10 ? 1 : 0;

	return sprintf( /* translators: %s: kilometers */ __( '%s km', 'contorno' ), number_format_i18n( $km, $decimals ) );
}

/**
 * Raio pedido na URL, limitado as opcoes conhecidas.
 */
function contorno_geo_requested_radius(): int {
	$requested = isset( $_GET[ CONTORNO_GEO_RADIUS_PARAM ] ) ? absint( wp_unslash( (string) $_GET[ CONTORNO_GEO_RADIUS_PARAM ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	return in_array( $requested, CONTORNO_GEO_RADIUS_OPTIONS, true ) ? $requested : CONTORNO_GEO_DEFAULT_RADIUS;
}

/**
 * Ordena unidades pela distancia ate um ponto. Unidades sem coordenadas
 * ficam de fora — nao ha como posiciona-las.
 *
 * @param WP_Post[] $units
 * @return array<int, array{post: WP_Post, distance: float}>
 */
function contorno_units_by_distance( array $units, float $lat, float $lng ): array {
	$ranked = array();

	foreach ( $units as $unit ) {
		$coords = contorno_unit_coords( $unit->ID );

		if ( null === $coords ) {
			continue;
		}

		$ranked[] = array(
			'post'     => $unit,
			'distance' => contorno_distance_km( $lat, $lng, $coords[0], $coords[1] ),
		);
	}

	usort( $ranked, static fn ( array $a, array $b ): int => $a['distance'] <=> $b['distance'] );

	return $ranked;
}

/* ============================================================
 * Geocodificacao (CEP e endereco)
 * ========================================================== */

/**
 * GET JSON com timeout curto. Retorna null em qualquer falha.
 *
 * @return array<string,mixed>|null
 */
function contorno_geo_fetch_json( string $url ): ?array {
	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 6,
			'user-agent' => CONTORNO_GEO_USER_AGENT,
			'headers'    => array( 'Accept' => 'application/json', 'Accept-Language' => 'pt-BR' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : null;
}

/**
 * Nominatim (OpenStreetMap): texto livre -> lat/lng.
 *
 * @return array{lat: float, lng: float, label: string}|null
 */
function contorno_geo_nominatim( string $query ): ?array {
	$query = trim( $query );

	if ( '' === $query ) {
		return null;
	}

	$data = contorno_geo_fetch_json(
		'https://nominatim.openstreetmap.org/search?' . http_build_query(
			array(
				'format'       => 'jsonv2',
				'limit'        => 1,
				'countrycodes' => 'br',
				'q'            => $query,
			)
		)
	);

	if ( empty( $data[0]['lat'] ) || empty( $data[0]['lon'] ) ) {
		return null;
	}

	return array(
		'lat'   => (float) $data[0]['lat'],
		'lng'   => (float) $data[0]['lon'],
		'label' => (string) ( $data[0]['display_name'] ?? $query ),
	);
}

/**
 * Endereco livre -> lat/lng (tenta o endereco completo, depois so cidade/UF).
 *
 * @return array{lat: float, lng: float, label: string}|null
 */
function contorno_geocode_address( string $address, string $city = '', string $state = '' ): ?array {
	$address = trim( (string) preg_replace( '/\s+/', ' ', $address ) );
	$suffix  = trim( implode( ', ', array_filter( array( $city, $state, 'Brasil' ) ) ) );

	$attempts = array_filter(
		array(
			'' !== $address ? $address . ', ' . $suffix : '',
			'' !== $city ? $suffix : '',
		)
	);

	foreach ( array_unique( $attempts ) as $query ) {
		$hit = contorno_geo_nominatim( $query );

		if ( null !== $hit ) {
			return $hit;
		}
	}

	return null;
}

/**
 * CEP -> lat/lng + rotulo legivel ("Lourdes, Belo Horizonte - MG").
 *
 * Ordem: ViaCEP (endereco; e rigoroso com CEP inexistente) -> Nominatim
 * (coordenadas da rua, depois do bairro, depois da cidade). A BrasilAPI so
 * entra se o ViaCEP estiver fora do ar: ela aceita CEP invalido e devolve
 * coordenada do centro da cidade, entao serve apenas como ultimo recurso.
 * Resultado fica 30 dias em transient; CEP nao encontrado fica 1 hora.
 *
 * @return array{lat: float, lng: float, label: string}|null
 */
function contorno_geocode_cep( string $cep ): ?array {
	$digits = contorno_cep_digits( $cep );

	if ( '' === $digits ) {
		return null;
	}

	$cache_key = 'contorno_geo_cep_' . $digits;
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return isset( $cached['lat'] ) ? $cached : null;
	}

	$result = contorno_geocode_cep_uncached( $digits );

	set_transient( $cache_key, null === $result ? array( 'miss' => true ) : $result, null === $result ? HOUR_IN_SECONDS : 30 * DAY_IN_SECONDS );

	return $result;
}

/**
 * @return array{lat: float, lng: float, label: string}|null
 */
function contorno_geocode_cep_uncached( string $digits ): ?array {
	$street       = '';
	$neighborhood = '';
	$city         = '';
	$state        = '';
	$last_resort  = null;

	// 1) ViaCEP — endereco do CEP. "erro" significa CEP inexistente.
	$via = contorno_geo_fetch_json( 'https://viacep.com.br/ws/' . $digits . '/json/' );

	if ( is_array( $via ) ) {
		if ( ! empty( $via['erro'] ) || empty( $via['localidade'] ) ) {
			return null;
		}

		$street       = (string) ( $via['logradouro'] ?? '' );
		$neighborhood = (string) ( $via['bairro'] ?? '' );
		$city         = (string) ( $via['localidade'] ?? '' );
		$state        = (string) ( $via['uf'] ?? '' );
	} else {
		// 2) ViaCEP fora do ar: BrasilAPI v2 como reserva.
		$brasil = contorno_geo_fetch_json( 'https://brasilapi.com.br/api/cep/v2/' . $digits );

		if ( ! is_array( $brasil ) || empty( $brasil['city'] ) ) {
			return null;
		}

		$street       = (string) ( $brasil['street'] ?? '' );
		$neighborhood = (string) ( $brasil['neighborhood'] ?? '' );
		$city         = (string) ( $brasil['city'] ?? '' );
		$state        = (string) ( $brasil['state'] ?? '' );

		$coords = $brasil['location']['coordinates'] ?? array();

		if ( ! empty( $coords['latitude'] ) && ! empty( $coords['longitude'] ) ) {
			$last_resort = array(
				'lat'   => (float) $coords['latitude'],
				'lng'   => (float) $coords['longitude'],
				'label' => contorno_geo_cep_label( $neighborhood, $city, $state ),
			);
		}
	}

	// 3) Nominatim — rua > bairro > cidade.
	$attempts = array_filter(
		array(
			'' !== $street ? implode( ', ', array_filter( array( $street, $neighborhood, $city, $state, 'Brasil' ) ) ) : '',
			'' !== $neighborhood ? implode( ', ', array_filter( array( $neighborhood, $city, $state, 'Brasil' ) ) ) : '',
			implode( ', ', array_filter( array( $city, $state, 'Brasil' ) ) ),
		)
	);

	foreach ( array_unique( $attempts ) as $query ) {
		$hit = contorno_geo_nominatim( $query );

		if ( null !== $hit ) {
			$hit['label'] = contorno_geo_cep_label( $neighborhood, $city, $state );

			return $hit;
		}
	}

	return $last_resort;
}

function contorno_geo_cep_label( string $neighborhood, string $city, string $state ): string {
	$place = trim( $city . ( '' !== $state ? ' - ' . $state : '' ) );

	return '' !== $neighborhood ? $neighborhood . ', ' . $place : $place;
}
