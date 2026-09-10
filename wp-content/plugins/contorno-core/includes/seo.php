<?php
/**
 * SEO, OpenGraph e JSON-LD.
 *
 * Porte de src/lib/seo.ts + src/lib/socialShareImage.ts.
 *
 * Se um plugin de SEO (Yoast / Rank Math / SEOPress / AIOSEO) estiver ativo,
 * o tema NAO emite meta tags — deixa o plugin no comando e evita duplicidade.
 * O JSON-LD de LocalBusiness/Organization continua sendo do tema, porque
 * depende dos campos estruturados das unidades.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO proprio de qualquer post (inclusive paginas).
 *
 * As Unidades e CTNs tem os campos no esquema; as PAGINAS recebem os mesmos
 * metas pelo importador. Por isso a leitura aqui e do meta cru, e nao via
 * contorno_field_text(), que so conhece os post types do esquema.
 */
function contorno_seo_meta( string $key, int $post_id = 0 ): string {
	$post_id = $post_id ?: (int) get_queried_object_id();

	if ( ! $post_id ) {
		return '';
	}

	$value = get_post_meta( $post_id, '_contorno_' . $key, true );

	return is_string( $value ) ? trim( $value ) : '';
}

function contorno_seo_plugin_active(): bool {
	return defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'AIOSEO_VERSION' );
}

/**
 * URL absoluta no dominio canonico oficial.
 */
function contorno_absolute_url( string $path ): string {
	if ( preg_match( '#^https?://#', $path ) ) {
		return $path;
	}

	return rtrim( CONTORNO_CANONICAL_URL, '/' ) . '/' . ltrim( $path, '/' );
}

/**
 * Imagem social da tela atual, com fallback para a imagem oficial da marca.
 *
 * Porte de resolveSocialShareImage(): imagem propria da entidade > destaque >
 * /brand/Logocompartilhamento.webp.
 */
function contorno_social_image(): string {
	$post_id = is_singular() ? (int) get_queried_object_id() : 0;

	if ( $post_id ) {
		$custom = contorno_field_image_url( 'seo_image', $post_id, 'full' );
		if ( '' !== $custom ) {
			return $custom;
		}

		$hero = contorno_field_image_url( 'hero_image', $post_id, 'contorno-hero' );
		if ( '' !== $hero ) {
			return $hero;
		}

		$image = contorno_field_image_url( 'image', $post_id, 'contorno-hero' );
		if ( '' !== $image ) {
			return $image;
		}

		$thumb = get_the_post_thumbnail_url( $post_id, 'contorno-hero' );
		if ( is_string( $thumb ) && '' !== $thumb ) {
			return $thumb;
		}
	}

	return contorno_asset_url( contorno_brand_get( 'og_image' ) );
}

/**
 * Descricao da tela atual.
 */
function contorno_meta_description(): string {
	if ( is_singular() ) {
		$post_id = (int) get_queried_object_id();

		$custom = contorno_field_text( 'seo_description', $post_id );
		if ( '' === $custom ) {
			// Paginas recebem o meta pelo importador, fora do esquema de campos.
			$custom = contorno_seo_meta( 'seo_description', $post_id );
		}
		if ( '' !== $custom ) {
			return $custom;
		}

		$excerpt = get_the_excerpt( $post_id );
		if ( is_string( $excerpt ) && '' !== trim( $excerpt ) ) {
			return wp_strip_all_tags( $excerpt );
		}
	}

	if ( is_post_type_archive( CONTORNO_CPT_UNIT ) ) {
		return 'Encontre a unidade Contorno do Corpo mais perto de voce. Estrutura premium, aulas coletivas e planos para todos os objetivos.';
	}

	if ( is_post_type_archive( CONTORNO_CPT_CTN ) ) {
		return 'Centros de Treinamento Contorno (CTN): equipamentos de alto nivel, estrutura premium e ' . CONTORNO_CTN_TAGLINE . '.';
	}

	return contorno_brand_get( 'description' );
}

/**
 * Title da tela atual — respeita campo SEO proprio.
 */
add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		if ( contorno_seo_plugin_active() ) {
			return $title;
		}

		if ( is_singular() ) {
			$custom = contorno_field_text( 'seo_title', (int) get_queried_object_id() );
			if ( '' === $custom ) {
				$custom = contorno_seo_meta( 'seo_title' );
			}
			if ( '' !== $custom ) {
				return $custom;
			}
		}

		return $title;
	}
);

/**
 * Meta tags: canonical, robots, OpenGraph, Twitter.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( contorno_seo_plugin_active() ) {
			return;
		}

		$brand       = contorno_brand();
		$title       = wp_get_document_title();
		$description = contorno_meta_description();
		$image       = contorno_social_image();
		$canonical   = is_singular()
			? (string) get_permalink()
			: ( is_post_type_archive() ? (string) get_post_type_archive_link( (string) get_query_var( 'post_type' ) ) : home_url( add_query_arg( array() ) ) );

		/*
		 * noindex nas rotas de fluxo (/matricula e /matricula/confirmacao),
		 * exatamente como no React — e em 404 e busca interna.
		 */
		if ( is_404() || is_search() || ( is_singular() && '' !== contorno_seo_meta( 'noindex' ) ) ) {
			echo '<meta name="robots" content="noindex, follow" />' . "\n";
		}

		if ( '' !== $canonical ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
		}

		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );

		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( (string) $brand['long_name'] ) );
		printf( '<meta property="og:locale" content="%s" />' . "\n", 'pt_BR' );
		printf( '<meta property="og:type" content="%s" />' . "\n", is_singular( 'post' ) ? 'article' : 'website' );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		if ( '' !== $canonical ) {
			printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $canonical ) );
		}
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr( (string) $brand['name'] ) );

		// Dimensoes declaradas apenas para a imagem institucional conhecida.
		if ( str_contains( $image, 'Logocompartilhamento' ) ) {
			printf( '<meta property="og:image:type" content="%s" />' . "\n", esc_attr( (string) $brand['og_image_type'] ) );
			printf( '<meta property="og:image:width" content="%s" />' . "\n", esc_attr( (string) $brand['og_image_w'] ) );
			printf( '<meta property="og:image:height" content="%s" />' . "\n", esc_attr( (string) $brand['og_image_h'] ) );
		}

		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );
	},
	5
);

/**
 * JSON-LD.
 */
add_action(
	'wp_head',
	static function (): void {
		$brand = contorno_brand();
		$graph = array();

		// Organization / WebSite — sempre.
		$graph[] = array(
			'@type'  => 'Organization',
			'@id'    => CONTORNO_CANONICAL_URL . '/#organization',
			'name'   => $brand['long_name'],
			'url'    => CONTORNO_CANONICAL_URL,
			'logo'   => contorno_asset_url( (string) $brand['logo'] ),
			'image'  => contorno_asset_url( (string) $brand['og_image'] ),
			'sameAs' => array_values( array_filter( array( (string) $brand['instagram'] ) ) ),
			'email'  => $brand['email'],
		);

		$graph[] = array(
			'@type'     => 'WebSite',
			'@id'       => CONTORNO_CANONICAL_URL . '/#website',
			'url'       => CONTORNO_CANONICAL_URL,
			'name'      => $brand['long_name'],
			'publisher' => array( '@id' => CONTORNO_CANONICAL_URL . '/#organization' ),
			'inLanguage' => 'pt-BR',
		);

		// LocalBusiness por unidade / CTN.
		if ( is_singular( array( CONTORNO_CPT_UNIT, CONTORNO_CPT_CTN ) ) ) {
			$post_id = (int) get_queried_object_id();

			$address = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => contorno_field_text( 'address', $post_id ),
					'addressLocality' => contorno_field_text( 'city', $post_id ),
					'addressRegion'   => contorno_field_text( 'state', $post_id ),
					'postalCode'      => contorno_field_text( 'postal_code', $post_id ),
					'addressCountry'  => 'BR',
				)
			);

			$business = array_filter(
				array(
					'@type'       => 'ExerciseGym',
					'@id'         => get_permalink( $post_id ) . '#gym',
					'name'        => get_the_title( $post_id ),
					'url'         => get_permalink( $post_id ),
					'image'       => contorno_social_image(),
					'telephone'   => contorno_field_text( 'phone', $post_id, (string) $brand['phone'] ),
					'address'     => $address,
					'parentOrganization' => array( '@id' => CONTORNO_CANONICAL_URL . '/#organization' ),
				)
			);

			$lat = (float) contorno_field( 'latitude', $post_id, 0 );
			$lng = (float) contorno_field( 'longitude', $post_id, 0 );
			if ( 0.0 !== $lat && 0.0 !== $lng ) {
				$business['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => $lat,
					'longitude' => $lng,
				);
			}

			$graph[] = $business;
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	},
	20
);

/**
 * Guarda-corpo: nada de branding anterior em saida publica.
 *
 * O projeto React passou por limpeza de residuos de outro template
 * (renatoassis.com.br). Este filtro registra um aviso no log caso algum
 * conteudo migrado reintroduza esses dominios.
 */
add_filter(
	'the_content',
	static function ( string $content ): string {
		if ( ! is_admin() && defined( 'WP_DEBUG' ) && WP_DEBUG && str_contains( $content, 'renatoassis' ) ) {
			error_log( '[contorno] Conteudo com branding legado (renatoassis) em: ' . ( is_singular() ? (string) get_permalink() : 'contexto nao singular' ) );
		}

		return $content;
	},
	99
);
