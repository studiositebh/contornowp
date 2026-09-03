<?php
/**
 * Constantes de marca — porte fiel de src/lib/site-data.ts do projeto React.
 *
 * Fonte unica para header, footer, SEO, OpenGraph e JSON-LD. Nao duplicar
 * estes valores dentro do page builder.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string,string|int>
 */
function contorno_brand(): array {
	static $brand = null;

	if ( null !== $brand ) {
		return $brand;
	}

	$brand = array(
		'name'          => 'Contorno do Corpo',
		'long_name'     => 'Academia Contorno do Corpo',
		'tagline'       => 'Experiência premium. Resultados reais.',
		'description'   => 'Academia Contorno do Corpo: experiência premium, resultados reais e uma unidade perto de você em Minas Gerais.',
		'city'          => 'Belo Horizonte',
		'region'        => 'MG',
		'country'       => 'BR',
		'phone'         => '3140420177',
		'whatsapp'      => '3140420177',
		'email'         => 'contato@contornodocorpo.com.br',
		'instagram'     => 'https://www.instagram.com/contornodocorpo/',
		// Imagem social oficial (400x200) — nao substituir por logo generico.
		'og_image'      => '/brand/Logocompartilhamento.webp',
		'og_image_w'    => 400,
		'og_image_h'    => 200,
		'og_image_type' => 'image/webp',
		'favicon'       => '/brand/favicon.webp',
		'logo'          => '/brand/logo.png',
		'logo_light'    => '/brand/logo-light.png',
		'ctn_logo'      => '/brand/ctn-logo.webp',
	);

	/**
	 * Permite ao cliente sobrescrever dados de marca sem editar o tema.
	 *
	 * @param array<string,string|int> $brand
	 */
	$brand = (array) apply_filters( 'contorno_brand', $brand );

	return $brand;
}

function contorno_brand_get( string $key, string $default = '' ): string {
	$brand = contorno_brand();

	return isset( $brand[ $key ] ) ? (string) $brand[ $key ] : $default;
}

/**
 * Resolve um caminho de asset de marca (/brand/...) para URL publica.
 *
 * Os assets migrados do React vivem em wp-content/uploads/contorno/ para que o
 * cliente possa troca-los pela Biblioteca de Midia. Se um asset ainda nao foi
 * migrado, cai no arquivo empacotado com o tema.
 */
function contorno_asset_url( string $path ): string {
	$path = '/' . ltrim( $path, '/' );

	$uploads  = wp_get_upload_dir();
	$candidate = $uploads['basedir'] . '/contorno' . $path;

	if ( is_readable( $candidate ) ) {
		return $uploads['baseurl'] . '/contorno' . $path;
	}

	return contorno_core_url( 'assets/img' ) . $path;
}

/**
 * Numero de WhatsApp em formato E.164 para links wa.me.
 */
function contorno_whatsapp_link( string $number = '', string $message = '' ): string {
	$number = $number !== '' ? $number : contorno_brand_get( 'whatsapp' );
	$digits = preg_replace( '/\D/', '', $number );

	if ( ! is_string( $digits ) || '' === $digits ) {
		return '';
	}

	// Numeros cadastrados vem sem DDI; a rede e brasileira.
	if ( strpos( $digits, '55' ) !== 0 ) {
		$digits = '55' . $digits;
	}

	$url = 'https://wa.me/' . $digits;

	if ( '' !== $message ) {
		$url .= '?text=' . rawurlencode( $message );
	}

	return $url;
}

/**
 * Favicon oficial — substitui o site-icon padrao do WordPress.
 */
add_action(
	'wp_head',
	static function (): void {
		$favicon = contorno_asset_url( contorno_brand_get( 'favicon' ) );
		printf( '<link rel="icon" type="image/webp" href="%s" />' . "\n", esc_url( $favicon ) );
		printf( '<link rel="apple-touch-icon" href="%s" />' . "\n", esc_url( $favicon ) );
	},
	1
);
