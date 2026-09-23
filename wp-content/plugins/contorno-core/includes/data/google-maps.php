<?php
/**
 * Chave da Google Maps Platform (Geocoding API) — armazenamento seguro.
 *
 * Precedencia:
 *   1. constante CONTORNO_GOOGLE_MAPS_API_KEY no wp-config.php;
 *   2. option `contorno_google_maps_api_key`, CIFRADA (libsodium secretbox,
 *      ou AES-256-GCM sem libsodium) com chave derivada dos salts do
 *      WordPress, gravada com autoload = no.
 *
 * A chave so e usada no servidor (geo.php, cabecalho X-Goog-Api-Key). Ela
 * nunca vai para o HTML, para o dataset nem para logs; a tela de
 * configuracao mostra apenas os 4 ultimos caracteres.
 *
 * Limite real: quem tem o wp-config.php E o banco consegue decifrar. O ganho
 * e que um dump do banco sozinho nao expoe a chave.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_GOOGLE_KEY_OPTION = 'contorno_google_maps_api_key';

function contorno_secret_key(): string {
	$material = '';

	foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $constant ) {
		$material .= defined( $constant ) ? (string) constant( $constant ) : '';
	}

	return hash( 'sha256', 'contorno-core-secret|' . $material, true );
}

function contorno_secret_encrypt( string $plain ): string {
	if ( '' === $plain ) {
		return '';
	}

	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		return 'sb1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, contorno_secret_key() ) );
	}

	if ( function_exists( 'openssl_encrypt' ) ) {
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', contorno_secret_key(), OPENSSL_RAW_DATA, $iv, $tag );

		if ( false !== $cipher ) {
			return 'gcm1:' . base64_encode( $iv . $tag . $cipher );
		}
	}

	return ''; // Sem criptografia disponivel: nao guarda em claro.
}

function contorno_secret_decrypt( string $stored ): string {
	if ( str_starts_with( $stored, 'sb1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$raw = base64_decode( substr( $stored, 4 ), true );

		if ( false !== $raw && strlen( $raw ) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), contorno_secret_key() );

			return false === $plain ? '' : $plain;
		}
	}

	if ( str_starts_with( $stored, 'gcm1:' ) && function_exists( 'openssl_decrypt' ) ) {
		$raw = base64_decode( substr( $stored, 5 ), true );

		if ( false !== $raw && strlen( $raw ) > 28 ) {
			$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', contorno_secret_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );

			return false === $plain ? '' : $plain;
		}
	}

	return '';
}

/**
 * Chave em uso ('' quando nao configurada). A option ja vem do cache do
 * WordPress; decifrar e barato, entao nao ha memo (salvar e testar na
 * mesma requisicao enxerga a chave nova).
 */
function contorno_google_maps_api_key(): string {
	if ( defined( 'CONTORNO_GOOGLE_MAPS_API_KEY' ) && '' !== trim( (string) constant( 'CONTORNO_GOOGLE_MAPS_API_KEY' ) ) ) {
		return trim( (string) constant( 'CONTORNO_GOOGLE_MAPS_API_KEY' ) );
	}

	return trim( contorno_secret_decrypt( (string) get_option( CONTORNO_GOOGLE_KEY_OPTION, '' ) ) );
}

function contorno_google_maps_key_source(): string {
	if ( defined( 'CONTORNO_GOOGLE_MAPS_API_KEY' ) && '' !== trim( (string) constant( 'CONTORNO_GOOGLE_MAPS_API_KEY' ) ) ) {
		return 'constant';
	}

	return '' !== (string) get_option( CONTORNO_GOOGLE_KEY_OPTION, '' ) ? 'option' : 'none';
}

/**
 * Grava a chave cifrada (autoload = no). String vazia remove.
 */
function contorno_google_maps_save_key( string $key ): bool {
	$key = trim( $key );

	if ( '' === $key ) {
		return delete_option( CONTORNO_GOOGLE_KEY_OPTION );
	}

	$cipher = contorno_secret_encrypt( $key );

	if ( '' === $cipher ) {
		return false;
	}

	delete_option( CONTORNO_GOOGLE_KEY_OPTION );

	return add_option( CONTORNO_GOOGLE_KEY_OPTION, $cipher, '', false );
}

/**
 * "••••••••1a2B" — so os 4 ultimos caracteres, para conferencia.
 */
function contorno_google_maps_key_hint(): string {
	$key = contorno_google_maps_api_key();

	return '' === $key ? '' : str_repeat( '•', 8 ) . substr( $key, -4 );
}
