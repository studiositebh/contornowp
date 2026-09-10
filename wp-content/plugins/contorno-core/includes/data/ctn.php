<?php
/**
 * Consultas e constantes das landings CTN.
 *
 * Porte de src/lib/contorno/ctn/{index,shared}.ts.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Tagline oficial presente nas landings CTN legadas. */
const CONTORNO_CTN_TAGLINE = 'YOUR ONLY LIMIT IS YOU';

/**
 * PUV do hub /ctn — fotografia oficial com o equipamento como protagonista
 * (Leg Press Realleader). Nunca substituir por foto generica de academia.
 *
 * @return array{image:string,alt:string}
 */
function contorno_ctn_hub_value_proposition(): array {
	$default = array(
		'image' => '/ctn/institucional/leg-press-realleader.jpg',
		'alt'   => 'Leg Press Realleader dourado — equipamento de alto nível dos Centros de Treinamento Contorno',
	);

	return (array) apply_filters( 'contorno_ctn_hub_value_proposition', $default );
}

/**
 * Fallback institucional de PUV para CTNs sem fotografia propria de maquina
 * em primeiro plano — Maquina de Gluteo 4 apoios da Panatta.
 *
 * @return array{image:string,alt:string}
 */
function contorno_ctn_institutional_equipment(): array {
	$default = array(
		'image' => '/ctn/institucional/panatta-gluteo-4-apoios.jpg',
		'alt'   => 'Máquina de Glúteo 4 apoios da Panatta — equipamento premium dos Centros de Treinamento Contorno',
	);

	return (array) apply_filters( 'contorno_ctn_institutional_equipment', $default );
}

/**
 * Playlist oficial do canal. PENDENCIA herdada do React: o canal nao possui
 * playlist curada so de CTN. Trocar aqui (ou no campo da CTN) quando existir.
 */
function contorno_ctn_default_playlist_url(): string {
	return (string) apply_filters(
		'contorno_ctn_default_playlist_url',
		'https://www.youtube.com/playlist?list=UUqqy9GqPTn9KvK_1lZZaSXw'
	);
}

/**
 * Marcas de equipamento padrao — usadas quando a CTN nao cadastra as suas.
 *
 * @return array<int,array{id:string,name:string,logo:string}>
 */
function contorno_ctn_default_brands(): array {
	return array(
		array( 'id' => 'panatta', 'name' => 'Panatta', 'logo' => '/ctn/brands/panatta.png' ),
		array( 'id' => 'hammer', 'name' => 'Hammer', 'logo' => '/ctn/brands/hammer.png' ),
		array( 'id' => 'realleader', 'name' => 'Realleader', 'logo' => '/ctn/brands/realleader.png' ),
		array( 'id' => 'supreme', 'name' => 'Supreme', 'logo' => '/ctn/brands/supreme.png' ),
		array( 'id' => 'shua', 'name' => 'Shua', 'logo' => '/ctn/brands/shua.png' ),
	);
}

/**
 * Horarios compartilhados das CTNs.
 *
 * @return array<int,array{label:string,hours:string}>
 */
function contorno_ctn_default_hours(): array {
	return array(
		array( 'label' => '2ª a 5ª feira', 'hours' => '04:00 às 00:00' ),
		array( 'label' => '6ª feira', 'hours' => '04:00 às 23:00' ),
		array( 'label' => 'Sábado, Domingo e feriados', 'hours' => '07:00 às 19:00' ),
	);
}

/**
 * @return WP_Post[]
 */
function contorno_get_ctns(): array {
	return get_posts(
		array(
			'post_type'      => CONTORNO_CPT_CTN,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);
}

function contorno_get_ctn_by_slug( string $slug ): ?WP_Post {
	$posts = get_posts(
		array(
			'post_type'      => CONTORNO_CPT_CTN,
			'name'           => sanitize_title( $slug ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		)
	);

	return $posts[0] ?? null;
}

/**
 * CTN vinculada a uma unidade do catalogo /unidades.
 */
function contorno_get_ctn_for_unit( int $unit_id ): ?WP_Post {
	$explicit = contorno_field_text( 'ctn_slug', $unit_id );
	if ( '' !== $explicit ) {
		return contorno_get_ctn_by_slug( $explicit );
	}

	$unit_slug = (string) get_post_field( 'post_name', $unit_id );

	foreach ( contorno_get_ctns() as $ctn ) {
		if ( contorno_field_text( 'unit_slug', $ctn->ID ) === $unit_slug ) {
			return $ctn;
		}
	}

	return null;
}

/**
 * PUV da CTN com a cadeia de fallback aprovada:
 * imagem propria > equipamento institucional Panatta.
 *
 * @return array{image:string,alt:string}
 */
function contorno_ctn_value_proposition( ?int $post_id = null ): array {
	$image = contorno_field_image_url( 'vp_image', $post_id, 'contorno-hero' );
	$alt   = contorno_field_text( 'vp_image_alt', $post_id );

	if ( '' === $image ) {
		$fallback = contorno_ctn_institutional_equipment();
		$image    = contorno_resolve_media( $fallback['image'], 'contorno-hero' );
		$alt      = '' !== $alt ? $alt : $fallback['alt'];
	}

	return array( 'image' => $image, 'alt' => $alt );
}

/**
 * Marcas da CTN com fallback para as marcas padrao.
 *
 * @return array<int,array<string,mixed>>
 */
function contorno_ctn_brands( ?int $post_id = null ): array {
	$brands = contorno_field_list( 'brands', $post_id );

	return array() !== $brands ? $brands : contorno_ctn_default_brands();
}

/**
 * Horarios da CTN com fallback para os horarios compartilhados.
 *
 * @return array<int,array<string,mixed>>
 */
function contorno_ctn_hours( ?int $post_id = null ): array {
	$hours = contorno_field_list( 'opening_hours', $post_id );

	return array() !== $hours ? $hours : contorno_ctn_default_hours();
}

/**
 * URL de "Ver mais" dos videos: campo da CTN > playlist oficial.
 */
function contorno_ctn_playlist_url( ?int $post_id = null ): string {
	$explicit = contorno_field_text( 'video_playlist_url', $post_id );

	return '' !== $explicit ? $explicit : contorno_ctn_default_playlist_url();
}
