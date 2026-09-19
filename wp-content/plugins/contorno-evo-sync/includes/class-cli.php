<?php
/**
 * WP-CLI: wp contorno evo <status|test|branches|sync|log|rollback>
 *
 *   wp contorno evo status
 *   wp contorno evo test
 *   wp contorno evo branches
 *   wp contorno evo sync [--dry-run] [--branch=<id>] [--post=<id>]
 *   wp contorno evo log [--limit=<n>]
 *   wp contorno evo rollback --post=<id>
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

final class Contorno_Evo_CLI {

	/**
	 * Estado da integracao.
	 */
	public function status(): void {
		$settings = Contorno_Evo_Settings::all();
		$status   = Contorno_Evo_Settings::status();
		$stats    = Contorno_Evo_Admin::stats();

		WP_CLI::log( 'Credenciais: ' . ( Contorno_Evo_Settings::has_credentials() ? 'ok' . ( Contorno_Evo_Settings::credentials_from_constants() ? ' (wp-config.php)' : ' (banco, cifrado)' ) : 'AUSENTES' ) );
		WP_CLI::log( 'Base URL: ' . Contorno_Evo_Settings::base_url() );
		WP_CLI::log( 'Auto sync: ' . ( $settings['auto_sync'] ? 'ligado, a cada ' . (int) $settings['interval_minutes'] . ' min' : 'desligado' ) );
		WP_CLI::log( 'Próxima execução: ' . ( Contorno_Evo_Cron::next_run() > 0 ? wp_date( 'd/m/Y H:i', Contorno_Evo_Cron::next_run() ) : '—' ) );
		WP_CLI::log( 'Última sincronização: ' . ( ! empty( $status['last_sync'] ) ? wp_date( 'd/m/Y H:i', (int) $status['last_sync'] ) : 'nunca' ) );
		WP_CLI::log( sprintf( 'Unidades: %d vinculadas, %d pendentes', $stats['mapped'], $stats['unmapped'] ) );
		WP_CLI::log( sprintf( 'Planos EVO: %d ativos, %d inativos, %d ausentes', $stats['active'], $stats['inactive'], $stats['missing'] ) );
	}

	/**
	 * Testa a conexao com a EVO.
	 */
	public function test(): void {
		$result = ( new Contorno_Evo_Client() )->test();
		Contorno_Evo_Log::add( $result['ok'] ? 'info' : 'error', 'teste de conexão (cli): ' . $result['message'], array( 'action' => 'test', 'http' => $result['http'] ) );
		Contorno_Evo_Settings::update_status( array( 'last_test' => time(), 'last_test_ok' => $result['ok'], 'connected' => $result['ok'] ) );

		if ( $result['ok'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Lista as filiais visiveis pela chave.
	 */
	public function branches(): void {
		$result = ( new Contorno_Evo_Client() )->branches();

		if ( ! $result['ok'] ) {
			WP_CLI::error( $result['message'] );
		}

		WP_CLI\Utils\format_items( 'table', $result['branches'], array( 'id', 'name', 'group' ) );
	}

	/**
	 * Sincroniza os planos.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Mostra as diferencas sem gravar.
	 *
	 * [--branch=<id>]
	 * : Somente a filial EVO informada.
	 *
	 * [--post=<id>]
	 * : Somente a unidade/CTN (ID do post).
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc
	 */
	public function sync( array $args, array $assoc ): void {
		$summary = Contorno_Evo_Sync::run(
			array(
				'dry_run' => isset( $assoc['dry-run'] ),
				'branch'  => (int) ( $assoc['branch'] ?? 0 ),
				'post_id' => (int) ( $assoc['post'] ?? 0 ),
				'trigger' => 'cli',
			)
		);

		foreach ( (array) $summary['messages'] as $message ) {
			WP_CLI::warning( (string) $message );
		}

		foreach ( (array) $summary['diffs'] as $slug => $unit ) {
			WP_CLI::log( sprintf( '— %s (filial %d)', $unit['title'], $unit['branch'] ) );
			foreach ( (array) $unit['diffs'] as $diff ) {
				$detail = 'new' === $diff['type'] ? 'novo' : ( 'missing' === $diff['type'] ? 'ausente no EVO' : implode( ', ', array_map( static fn ( string $k, array $p ): string => sprintf( '%s: %s → %s', $k, self::fmt( $p[0] ), self::fmt( $p[1] ) ), array_keys( (array) $diff['changes'] ), array_values( (array) $diff['changes'] ) ) ) );
				WP_CLI::log( sprintf( '   %s (%d): %s', $diff['name'], $diff['id_membership'], $detail ) );
			}
		}

		$line = sprintf(
			'%d unidades avaliadas, %d planos recebidos, %d atualizados, %d novos, %d sem alteração, %d desativados, %d erros, %d requisições',
			$summary['units'],
			$summary['received'],
			$summary['updated'],
			$summary['created'],
			$summary['unchanged'],
			$summary['disabled'],
			$summary['errors'],
			$summary['requests']
		);

		if ( $summary['errors'] > 0 ) {
			WP_CLI::warning( ( $summary['dry_run'] ? '[dry-run] ' : '' ) . $line );
		} else {
			WP_CLI::success( ( $summary['dry_run'] ? '[dry-run] ' : '' ) . $line );
		}
	}

	/**
	 * Ultimas entradas do log.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : Quantidade (padrao 30).
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc
	 */
	public function log( array $args, array $assoc ): void {
		$rows = array_slice( Contorno_Evo_Log::all(), 0, max( 1, (int) ( $assoc['limit'] ?? 30 ) ) );
		$rows = array_map(
			static fn ( array $e ): array => array(
				'quando'   => wp_date( 'd/m H:i:s', (int) $e['time'] ),
				'nivel'    => $e['level'],
				'acao'     => $e['action'],
				'unidade'  => $e['unit'],
				'branch'   => $e['id_branch'],
				'http'     => $e['http'] ?: '',
				'mensagem' => $e['message'],
			),
			$rows
		);
		WP_CLI\Utils\format_items( 'table', $rows, array( 'quando', 'nivel', 'acao', 'unidade', 'branch', 'http', 'mensagem' ) );
	}

	/**
	 * Restaura a versao anterior dos planos EVO de uma unidade.
	 *
	 * ## OPTIONS
	 *
	 * --post=<id>
	 * : ID do post da unidade/CTN.
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc
	 */
	public function rollback( array $args, array $assoc ): void {
		$post_id = (int) ( $assoc['post'] ?? 0 );

		if ( $post_id > 0 && Contorno_Evo_Sync::rollback( $post_id ) ) {
			WP_CLI::success( 'Restaurado.' );
		} else {
			WP_CLI::error( 'Sem snapshot para restaurar.' );
		}
	}

	private static function fmt( mixed $v ): string {
		if ( is_bool( $v ) ) {
			return $v ? 'sim' : 'não';
		}
		if ( is_array( $v ) ) {
			return implode( '|', array_map( 'strval', $v ) );
		}

		return mb_substr( (string) $v, 0, 60 );
	}
}

WP_CLI::add_command( 'contorno evo', 'Contorno_Evo_CLI' );
