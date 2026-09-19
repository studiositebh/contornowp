<?php
/**
 * Protecao do token da API em repouso.
 *
 * Estrategia (documentada aqui para nao existir "falsa seguranca"):
 *
 *  - O token e cifrado com libsodium (XSalsa20-Poly1305, secretbox) usando uma
 *    chave de 32 bytes DERIVADA dos salts do proprio WordPress
 *    (AUTH_KEY + SECURE_AUTH_KEY + LOGGED_IN_KEY + NONCE_KEY, via SHA-256).
 *    Nenhuma chave e escrita no codigo. Sem libsodium, cai em OpenSSL
 *    AES-256-GCM com a mesma chave derivada.
 *  - O que fica no banco e "sb1:" ou "gcm1:" + base64(nonce/iv + tag + cifra).
 *  - Quem tem acesso ao wp-config.php E ao banco consegue decifrar — esse e o
 *    limite real de qualquer segredo guardado pelo WordPress. O ganho e que um
 *    dump do banco sozinho (backup, SQL injection, phpMyAdmin) nao expoe o
 *    token, e que trocar os salts invalida o valor guardado.
 *  - Se os salts forem trocados, o token precisa ser informado de novo; a tela
 *    avisa "token gravado com chave anterior".
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Crypto {

	private static function key(): string {
		$material = '';

		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $constant ) {
			$material .= defined( $constant ) ? (string) constant( $constant ) : '';
		}

		// Salts padrao ("put your unique phrase here") ou ausentes: ainda cifra,
		// mas a tela de configuracao avisa que os salts precisam ser definidos.
		return hash( 'sha256', 'contorno-evo-sync|' . $material, true );
	}

	public static function salts_are_configured(): bool {
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $constant ) {
			if ( ! defined( $constant ) || '' === (string) constant( $constant ) || str_contains( (string) constant( $constant ), 'put your unique phrase here' ) ) {
				return false;
			}
		}

		return true;
	}

	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$box   = sodium_crypto_secretbox( $plain, $nonce, self::key() );

			return 'sb1:' . base64_encode( $nonce . $box );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );

			if ( false !== $cipher ) {
				return 'gcm1:' . base64_encode( $iv . $tag . $cipher );
			}
		}

		// Sem nenhum mecanismo criptografico disponivel: nao guardar em claro.
		return '';
	}

	public static function decrypt( string $stored ): ?string {
		if ( '' === $stored ) {
			return null;
		}

		if ( str_starts_with( $stored, 'sb1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$raw = base64_decode( substr( $stored, 4 ), true );

			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return null;
			}

			$plain = sodium_crypto_secretbox_open(
				substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				self::key()
			);

			return false === $plain ? null : $plain;
		}

		if ( str_starts_with( $stored, 'gcm1:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $stored, 5 ), true );

			if ( false === $raw || strlen( $raw ) <= 28 ) {
				return null;
			}

			$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );

			return false === $plain ? null : $plain;
		}

		return null;
	}

	public static function is_available(): bool {
		return function_exists( 'sodium_crypto_secretbox' ) || function_exists( 'openssl_encrypt' );
	}

	public static function backend(): string {
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			return 'libsodium secretbox';
		}

		return function_exists( 'openssl_encrypt' ) ? 'OpenSSL AES-256-GCM' : 'indisponível';
	}
}
