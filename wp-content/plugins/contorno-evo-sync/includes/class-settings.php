<?php
/**
 * Configuracoes e credenciais.
 *
 * Options (todas autoload = no):
 *   contorno_evo_settings  array  dns, base_url, auto_sync, interval_minutes,
 *                                 add_new_plans, hide_inactive, fetch_mode
 *   contorno_evo_token     string token cifrado (Contorno_Evo_Crypto)
 *   contorno_evo_status    array  ultimo teste de conexao / ultima execucao
 *
 * Constantes em wp-config.php tem precedencia sobre o banco:
 *   CONTORNO_EVO_USERNAME  (DNS)
 *   CONTORNO_EVO_TOKEN
 *   CONTORNO_EVO_BASE_URL  (opcional)
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Settings {

	public const OPTION          = 'contorno_evo_settings';
	public const OPTION_TOKEN    = 'contorno_evo_token';
	public const OPTION_STATUS   = 'contorno_evo_status';
	public const MIN_INTERVAL    = 30;    // minutos
	public const MAX_INTERVAL    = 1440;  // minutos

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'dns'              => '',
			'base_url'         => CONTORNO_EVO_DEFAULT_BASE_URL,
			'auto_sync'        => false,
			'interval_minutes' => 360,
			'add_new_plans'    => true,
			'hide_inactive'    => true,
			'fetch_mode'       => 'auto', // auto | global | per-branch
		);
	}

	public static function ensure_defaults(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
		if ( false === get_option( self::OPTION_TOKEN, false ) ) {
			add_option( self::OPTION_TOKEN, '', '', false );
		}
		if ( false === get_option( self::OPTION_STATUS, false ) ) {
			add_option( self::OPTION_STATUS, array(), '', false );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();

		return $all[ $key ] ?? $default;
	}

	/**
	 * @param array<string,mixed> $values
	 */
	public static function save( array $values ): void {
		$current = self::all();
		$clean   = array(
			'dns'              => sanitize_text_field( (string) ( $values['dns'] ?? $current['dns'] ) ),
			'base_url'         => self::sanitize_base_url( (string) ( $values['base_url'] ?? $current['base_url'] ) ),
			'auto_sync'        => ! empty( $values['auto_sync'] ),
			'interval_minutes' => max( self::MIN_INTERVAL, min( self::MAX_INTERVAL, (int) ( $values['interval_minutes'] ?? $current['interval_minutes'] ) ) ),
			'add_new_plans'    => ! empty( $values['add_new_plans'] ),
			'hide_inactive'    => ! empty( $values['hide_inactive'] ),
			'fetch_mode'       => in_array( (string) ( $values['fetch_mode'] ?? '' ), array( 'auto', 'global', 'per-branch' ), true ) ? (string) $values['fetch_mode'] : $current['fetch_mode'],
		);

		self::ensure_defaults();
		update_option( self::OPTION, $clean, false );
	}

	public static function sanitize_base_url( string $url ): string {
		$url = untrailingslashit( trim( $url ) );

		if ( '' === $url || ! preg_match( '#^https://#i', $url ) ) {
			return CONTORNO_EVO_DEFAULT_BASE_URL;
		}

		return esc_url_raw( $url );
	}

	/* ---------------------------------------------------------------
	 * Credenciais
	 * ------------------------------------------------------------- */

	public static function dns(): string {
		if ( defined( 'CONTORNO_EVO_USERNAME' ) && '' !== (string) constant( 'CONTORNO_EVO_USERNAME' ) ) {
			return (string) constant( 'CONTORNO_EVO_USERNAME' );
		}

		return (string) self::get( 'dns', '' );
	}

	public static function base_url(): string {
		if ( defined( 'CONTORNO_EVO_BASE_URL' ) && '' !== (string) constant( 'CONTORNO_EVO_BASE_URL' ) ) {
			return self::sanitize_base_url( (string) constant( 'CONTORNO_EVO_BASE_URL' ) );
		}

		return self::sanitize_base_url( (string) self::get( 'base_url', CONTORNO_EVO_DEFAULT_BASE_URL ) );
	}

	/**
	 * Token em claro — usado SOMENTE para montar o header Authorization.
	 * Nunca chegue com isso perto de HTML, JS, log ou REST.
	 */
	public static function token(): string {
		if ( defined( 'CONTORNO_EVO_TOKEN' ) && '' !== (string) constant( 'CONTORNO_EVO_TOKEN' ) ) {
			return (string) constant( 'CONTORNO_EVO_TOKEN' );
		}

		$stored = (string) get_option( self::OPTION_TOKEN, '' );

		return $stored === '' ? '' : (string) ( Contorno_Evo_Crypto::decrypt( $stored ) ?? '' );
	}

	public static function credentials_from_constants(): bool {
		return defined( 'CONTORNO_EVO_USERNAME' ) && defined( 'CONTORNO_EVO_TOKEN' )
			&& '' !== (string) constant( 'CONTORNO_EVO_USERNAME' ) && '' !== (string) constant( 'CONTORNO_EVO_TOKEN' );
	}

	public static function has_token(): bool {
		return '' !== self::token();
	}

	/** Token existe no banco mas nao decifra (salts trocados). */
	public static function token_is_unreadable(): bool {
		if ( self::credentials_from_constants() ) {
			return false;
		}

		$stored = (string) get_option( self::OPTION_TOKEN, '' );

		return '' !== $stored && null === Contorno_Evo_Crypto::decrypt( $stored );
	}

	public static function has_credentials(): bool {
		return '' !== self::dns() && self::has_token();
	}

	/**
	 * Grava um token novo. String vazia = PRESERVA o atual (regra da tela).
	 */
	public static function save_token( string $plain ): bool {
		$plain = trim( $plain );

		if ( '' === $plain ) {
			return true;
		}

		$cipher = Contorno_Evo_Crypto::encrypt( $plain );

		if ( '' === $cipher ) {
			return false;
		}

		self::ensure_defaults();
		update_option( self::OPTION_TOKEN, $cipher, false );

		return true;
	}

	public static function clear_token(): void {
		update_option( self::OPTION_TOKEN, '', false );
	}

	/** Mascara de exibicao — nunca revela o valor. */
	public static function token_mask(): string {
		return self::has_token() ? str_repeat( '•', 16 ) : '';
	}

	/* ---------------------------------------------------------------
	 * Status (ultimo teste / ultima execucao)
	 * ------------------------------------------------------------- */

	/**
	 * @return array<string,mixed>
	 */
	public static function status(): array {
		$status = get_option( self::OPTION_STATUS, array() );

		return is_array( $status ) ? $status : array();
	}

	/**
	 * @param array<string,mixed> $patch
	 */
	public static function update_status( array $patch ): void {
		self::ensure_defaults();
		update_option( self::OPTION_STATUS, array_merge( self::status(), $patch ), false );
	}
}
