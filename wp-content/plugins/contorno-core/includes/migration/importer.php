<?php
/**
 * Importador do dataset de migracao.
 *
 * Le data/dataset.json (gerado no repositorio React por
 * `bun run scripts/export-wp-dataset.ts`) e cria/atualiza:
 *
 *  1. anexos na Biblioteca de Midia, a partir de wp-content/uploads/contorno/;
 *  2. CPT unidade  — 1 post por academia, com os campos estruturados;
 *  3. CPT ctn      — 1 post por CTN;
 *  4. paginas Home, /unidades e /ctn montadas com elementos do WPBakery;
 *  5. menu principal.
 *
 * IDEMPOTENTE: casa por slug. Rodar de novo atualiza, nao duplica. Por isso
 * o mesmo comando serve para staging e producao.
 *
 * NAO sobrescreve conteudo editorial que o cliente ja tenha alterado, a menos
 * que --force seja usado.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resultado de uma importacao, para relatorio no CLI ou no painel.
 */
final class Contorno_Migration_Report {

	/** @var array<int,string> */
	public array $lines = array();

	/** @var array<string,int> */
	public array $counts = array(
		'attachments' => 0,
		'units'       => 0,
		'ctns'        => 0,
		'pages'       => 0,
		'menu_items'  => 0,
		'skipped'     => 0,
	);

	/** @var array<int,string> */
	public array $warnings = array();

	public function log( string $line ): void {
		$this->lines[] = $line;
	}

	public function warn( string $line ): void {
		$this->warnings[] = $line;
	}

	public function bump( string $key, int $by = 1 ): void {
		$this->counts[ $key ] = ( $this->counts[ $key ] ?? 0 ) + $by;
	}
}

final class Contorno_Migration {

	private Contorno_Migration_Report $report;

	/** Mapa caminho do React (/brand/x.jpg) => ID do anexo. */
	private array $attachments = array();

	private bool $force;

	private bool $dry_run;

	public function __construct( bool $force = false, bool $dry_run = false ) {
		$this->report  = new Contorno_Migration_Report();
		$this->force   = $force;
		$this->dry_run = $dry_run;
	}

	public static function dataset_path(): string {
		return CONTORNO_CORE_DIR . 'data/dataset.json';
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function read_dataset(): ?array {
		$path = self::dataset_path();

		if ( ! is_readable( $path ) ) {
			return null;
		}

		$raw = file_get_contents( $path );

		if ( ! is_string( $raw ) ) {
			return null;
		}

		$data = json_decode( $raw, true );

		return is_array( $data ) ? $data : null;
	}

	public function run( array $steps = array( 'assets', 'units', 'ctns', 'pages', 'menus' ) ): Contorno_Migration_Report {
		$dataset = self::read_dataset();

		if ( null === $dataset ) {
			$this->report->warn(
				sprintf(
					'Dataset nao encontrado em %s. Gere-o no repositorio React com: bun run scripts/export-wp-dataset.ts',
					self::dataset_path()
				)
			);

			return $this->report;
		}

		$this->report->log( sprintf( 'Dataset v%s gerado em %s', (string) ( $dataset['version'] ?? '?' ), (string) ( $dataset['generatedAt'] ?? '?' ) ) );

		if ( $this->dry_run ) {
			$this->report->log( 'MODO SIMULACAO — nada sera gravado.' );
		}

		if ( in_array( 'assets', $steps, true ) ) {
			$this->import_assets( (array) ( $dataset['assets'] ?? array() ) );
		}

		if ( in_array( 'units', $steps, true ) ) {
			$this->import_entities( (array) ( $dataset['units'] ?? array() ), CONTORNO_CPT_UNIT, 'units' );
		}

		if ( in_array( 'ctns', $steps, true ) ) {
			$this->import_entities( (array) ( $dataset['ctns'] ?? array() ), CONTORNO_CPT_CTN, 'ctns' );
		}

		if ( in_array( 'pages', $steps, true ) ) {
			$this->import_pages( (array) ( $dataset['pages'] ?? array() ) );
		}

		if ( in_array( 'menus', $steps, true ) ) {
			$this->import_menu( (array) ( $dataset['menus']['primary'] ?? array() ) );
		}

		if ( ! $this->dry_run ) {
			flush_rewrite_rules();
			update_option( 'contorno_migration_last_run', current_time( 'mysql' ) );
		}

		return $this->report;
	}

	/* ============================================================
	 * Assets
	 * ========================================================== */

	/**
	 * Registra na Biblioteca de Midia os arquivos ja presentes em
	 * wp-content/uploads/contorno/ (copiados pelo exportador).
	 *
	 * Nao baixa nada da internet e nao move arquivo: apenas cria o anexo
	 * apontando para o arquivo existente. Assim o editor passa a poder trocar
	 * a imagem pela biblioteca, sem FTP nem Git.
	 *
	 * @param array<int,string> $assets
	 */
	private function import_assets( array $assets ): void {
		$uploads = wp_get_upload_dir();

		foreach ( $assets as $asset ) {
			$asset = '/' . ltrim( (string) $asset, '/' );
			$file  = $uploads['basedir'] . '/contorno' . $asset;

			if ( ! is_readable( $file ) ) {
				$this->report->warn( 'Asset ausente no servidor: ' . $asset );
				continue;
			}

			$existing = $this->find_attachment_by_source( $asset );

			if ( $existing ) {
				$this->attachments[ $asset ] = $existing;
				$this->report->bump( 'skipped' );
				continue;
			}

			if ( $this->dry_run ) {
				$this->report->log( 'Criaria anexo: ' . $asset );
				$this->report->bump( 'attachments' );
				continue;
			}

			$type = wp_check_filetype( basename( $file ), null );

			$attachment_id = wp_insert_attachment(
				array(
					'post_mime_type' => (string) ( $type['type'] ?? '' ),
					'post_title'     => sanitize_file_name( pathinfo( $file, PATHINFO_FILENAME ) ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				),
				$file
			);

			if ( is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
				$this->report->warn( 'Falha ao criar anexo: ' . $asset );
				continue;
			}

			require_once ABSPATH . 'wp-admin/includes/image.php';
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );

			// Marca a origem para a importacao ser idempotente.
			update_post_meta( $attachment_id, '_contorno_source_path', $asset );

			$this->attachments[ $asset ] = $attachment_id;
			$this->report->bump( 'attachments' );
		}

		$this->report->log( sprintf( 'Anexos: %d criados, %d ja existiam.', $this->report->counts['attachments'], $this->report->counts['skipped'] ) );
	}

	private function find_attachment_by_source( string $path ): int {
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_contorno_source_path', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return isset( $found[0] ) ? (int) $found[0] : 0;
	}

	/**
	 * Troca um caminho do React pelo ID do anexo correspondente, quando ele
	 * existir. Caminhos sem anexo continuam funcionando via
	 * contorno_asset_url(), que le direto de uploads/contorno/.
	 */
	private function map_asset( mixed $value ): mixed {
		if ( ! is_string( $value ) || '' === $value || ! str_starts_with( $value, '/' ) ) {
			return $value;
		}

		return isset( $this->attachments[ $value ] ) ? (string) $this->attachments[ $value ] : $value;
	}

	/**
	 * Aplica map_asset() recursivamente num valor de campo.
	 */
	private function map_assets_deep( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$mapped = array();
			foreach ( $value as $key => $item ) {
				$mapped[ $key ] = $this->map_assets_deep( $item );
			}

			return $mapped;
		}

		return $this->map_asset( $value );
	}

	/* ============================================================
	 * CPTs
	 * ========================================================== */

	/**
	 * @param array<int,array<string,mixed>> $entities
	 */
	private function import_entities( array $entities, string $post_type, string $counter ): void {
		$media_fields = array();

		foreach ( contorno_flat_fields( $post_type ) as $name => $definition ) {
			$type = (string) ( $definition['type'] ?? 'text' );
			if ( in_array( $type, array( 'media', 'media_list' ), true ) ) {
				$media_fields[] = $name;
			}
		}

		foreach ( $entities as $entity ) {
			$slug = sanitize_title( (string) ( $entity['slug'] ?? '' ) );

			if ( '' === $slug ) {
				continue;
			}

			$existing = get_posts(
				array(
					'post_type'      => $post_type,
					'name'           => $slug,
					'post_status'    => 'any',
					'posts_per_page' => 1,
				)
			);

			$post_id = isset( $existing[0] ) ? (int) $existing[0]->ID : 0;

			if ( $this->dry_run ) {
				$this->report->log( sprintf( '%s %s: %s', $post_id ? 'Atualizaria' : 'Criaria', $post_type, $slug ) );
				$this->report->bump( $counter );
				continue;
			}

			$postarr = array(
				'post_type'    => $post_type,
				'post_name'    => $slug,
				'post_title'   => (string) ( $entity['title'] ?? $slug ),
				'post_excerpt' => (string) ( $entity['excerpt'] ?? '' ),
				'post_status'  => 'publish',
				// Preserva a ordem aprovada do bloco de unidades da Home.
				'menu_order'   => (int) ( $entity['menuOrder'] ?? 0 ),
			);

			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				$result        = wp_update_post( $postarr, true );
			} else {
				$result = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $result ) ) {
				$this->report->warn( sprintf( 'Falha em %s/%s: %s', $post_type, $slug, $result->get_error_message() ) );
				continue;
			}

			$post_id = (int) $result;

			// Campos estruturados.
			$fields = (array) ( $entity['fields'] ?? array() );

			foreach ( $fields as $name => $value ) {
				$name = (string) $name;

				if ( in_array( $name, $media_fields, true ) ) {
					$value = $this->map_assets_deep( $value );
				} elseif ( 'plans' === $name || 'brands' === $name || 'equipment' === $name ) {
					// Repeaters podem ter subcampo de midia (logo, image).
					$value = $this->map_assets_deep( $value );
				}

				contorno_update_field( $post_id, $name, $value );
			}

			// Imagem destacada: hero da unidade / da CTN.
			$hero = $fields['image'] ?? ( $fields['hero_image'] ?? '' );
			$hero = $this->map_asset( $hero );
			if ( is_numeric( $hero ) && (int) $hero > 0 ) {
				set_post_thumbnail( $post_id, (int) $hero );
			}

			// Taxonomias.
			$terms = (array) ( $entity['terms'] ?? array() );

			if ( ! empty( $terms['city'] ) ) {
				wp_set_object_terms( $post_id, array( (string) $terms['city'] ), CONTORNO_TAX_CITY, false );
			}

			if ( ! empty( $terms['kind'] ) && taxonomy_exists( CONTORNO_TAX_UNIT_KIND ) ) {
				wp_set_object_terms( $post_id, array( sanitize_title( (string) $terms['kind'] ) ), CONTORNO_TAX_UNIT_KIND, false );
			}

			// Habilita o WPBakery no registro, para a area editorial opcional.
			update_post_meta( $post_id, '_wpb_vc_js_status', 'true' );

			$this->report->bump( $counter );
		}

		$this->report->log( sprintf( '%s: %d processados.', $post_type, $this->report->counts[ $counter ] ) );
	}

	/* ============================================================
	 * Paginas
	 * ========================================================== */

	/**
	 * @param array<int,array<string,mixed>> $pages
	 */
	private function import_pages( array $pages ): void {
		foreach ( $pages as $page ) {
			$slug = sanitize_title( (string) ( $page['slug'] ?? '' ) );

			if ( '' === $slug ) {
				continue;
			}

			$existing = get_page_by_path( $slug );
			$post_id  = $existing instanceof WP_Post ? (int) $existing->ID : 0;

			// Conteudo do builder com os caminhos de asset trocados por IDs,
			// para que o campo "Imagem" do elemento abra na biblioteca.
			$content = (string) ( $page['content'] ?? '' );
			foreach ( $this->attachments as $path => $attachment_id ) {
				$content = str_replace( '"' . $path . '"', '"' . $attachment_id . '"', $content );
			}

			if ( $this->dry_run ) {
				$this->report->log( sprintf( '%s pagina: /%s', $post_id ? 'Atualizaria' : 'Criaria', $slug ) );
				$this->report->bump( 'pages' );
				continue;
			}

			// Respeita edicao do cliente: sem --force, nao sobrescreve conteudo.
			$keep_content = false;

			if ( $post_id ) {
				$imported_hash = (string) get_post_meta( $post_id, '_contorno_imported_hash', true );
				$current_hash  = md5( (string) get_post_field( 'post_content', $post_id ) );

				if ( '' !== $imported_hash && $imported_hash !== $current_hash && ! $this->force ) {
					$keep_content = true;
					$this->report->warn(
						sprintf( 'Pagina /%s foi editada no painel — conteudo preservado. Use --force para sobrescrever.', $slug )
					);
				}
			}

			$postarr = array(
				'post_type'   => 'page',
				'post_name'   => $slug,
				'post_title'  => (string) ( $page['title'] ?? $slug ),
				'post_status' => 'publish',
			);

			if ( ! $keep_content ) {
				$postarr['post_content'] = $content;
			}

			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				$result        = wp_update_post( $postarr, true );
			} else {
				$postarr['post_content'] = $content;
				$result                  = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $result ) ) {
				$this->report->warn( sprintf( 'Falha na pagina /%s: %s', $slug, $result->get_error_message() ) );
				continue;
			}

			$post_id = (int) $result;

			if ( ! $keep_content ) {
				update_post_meta( $post_id, '_contorno_imported_hash', md5( $content ) );
			}

			if ( ! empty( $page['template'] ) ) {
				update_post_meta( $post_id, '_wp_page_template', (string) $page['template'] );
			}

			// Abre no editor do WPBakery em vez do editor padrao.
			update_post_meta( $post_id, '_wpb_vc_js_status', 'true' );

			if ( ! empty( $page['isFront'] ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $post_id );
				$this->report->log( sprintf( 'Pagina inicial definida: /%s', $slug ) );
			}

			$this->report->bump( 'pages' );
		}

		$this->report->log( sprintf( 'Paginas: %d processadas.', $this->report->counts['pages'] ) );
	}

	/* ============================================================
	 * Menu
	 * ========================================================== */

	/**
	 * @param array<int,array<string,string>> $items
	 */
	private function import_menu( array $items ): void {
		if ( array() === $items ) {
			return;
		}

		if ( $this->dry_run ) {
			$this->report->log( sprintf( 'Criaria menu principal com %d itens.', count( $items ) ) );

			return;
		}

		$menu_name = 'Menu principal';
		$menu      = wp_get_nav_menu_object( $menu_name );

		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $menu_name );

			if ( is_wp_error( $menu_id ) ) {
				$this->report->warn( 'Falha ao criar o menu principal: ' . $menu_id->get_error_message() );

				return;
			}

			$menu = wp_get_nav_menu_object( (int) $menu_id );
		}

		if ( ! $menu ) {
			return;
		}

		$existing = wp_get_nav_menu_items( $menu->term_id );
		$titles   = array();

		if ( is_array( $existing ) ) {
			foreach ( $existing as $item ) {
				$titles[] = $item->title;
			}
		}

		foreach ( $items as $item ) {
			$label = (string) ( $item['label'] ?? '' );
			$path  = (string) ( $item['path'] ?? '' );

			if ( '' === $label || in_array( $label, $titles, true ) ) {
				continue;
			}

			$page = get_page_by_path( trim( $path, '/' ) );

			$args = $page instanceof WP_Post
				? array(
					'menu-item-title'     => $label,
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $page->ID,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
				: array(
					'menu-item-title'  => $label,
					'menu-item-url'    => home_url( $path ),
					'menu-item-type'   => 'custom',
					'menu-item-status' => 'publish',
				);

			wp_update_nav_menu_item( $menu->term_id, 0, $args );
			$this->report->bump( 'menu_items' );
		}

		// Vincula o menu a localizacao "primary" do tema.
		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
		if ( empty( $locations['primary'] ) ) {
			$locations['primary'] = $menu->term_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		$this->report->log( sprintf( 'Menu principal: %d itens adicionados.', $this->report->counts['menu_items'] ) );
	}
}
