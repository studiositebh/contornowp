<?php
/**
 * Comandos WP-CLI.
 *
 *   wp contorno migrate                # importa tudo
 *   wp contorno migrate --dry-run      # simula, nao grava
 *   wp contorno migrate --force        # sobrescreve paginas ja editadas
 *   wp contorno migrate --steps=units  # so um passo (assets|units|ctns|pages|menus)
 *   wp contorno status                 # o que ja existe no banco
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once CONTORNO_CORE_DIR . 'includes/migration/importer.php';

final class Contorno_CLI {

	/**
	 * Importa o conteudo do site React para o WordPress.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Simula a importacao sem gravar nada.
	 *
	 * [--force]
	 * : Sobrescreve paginas que ja foram editadas no painel.
	 *
	 * [--steps=<steps>]
	 * : Passos a executar, separados por virgula. Padrao: assets,units,ctns,pages,menus
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc_args
	 */
	public function migrate( array $args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		$force   = isset( $assoc_args['force'] );

		$steps = isset( $assoc_args['steps'] )
			? array_map( 'trim', explode( ',', (string) $assoc_args['steps'] ) )
			: array( 'assets', 'thumbs', 'units', 'ctns', 'pages', 'menus' );

		$migration = new Contorno_Migration( $force, $dry_run );
		$report    = $migration->run( $steps );

		foreach ( $report->lines as $line ) {
			WP_CLI::log( $line );
		}

		foreach ( $report->warnings as $warning ) {
			WP_CLI::warning( $warning );
		}

		if ( array() === $report->lines && array() === $report->warnings ) {
			WP_CLI::error( 'Nada foi processado.' );
		}

		WP_CLI::success(
			sprintf(
				'Migracao concluida — anexos: %d, unidades: %d, CTNs: %d, paginas: %d, itens de menu: %d.',
				$report->counts['attachments'],
				$report->counts['units'],
				$report->counts['ctns'],
				$report->counts['pages'],
				$report->counts['menu_items']
			)
		);
	}

	/**
	 * Mostra o estado atual da migracao.
	 */
	public function status(): void {
		$dataset = Contorno_Migration::read_dataset();

		WP_CLI::log( 'Dataset: ' . ( null === $dataset ? 'AUSENTE' : 'ok (v' . (string) ( $dataset['version'] ?? '?' ) . ', ' . (string) ( $dataset['generatedAt'] ?? '?' ) . ')' ) );

		if ( null !== $dataset ) {
			WP_CLI::log( sprintf( '  no dataset: %d unidades, %d CTNs, %d paginas, %d assets', count( (array) ( $dataset['units'] ?? array() ) ), count( (array) ( $dataset['ctns'] ?? array() ) ), count( (array) ( $dataset['pages'] ?? array() ) ), count( (array) ( $dataset['assets'] ?? array() ) ) ) );
		}

		WP_CLI::log( sprintf( 'No banco: %d unidades, %d CTNs', count( contorno_get_units() ), count( contorno_get_ctns() ) ) );

		foreach ( array( 'home', 'unidades', 'ctn' ) as $slug ) {
			$page = get_page_by_path( $slug );
			WP_CLI::log( sprintf( '  pagina /%s: %s', $slug, $page instanceof WP_Post ? 'ok (#' . $page->ID . ')' : 'AUSENTE' ) );
		}

		WP_CLI::log( 'WPBakery: ' . ( defined( 'WPB_VC_VERSION' ) ? 'ativo ' . WPB_VC_VERSION : 'INATIVO' ) );
		WP_CLI::log( 'Tema ativo: ' . (string) get_option( 'stylesheet' ) );
		WP_CLI::log( 'Ultima migracao: ' . ( (string) get_option( 'contorno_migration_last_run', 'nunca' ) ) );
	}
}

WP_CLI::add_command( 'contorno', 'Contorno_CLI' );
