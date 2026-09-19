<?php
/**
 * Comandos WP-CLI.
 *
 *   wp contorno migrate                # importa tudo
 *   wp contorno migrate --dry-run      # simula, nao grava
 *   wp contorno migrate --force        # sobrescreve paginas ja editadas
 *   wp contorno migrate --steps=units  # so um passo (assets|units|ctns|pages|menus)
 *   wp contorno status                 # o que ja existe no banco
 *   wp contorno geocode                # preenche lat/lng de unidades sem coordenadas
 *   wp contorno geocode --dry-run      # so mostra
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
	 * Preenche latitude/longitude das unidades que ainda nao tem coordenadas,
	 * geocodificando o endereco cadastrado (Nominatim / OpenStreetMap).
	 *
	 * Unidades com coordenadas ficam intactas, salvo com --force. Respeita o
	 * limite de 1 requisicao por segundo do Nominatim.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Mostra o que seria geocodificado sem gravar.
	 *
	 * [--force]
	 * : Recalcula tambem unidades que ja tem coordenadas.
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc_args
	 */
	public function geocode( array $args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		$force   = isset( $assoc_args['force'] );
		$done    = 0;
		$failed  = 0;
		$skipped = 0;

		foreach ( contorno_get_units( array( 'post_status' => 'any' ) ) as $unit ) {
			if ( ! $force && null !== contorno_unit_coords( $unit->ID ) ) {
				++$skipped;
				continue;
			}

			$address = contorno_field_text( 'address', $unit->ID );
			$city    = contorno_field_text( 'city', $unit->ID );
			$state   = contorno_field_text( 'state', $unit->ID );

			if ( '' === $address && '' === $city ) {
				WP_CLI::warning( sprintf( '%s: sem endereco nem cidade — pulado.', $unit->post_name ) );
				++$failed;
				continue;
			}

			$hit = contorno_geocode_address( $address, $city, $state );
			sleep( 1 );

			if ( null === $hit ) {
				WP_CLI::warning( sprintf( '%s: endereco nao localizado (%s).', $unit->post_name, $address ) );
				++$failed;
				continue;
			}

			WP_CLI::log( sprintf( '%s %s: %.6f, %.6f — %s', $dry_run ? 'Geocodificaria' : 'Geocodificado', $unit->post_name, $hit['lat'], $hit['lng'], $hit['label'] ) );

			if ( ! $dry_run ) {
				contorno_update_field( $unit->ID, 'latitude', (string) $hit['lat'] );
				contorno_update_field( $unit->ID, 'longitude', (string) $hit['lng'] );
			}

			++$done;
		}

		WP_CLI::success( sprintf( 'Geocode — %d preenchidas, %d ja tinham coordenadas, %d sem resultado.', $done, $skipped, $failed ) );
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

		$units_in_db  = contorno_get_units();
		$without_geo  = array_filter( $units_in_db, static fn ( WP_Post $unit ): bool => null === contorno_unit_coords( $unit->ID ) );

		WP_CLI::log( sprintf( 'No banco: %d unidades (%d sem coordenadas), %d CTNs', count( $units_in_db ), count( $without_geo ), count( contorno_get_ctns() ) ) );

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
