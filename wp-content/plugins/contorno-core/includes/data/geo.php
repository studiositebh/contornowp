<?php
/**
 * Geolocalizacao: busca de unidades por CEP e raio.
 *
 * O visitante digita um CEP; ele vira lat/lng (ViaCEP -> Google Geocoding
 * API v4 quando ha chave -> Nominatim como reserva, com cache em transient) e
 * as unidades sao ordenadas pela distancia em linha reta (Haversine) usando
 * os campos latitude/longitude persistidos em cada `unidade`. Sem chave do
 * Google tudo continua funcionando com a reserva. Chave: data/google-maps.php.
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
 * Distancia a partir de um ponto aproximado (centro da regiao do CEP):
 * arredonda para km inteiro — nao ha precisao para mais que isso.
 */
function contorno_format_distance_approx( float $km ): string {
	if ( $km < 1 ) {
		return __( 'a menos de 1 km', 'contorno' );
	}

	return sprintf( /* translators: %s: kilometers */ __( 'aprox. %s km', 'contorno' ), number_format_i18n( round( $km ) ) );
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
 *
 * Um unico pipeline, usado pela busca por CEP e pelo preenchimento de
 * coordenadas das unidades:
 *
 *   CEP -> ViaCEP (endereco oficial; BrasilAPI so se o ViaCEP cair)
 *       -> Google Geocoding API v4 (se houver chave) -> Nominatim (reserva)
 *
 * Todo ponto sai como "local" estruturado — nunca como string pronta — para
 * a interface poder dizer "bairro X, em Cidade/UF" sem ambiguidade:
 *
 *   array{lat: float, lng: float, neighborhood: string, city: string,
 *         state: string, precision: 'street'|'area'|'city', source: string}
 * ========================================================== */

/** Transients: resultado de CEP e de endereco (Google/Nominatim). */
const CONTORNO_GEO_CACHE_TTL      = 30 * DAY_IN_SECONDS;
const CONTORNO_GEO_MISS_CACHE_TTL = HOUR_IN_SECONDS;

/** Versao do formato em cache (troca invalida os transients antigos). */
const CONTORNO_GEO_CACHE_VERSION = 'v2';

/**
 * GET JSON com timeout curto. Retorna null em qualquer falha.
 *
 * @param array<string,string> $headers
 * @return array<string,mixed>|null
 */
function contorno_geo_fetch_json( string $url, array $headers = array() ): ?array {
	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 6,
			'user-agent' => CONTORNO_GEO_USER_AGENT,
			'headers'    => array_merge( array( 'Accept' => 'application/json', 'Accept-Language' => 'pt-BR' ), $headers ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : null;
}

/**
 * Chave de cache normalizada (sem acento, caixa, espacos) + versao + se a
 * resposta veio com Google disponivel. Cadastrar a chave passa a usar o
 * Google sem esperar o cache antigo (so Nominatim) expirar.
 */
function contorno_geo_cache_key( string $kind, string $query ): string {
	$normalized = contorno_normalize_search( $query );

	return 'contorno_geo_' . CONTORNO_GEO_CACHE_VERSION . '_' . $kind . '_' . ( '' !== contorno_google_maps_api_key() ? 'g' : 'n' ) . '_' . md5( $normalized );
}

/* ---------- Google Geocoding API v4 (server-side) ---------- */

/**
 * Consulta o Google com endereco estruturado, restrito ao Brasil.
 *
 * Retorna o ponto, null quando o Google nao achou nada, ou false quando o
 * Google esta indisponivel (sem chave, chave recusada, cota, rede). Quem
 * chama trata false como "use a reserva" — a busca nunca cai por isso.
 *
 * @param array{street?: string, neighborhood?: string, city?: string, state?: string, postal_code?: string} $parts
 * @param bool $use_cache false so no teste da tela de configuracao.
 * @return array{lat: float, lng: float, precision: string}|null|false
 */
function contorno_google_geocode( array $parts, bool $use_cache = true ) {
	$key = contorno_google_maps_api_key();

	if ( '' === $key ) {
		return false;
	}

	$lines = implode( ', ', array_filter( array( $parts['street'] ?? '', $parts['neighborhood'] ?? '' ) ) );
	$query = array_filter(
		array(
			'address.addressLines'       => $lines,
			'address.locality'           => (string) ( $parts['city'] ?? '' ),
			'address.administrativeArea' => (string) ( $parts['state'] ?? '' ),
			'address.postalCode'         => (string) ( $parts['postal_code'] ?? '' ),
			'address.regionCode'         => 'BR',
			'regionCode'                 => 'BR',
			'languageCode'               => 'pt-BR',
		),
		static fn ( string $value ): bool => '' !== $value
	);

	$cache_key = contorno_geo_cache_key( 'google', (string) wp_json_encode( $query ) );
	$cached    = $use_cache ? get_transient( $cache_key ) : false;

	if ( is_array( $cached ) ) {
		return isset( $cached['lat'] ) ? $cached : null;
	}

	// Chave no cabecalho (nunca na URL, que pode ir para logs de acesso).
	$response = wp_remote_get(
		'https://geocode.googleapis.com/v4/geocode/address?' . http_build_query( $query ),
		array(
			'timeout' => 6,
			'headers' => array(
				'X-Goog-Api-Key'   => $key,
				'X-Goog-FieldMask' => 'results.location,results.granularity,results.types',
				'Accept'           => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		contorno_google_geocode_status( 'network' );

		return false;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || ! is_array( $data ) ) {
		// 400 chave invalida / 403 API nao habilitada ou restricao / 429 cota.
		contorno_google_geocode_status( 'http_' . $code );

		return false;
	}

	contorno_google_geocode_status( 'ok' );

	$first = $data['results'][0] ?? null;

	if ( ! is_array( $first ) || ! isset( $first['location']['latitude'], $first['location']['longitude'] ) ) {
		set_transient( $cache_key, array( 'miss' => true ), DAY_IN_SECONDS );

		return null;
	}

	$result = array(
		'lat'       => (float) $first['location']['latitude'],
		'lng'       => (float) $first['location']['longitude'],
		'precision' => contorno_google_precision( (string) ( $first['granularity'] ?? '' ), (array) ( $first['types'] ?? array() ) ),
	);

	set_transient( $cache_key, $result, CONTORNO_GEO_CACHE_TTL );

	return $result;
}

/**
 * granularity + types do Google -> street | area | city.
 *
 * @param array<int,string> $types
 */
function contorno_google_precision( string $granularity, array $types ): string {
	if ( in_array( $granularity, array( 'ROOFTOP', 'RANGE_INTERPOLATED' ), true ) ) {
		return 'street';
	}

	if ( array_intersect( $types, array( 'street_address', 'route', 'premise', 'subpremise', 'intersection' ) ) ) {
		return 'street';
	}

	if ( array_intersect( $types, array( 'locality', 'administrative_area_level_2', 'administrative_area_level_1', 'country' ) ) ) {
		return 'city';
	}

	return 'area'; // bairro, CEP, sublocalidade.
}

/**
 * Ultimo status do Google nesta requisicao (diagnostico da tela de
 * configuracao). Nunca guarda a chave nem a URL.
 */
function contorno_google_geocode_status( ?string $status = null ): string {
	static $last = '';

	if ( null !== $status ) {
		$last = $status;
	}

	return $last;
}

/* ---------- Nominatim (reserva, sem chave) ---------- */

/**
 * @return array{lat: float, lng: float}|null
 */
function contorno_geo_nominatim( string $query ): ?array {
	$query = trim( $query );

	if ( '' === $query ) {
		return null;
	}

	$cache_key = contorno_geo_cache_key( 'osm', $query );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return isset( $cached['lat'] ) ? $cached : null;
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

	if ( null === $data ) {
		return null; // fora do ar: nao guarda "nao encontrado".
	}

	$result = ! empty( $data[0]['lat'] ) && ! empty( $data[0]['lon'] )
		? array( 'lat' => (float) $data[0]['lat'], 'lng' => (float) $data[0]['lon'] )
		: null;

	set_transient( $cache_key, $result ?? array( 'miss' => true ), null === $result ? CONTORNO_GEO_MISS_CACHE_TTL : CONTORNO_GEO_CACHE_TTL );

	return $result;
}

/* ---------- Endereco -> ponto ---------- */

/**
 * Endereco estruturado -> local. Google primeiro (endereco completo); sem
 * Google, Nominatim da rua ao bairro e a cidade — e a precisao registra ate
 * onde foi possivel chegar.
 *
 * @param array{street?: string, neighborhood?: string, city?: string, state?: string, postal_code?: string} $parts
 * @return array{lat: float, lng: float, neighborhood: string, city: string, state: string, precision: string, source: string}|null
 */
function contorno_geo_locate_address( array $parts ): ?array {
	$street       = trim( (string) ( $parts['street'] ?? '' ) );
	$neighborhood = trim( (string) ( $parts['neighborhood'] ?? '' ) );
	$city         = trim( (string) ( $parts['city'] ?? '' ) );
	$state        = trim( (string) ( $parts['state'] ?? '' ) );

	if ( '' === $street && '' === $neighborhood && '' === $city && '' === (string) ( $parts['postal_code'] ?? '' ) ) {
		return null;
	}

	$place = array(
		'neighborhood' => $neighborhood,
		'city'         => $city,
		'state'        => $state,
	);

	$google = contorno_google_geocode( $parts );

	if ( is_array( $google ) ) {
		return array( 'lat' => $google['lat'], 'lng' => $google['lng'] ) + $place + array( 'precision' => $google['precision'], 'source' => 'google' );
	}

	$attempts = array(
		'street' => '' !== $street ? implode( ', ', array_filter( array( $street, $neighborhood, $city, $state, 'Brasil' ) ) ) : '',
		'area'   => '' !== $neighborhood ? implode( ', ', array_filter( array( $neighborhood, $city, $state, 'Brasil' ) ) ) : '',
		'city'   => '' !== $city ? implode( ', ', array_filter( array( $city, $state, 'Brasil' ) ) ) : '',
	);

	foreach ( $attempts as $precision => $query ) {
		if ( '' === $query ) {
			continue;
		}

		$hit = contorno_geo_nominatim( $query );

		if ( null !== $hit ) {
			return $hit + $place + array( 'precision' => $precision, 'source' => 'nominatim' );
		}
	}

	return null;
}

/**
 * Endereco livre da unidade -> lat/lng (usado uma unica vez por unidade,
 * pelo `wp contorno geocode` e pela auto-migracao; o resultado e gravado).
 *
 * @return array{lat: float, lng: float, neighborhood: string, city: string, state: string, precision: string, source: string}|null
 */
function contorno_geocode_address( string $address, string $city = '', string $state = '' ): ?array {
	$address = trim( (string) preg_replace( '/\s+/', ' ', $address ) );

	return contorno_geo_locate_address(
		array(
			'street' => $address,
			'city'   => $city,
			'state'  => $state,
		)
	);
}

/* ---------- CEP ---------- */

/**
 * Endereco oficial de um CEP.
 *
 * @return array{street: string, neighborhood: string, city: string, state: string, postal_code: string}|string
 *         O endereco, 'not_found' (CEP inexistente) ou 'unavailable'.
 */
function contorno_geo_cep_address( string $digits ) {
	$via = contorno_geo_fetch_json( 'https://viacep.com.br/ws/' . $digits . '/json/' );

	if ( is_array( $via ) ) {
		if ( ! empty( $via['erro'] ) || empty( $via['localidade'] ) ) {
			return 'not_found';
		}

		return array(
			'street'       => (string) ( $via['logradouro'] ?? '' ),
			'neighborhood' => (string) ( $via['bairro'] ?? '' ),
			'city'         => (string) $via['localidade'],
			'state'        => (string) ( $via['uf'] ?? '' ),
			'postal_code'  => contorno_format_cep( $digits ),
		);
	}

	// ViaCEP fora do ar: BrasilAPI (so o endereco; ela aceita CEP invalido
	// e devolve o centro da cidade, entao as coordenadas dela nao servem).
	$brasil = contorno_geo_fetch_json( 'https://brasilapi.com.br/api/cep/v2/' . $digits );

	if ( is_array( $brasil ) && ! empty( $brasil['city'] ) ) {
		return array(
			'street'       => (string) ( $brasil['street'] ?? '' ),
			'neighborhood' => (string) ( $brasil['neighborhood'] ?? '' ),
			'city'         => (string) $brasil['city'],
			'state'        => (string) ( $brasil['state'] ?? '' ),
			'postal_code'  => contorno_format_cep( $digits ),
		);
	}

	return 'unavailable';
}

/**
 * Localiza um CEP para a busca de unidades.
 *
 *   exact       — CEP existe; ponto do endereco dele.
 *   region      — CEP nao existe; ponto APROXIMADO da regiao (CEP geral do
 *                 setor, XXXXX-000, ou o codigo postal no Google/OSM).
 *   not_found   — CEP nao existe e a regiao tambem nao foi localizada.
 *   unavailable — servicos de CEP/geocodificacao fora do ar.
 *
 * Cache: 30 dias para exact/region, 1 hora para not_found; unavailable nao
 * e guardado (a proxima busca tenta de novo).
 *
 * @return array{status: string, cep: string, origin: array<string,mixed>|null}
 */
function contorno_geo_locate_cep( string $cep ): array {
	$digits = contorno_cep_digits( $cep );

	if ( '' === $digits ) {
		return array( 'status' => 'not_found', 'cep' => '', 'origin' => null );
	}

	$cache_key = contorno_geo_cache_key( 'cep', $digits );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) && isset( $cached['status'] ) ) {
		return $cached;
	}

	$result = contorno_geo_locate_cep_uncached( $digits );

	// Com chave cadastrada, um ponto que veio da reserva (Google fora do ar
	// naquele momento) vale so 1 hora: a proxima busca tenta o Google de novo.
	$fallback = '' !== contorno_google_maps_api_key() && 'google' !== ( $result['origin']['source'] ?? 'google' );

	if ( 'unavailable' !== $result['status'] ) {
		set_transient( $cache_key, $result, 'not_found' === $result['status'] || $fallback ? CONTORNO_GEO_MISS_CACHE_TTL : CONTORNO_GEO_CACHE_TTL );
	}

	return $result;
}

/**
 * @return array{status: string, cep: string, origin: array<string,mixed>|null}
 */
function contorno_geo_locate_cep_uncached( string $digits ): array {
	$out   = static fn ( string $status, ?array $origin = null ): array => array( 'status' => $status, 'cep' => $digits, 'origin' => $origin );
	$state = contorno_cep_state( $digits );

	// Faixa que os Correios nao usam: inexistente, sem gastar nenhuma consulta.
	if ( '' === $state ) {
		return $out( 'not_found' );
	}

	$address = contorno_geo_cep_address( $digits );

	if ( is_array( $address ) ) {
		$origin = contorno_geo_locate_address( $address );

		return null !== $origin ? $out( 'exact', $origin ) : $out( 'unavailable' );
	}

	if ( 'unavailable' === $address ) {
		return $out( 'unavailable' );
	}

	// CEP inexistente: regiao pelo CEP geral do setor (mesmos 5 digitos).
	$sector = substr( $digits, 0, 5 ) . '000';

	if ( $sector !== $digits ) {
		$sector_address = contorno_geo_cep_address( $sector );

		if ( is_array( $sector_address ) ) {
			// So bairro/cidade: a rua do CEP geral nao e a do visitante.
			$origin = contorno_geo_locate_address(
				array(
					'neighborhood' => $sector_address['neighborhood'],
					'city'         => $sector_address['city'],
					'state'        => $sector_address['state'],
					'postal_code'  => $sector_address['postal_code'],
				)
			);

			if ( null !== $origin ) {
				return $out( 'region', array( 'precision' => 'area' === $origin['precision'] || 'street' === $origin['precision'] ? 'area' : 'city' ) + $origin );
			}
		}
	}

	// Sem CEP de setor: o codigo postal no Google e, por fim, no OSM.
	$google = contorno_google_geocode( array( 'postal_code' => contorno_format_cep( $digits ) ) );

	if ( is_array( $google ) ) {
		return $out( 'region', array( 'lat' => $google['lat'], 'lng' => $google['lng'], 'neighborhood' => '', 'city' => '', 'state' => '', 'precision' => 'area', 'source' => 'google' ) );
	}

	foreach ( array_unique( array( $digits, $sector ) ) as $candidate ) {
		$data = contorno_geo_fetch_json(
			'https://nominatim.openstreetmap.org/search?' . http_build_query(
				array(
					'format'         => 'jsonv2',
					'limit'          => 1,
					'countrycodes'   => 'br',
					'postalcode'     => contorno_format_cep( $candidate ),
					'addressdetails' => 1,
				)
			)
		);

		if ( ! empty( $data[0]['lat'] ) && ! empty( $data[0]['lon'] ) ) {
			$place = (array) ( $data[0]['address'] ?? array() );

			// Codigo postal do OSM em outro estado = dado lixo; ignora.
			if ( contorno_geo_state_code( (string) ( $place['ISO3166-2-lvl4'] ?? '' ) ) !== $state ) {
				continue;
			}

			return $out(
				'region',
				array(
					'lat'          => (float) $data[0]['lat'],
					'lng'          => (float) $data[0]['lon'],
					'neighborhood' => (string) ( $place['suburb'] ?? '' ),
					'city'         => (string) ( $place['city'] ?? $place['town'] ?? $place['village'] ?? $place['municipality'] ?? '' ),
					'state'        => contorno_geo_state_code( (string) ( $place['ISO3166-2-lvl4'] ?? '' ) ),
					'precision'    => 'area',
					'source'       => 'nominatim',
				)
			);
		}
	}

	return $out( 'not_found' );
}

/**
 * UF da faixa de CEP dos Correios ('' = faixa inexistente, ex.: 00000-xxx).
 * Barra CEP impossivel antes de qualquer consulta e confere se um resultado
 * regional do OSM caiu no estado certo (o OSM tem codigos postais lixo, como
 * "00000-000" e "99999-999", marcados em lugares aleatorios).
 */
function contorno_cep_state( string $digits ): string {
	$prefix = (int) substr( $digits, 0, 5 );
	$ranges = array(
		array( 1000, 19999, 'SP' ), array( 20000, 28999, 'RJ' ), array( 29000, 29999, 'ES' ),
		array( 30000, 39999, 'MG' ), array( 40000, 48999, 'BA' ), array( 49000, 49999, 'SE' ),
		array( 50000, 56999, 'PE' ), array( 57000, 57999, 'AL' ), array( 58000, 58999, 'PB' ),
		array( 59000, 59999, 'RN' ), array( 60000, 63999, 'CE' ), array( 64000, 64999, 'PI' ),
		array( 65000, 65999, 'MA' ), array( 66000, 68899, 'PA' ), array( 68900, 68999, 'AP' ),
		array( 69000, 69299, 'AM' ), array( 69300, 69399, 'RR' ), array( 69400, 69899, 'AM' ),
		array( 69900, 69999, 'AC' ), array( 70000, 72799, 'DF' ), array( 72800, 72999, 'GO' ),
		array( 73000, 73699, 'DF' ), array( 73700, 76799, 'GO' ), array( 76800, 76999, 'RO' ),
		array( 77000, 77999, 'TO' ), array( 78000, 78899, 'MT' ), array( 79000, 79999, 'MS' ),
		array( 80000, 87999, 'PR' ), array( 88000, 89999, 'SC' ), array( 90000, 99999, 'RS' ),
	);

	foreach ( $ranges as $range ) {
		if ( $prefix >= $range[0] && $prefix <= $range[1] ) {
			return $range[2];
		}
	}

	return '';
}

/**
 * "BR-MG" -> "MG".
 */
function contorno_geo_state_code( string $iso ): string {
	return 1 === preg_match( '/^BR-([A-Z]{2})$/', $iso, $match ) ? $match[1] : '';
}

/**
 * Nome do local sem ambiguidade para a interface:
 *   "bairro São Paulo, em Belo Horizonte/MG" | "Belo Horizonte/MG"
 * O bairro sempre leva a palavra "bairro" — "São Paulo" sozinho seria lido
 * como a cidade.
 *
 * @param array<string,mixed> $origin
 */
function contorno_geo_place_label( array $origin ): string {
	$city  = trim( (string) ( $origin['city'] ?? '' ) );
	$state = trim( (string) ( $origin['state'] ?? '' ) );
	$place = '' !== $city ? $city . ( '' !== $state ? '/' . $state : '' ) : '';
	$hood  = trim( (string) ( $origin['neighborhood'] ?? '' ) );

	if ( '' !== $hood && '' !== $place && 'city' !== ( $origin['precision'] ?? '' ) ) {
		/* translators: 1: neighborhood, 2: city/UF */
		return sprintf( __( 'bairro %1$s, em %2$s', 'contorno' ), $hood, $place );
	}

	return $place;
}

/**
 * Local exato o bastante para medir distancia real e filtrar pelo raio?
 *
 * @param array{status: string, origin: array<string,mixed>|null} $location
 */
function contorno_geo_is_precise( array $location ): bool {
	return 'exact' === $location['status'] && is_array( $location['origin'] ) && 'city' !== ( $location['origin']['precision'] ?? '' );
}

/**
 * Ultimo recurso da busca por CEP, sem rede: unidades cujo CEP compartilha
 * o maior prefixo com o CEP pesquisado (mesma sub-regiao postal). Sem
 * distancia — so a ordem por proximidade de faixa de CEP.
 *
 * @param WP_Post[] $units
 * @return WP_Post[]
 */
function contorno_units_by_cep_prefix( array $units, string $cep, int $min_prefix = 3, int $limit = 6 ): array {
	$digits = contorno_cep_digits( $cep );

	if ( '' === $digits ) {
		return array();
	}

	$ranked = array();

	foreach ( $units as $unit ) {
		$postal = contorno_unit_postal_digits( $unit->ID );

		if ( 8 !== strlen( $postal ) ) {
			continue;
		}

		$prefix = 0;
		while ( $prefix < 8 && $postal[ $prefix ] === $digits[ $prefix ] ) {
			++$prefix;
		}

		if ( $prefix < $min_prefix ) {
			continue;
		}

		$ranked[] = array(
			'post'   => $unit,
			'prefix' => $prefix,
			'gap'    => abs( (int) $postal - (int) $digits ),
		);
	}

	usort(
		$ranked,
		static fn ( array $a, array $b ): int => array( $b['prefix'], $a['gap'] ) <=> array( $a['prefix'], $b['gap'] )
	);

	return array_map( static fn ( array $row ): WP_Post => $row['post'], array_slice( $ranked, 0, $limit ) );
}

/**
 * Preenche lat/lng das unidades que ainda nao tem coordenadas (ate $limit
 * por chamada, respeitando 1 req/s do Nominatim). Usado pela auto-migracao.
 *
 * @return int Quantas unidades foram geocodificadas.
 */
function contorno_geocode_missing_units( int $limit = 20 ): int {
	$done = 0;

	foreach ( contorno_get_units( array( 'post_status' => 'any' ) ) as $unit ) {
		if ( $done >= $limit ) {
			break;
		}

		if ( null !== contorno_unit_coords( $unit->ID ) ) {
			continue;
		}

		$address = contorno_field_text( 'address', $unit->ID );
		$city    = contorno_field_text( 'city', $unit->ID );

		if ( '' === $address && '' === $city ) {
			continue;
		}

		$hit = contorno_geocode_address( $address, $city, contorno_field_text( 'state', $unit->ID ) );

		// Nominatim pede no maximo 1 requisicao por segundo.
		if ( null === $hit || 'nominatim' === $hit['source'] ) {
			sleep( 1 );
		}

		if ( null === $hit ) {
			continue;
		}

		contorno_update_field( $unit->ID, 'latitude', (string) $hit['lat'] );
		contorno_update_field( $unit->ID, 'longitude', (string) $hit['lng'] );
		++$done;
	}

	return $done;
}
