<?php
/**
 * Log administrativo (option autoload = no, limitado a N entradas).
 *
 * Nunca registra token, header Authorization, senha nem dados de alunos.
 * Mensagens passam por sanitize antes de gravar.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Log {

	public const OPTION = 'contorno_evo_log';
	public const LIMIT  = 400;

	/**
	 * @param array<string,mixed> $context  unidade, id_branch, id_membership, http, action
	 */
	public static function add( string $level, string $message, array $context = array() ): void {
		$entry = array(
			'time'          => time(),
			'level'         => in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : 'info',
			'message'       => self::sanitize( $message ),
			'action'        => sanitize_key( (string) ( $context['action'] ?? '' ) ),
			'unit'          => sanitize_text_field( (string) ( $context['unit'] ?? '' ) ),
			'id_branch'     => sanitize_text_field( (string) ( $context['id_branch'] ?? '' ) ),
			'id_membership' => sanitize_text_field( (string) ( $context['id_membership'] ?? '' ) ),
			'http'          => (int) ( $context['http'] ?? 0 ),
		);

		$log = self::all();
		array_unshift( $log, $entry );

		if ( count( $log ) > self::LIMIT ) {
			$log = array_slice( $log, 0, self::LIMIT );
		}

		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $log, '', false );
		} else {
			update_option( self::OPTION, $log, false );
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		$log = get_option( self::OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	public static function clear(): void {
		update_option( self::OPTION, array(), false );
	}

	/**
	 * Remove qualquer coisa que pareca credencial antes de gravar.
	 */
	public static function sanitize( string $message ): string {
		// Tudo que vier depois de "Authorization:" / "token:" e qualquer Basic/Bearer <valor> e segredo.
		$message = (string) preg_replace( '/(authorization\s*[:=]\s*)[^\r\n]+/i', '$1[oculto]', $message );
		$message = (string) preg_replace( '/\b(basic|bearer)\s+[A-Za-z0-9+\/=_\-.]{8,}/i', '$1 [oculto]', $message );
		$message = (string) preg_replace( '/(token\s*[:=]\s*)[^\s,;]+/i', '$1[oculto]', $message );

		return sanitize_text_field( mb_substr( $message, 0, 500 ) );
	}
}
