<?php
/**
 * Tela "Contorno > Migracao".
 *
 * Alternativa ao WP-CLI para hospedagens sem acesso a linha de comando.
 * Mesmo importador, mesmo resultado.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CONTORNO_CORE_DIR . 'includes/migration/importer.php';

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			__( 'Contorno', 'contorno' ),
			__( 'Contorno', 'contorno' ),
			'manage_options',
			'contorno-migracao',
			'contorno_render_migration_page',
			'dashicons-superhero-alt',
			20
		);
	}
);

function contorno_render_migration_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissao.', 'contorno' ) );
	}

	$report = null;

	if ( isset( $_POST['contorno_migrate'] ) ) {
		check_admin_referer( 'contorno_migrate' );

		$dry_run   = ! empty( $_POST['contorno_dry_run'] );
		$force     = ! empty( $_POST['contorno_force'] );
		$migration = new Contorno_Migration( $force, $dry_run );
		$report    = $migration->run();
	}

	$dataset = Contorno_Migration::read_dataset();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Contorno — Migracao do conteudo', 'contorno' ); ?></h1>

		<p>
			<?php
			esc_html_e(
				'Importa unidades, CTNs, imagens e as paginas Home, /unidades e /ctn a partir do dataset exportado do site React. A operacao e idempotente: rodar de novo atualiza, nao duplica.',
				'contorno'
			);
			?>
		</p>

		<h2><?php esc_html_e( 'Estado atual', 'contorno' ); ?></h2>
		<table class="widefat striped" style="max-width:760px">
			<tbody>
				<tr>
					<th style="width:220px"><?php esc_html_e( 'Dataset', 'contorno' ); ?></th>
					<td>
						<?php if ( null === $dataset ) : ?>
							<strong style="color:#b32d2e"><?php esc_html_e( 'Ausente', 'contorno' ); ?></strong><br />
							<code><?php echo esc_html( Contorno_Migration::dataset_path() ); ?></code><br />
							<?php esc_html_e( 'Gere no repositorio React com: bun run scripts/export-wp-dataset.ts', 'contorno' ); ?>
						<?php else : ?>
							<?php
							printf(
								/* translators: 1: version, 2: date, 3: units, 4: ctns, 5: pages, 6: assets */
								esc_html__( 'v%1$s de %2$s — %3$d unidades, %4$d CTNs, %5$d paginas, %6$d imagens', 'contorno' ),
								esc_html( (string) ( $dataset['version'] ?? '?' ) ),
								esc_html( (string) ( $dataset['generatedAt'] ?? '?' ) ),
								count( (array) ( $dataset['units'] ?? array() ) ),
								count( (array) ( $dataset['ctns'] ?? array() ) ),
								count( (array) ( $dataset['pages'] ?? array() ) ),
								count( (array) ( $dataset['assets'] ?? array() ) )
							);
							?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'No banco', 'contorno' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: 1: units, 2: ctns */
							esc_html__( '%1$d unidades, %2$d CTNs', 'contorno' ),
							count( contorno_get_units() ),
							count( contorno_get_ctns() )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Paginas', 'contorno' ); ?></th>
					<td>
						<?php
						foreach ( array( 'home', 'unidades', 'ctn' ) as $slug ) {
							$page = get_page_by_path( $slug );
							printf(
								'<code>/%s</code>: %s<br />',
								esc_html( $slug ),
								$page instanceof WP_Post
									? '<a href="' . esc_url( (string) get_edit_post_link( $page->ID ) ) . '">' . esc_html__( 'editar', 'contorno' ) . '</a>'
									: '<span style="color:#b32d2e">' . esc_html__( 'ausente', 'contorno' ) . '</span>'
							);
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WPBakery Page Builder', 'contorno' ); ?></th>
					<td>
						<?php
						echo defined( 'WPB_VC_VERSION' )
							? esc_html( sprintf( /* translators: %s: version */ __( 'ativo (%s)', 'contorno' ), WPB_VC_VERSION ) )
							: '<strong style="color:#b32d2e">' . esc_html__( 'inativo', 'contorno' ) . '</strong>';
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Campos personalizados', 'contorno' ); ?></th>
					<td><?php echo esc_html( contorno_custom_fields_provider() ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Ultima migracao', 'contorno' ); ?></th>
					<td><?php echo esc_html( (string) get_option( 'contorno_migration_last_run', __( 'nunca', 'contorno' ) ) ); ?></td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Executar', 'contorno' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'contorno_migrate' ); ?>
			<p>
				<label>
					<input type="checkbox" name="contorno_dry_run" value="1" checked />
					<?php esc_html_e( 'Simular primeiro (nao grava nada)', 'contorno' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="contorno_force" value="1" />
					<?php esc_html_e( 'Sobrescrever paginas que ja foram editadas no painel', 'contorno' ); ?>
				</label>
			</p>
			<p>
				<button type="submit" name="contorno_migrate" value="1" class="button button-primary">
					<?php esc_html_e( 'Importar conteudo', 'contorno' ); ?>
				</button>
			</p>
		</form>

		<?php if ( $report instanceof Contorno_Migration_Report ) : ?>
			<h2><?php esc_html_e( 'Resultado', 'contorno' ); ?></h2>

			<?php if ( array() !== $report->warnings ) : ?>
				<div class="notice notice-warning">
					<ul style="margin:8px 0 8px 20px;list-style:disc">
						<?php foreach ( $report->warnings as $warning ) : ?>
							<li><?php echo esc_html( $warning ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<pre style="padding:12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;white-space:pre-wrap"><?php
				echo esc_html( implode( "\n", $report->lines ) );
			?></pre>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Pela linha de comando', 'contorno' ); ?></h2>
		<pre style="padding:12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px">wp contorno status
wp contorno migrate --dry-run
wp contorno migrate</pre>
	</div>
	<?php
}
