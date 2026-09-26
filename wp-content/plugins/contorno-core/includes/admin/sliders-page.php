<?php
/**
 * Tela "Contorno > Slider Contorno".
 *
 * Lista de sliders (Home, CTN, campanhas...) + editor de cada um (abas
 * Slides / Configurações). Cada slider tem id estavel, nome, ativo/inativo,
 * slides e configuracoes proprias — nada de configuracao global unica.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'contorno-migracao',
			__( 'Slider Contorno', 'contorno' ),
			__( 'Slider Contorno', 'contorno' ),
			'manage_options',
			CONTORNO_SLIDERS_PAGE,
			'contorno_render_sliders_page'
		);
	},
	20
);

function contorno_sliders_page_url( array $extra = array() ): string {
	return add_query_arg(
		array_merge( array( 'page' => CONTORNO_SLIDERS_PAGE ), $extra ),
		admin_url( 'admin.php' )
	);
}

/**
 * @return array<int,array{type:string,text:string}>
 */
function contorno_sliders_handle_post(): array {
	if ( ! isset( $_POST['contorno_sliders_action'] ) ) {
		return array();
	}

	check_admin_referer( 'contorno_sliders' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'contorno' ) );
	}

	$action  = sanitize_key( wp_unslash( (string) $_POST['contorno_sliders_action'] ) );
	$sliders = contorno_sliders_data();

	if ( 'create' === $action ) {
		$name = sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) );

		if ( '' === trim( $name ) ) {
			return array( array( 'type' => 'error', 'text' => __( 'Informe o nome do slider.', 'contorno' ) ) );
		}

		$id             = contorno_slider_unique_id( $name, $sliders );
		$sliders[ $id ] = contorno_slider_sanitize(
			array(
				'id'       => $id,
				'name'     => $name,
				'active'   => true,
				'settings' => contorno_slider_default_settings(),
				'slides'   => array(),
			)
		);
		contorno_sliders_save( $sliders );

		return array(
			array(
				'type' => 'success',
				/* translators: %s: nome do slider */
				'text' => sprintf( __( 'Slider “%s” criado. Clique em Editar para adicionar slides.', 'contorno' ), $name ),
			),
		);
	}

	if ( 'duplicate' === $action ) {
		$source_id = sanitize_key( (string) ( $_POST['id'] ?? '' ) );

		if ( ! isset( $sliders[ $source_id ] ) ) {
			return array( array( 'type' => 'error', 'text' => __( 'Slider não encontrado.', 'contorno' ) ) );
		}

		$source           = $sliders[ $source_id ];
		/* translators: %s: nome do slider original */
		$name             = sprintf( __( '%s (cópia)', 'contorno' ), (string) $source['name'] );
		$id               = contorno_slider_unique_id( $name, $sliders );
		$copy             = $source;
		$copy['id']       = $id;
		$copy['name']     = $name;
		$sliders[ $id ]   = contorno_slider_sanitize( $copy );
		contorno_sliders_save( $sliders );

		return array(
			array(
				'type' => 'success',
				/* translators: 1: nome original 2: nome da copia */
				'text' => sprintf( __( '“%1$s” duplicado como “%2$s”.', 'contorno' ), (string) $source['name'], $name ),
			),
		);
	}

	if ( 'delete' === $action ) {
		$id    = sanitize_key( (string) ( $_POST['id'] ?? '' ) );
		$usage = contorno_slider_usage_count( $id );

		if ( $usage > 0 && empty( $_POST['confirm'] ) ) {
			return array(
				array(
					'type' => 'error',
					/* translators: %d: quantidade de paginas */
					'text' => sprintf( __( 'Este slider está inserido em %d página(s). Marque "Confirmo excluir mesmo em uso" e tente de novo.', 'contorno' ), $usage ),
				),
			);
		}

		unset( $sliders[ $id ] );
		contorno_sliders_save( $sliders );

		return array( array( 'type' => 'success', 'text' => __( 'Slider excluído.', 'contorno' ) ) );
	}

	if ( 'save' === $action ) {
		$id = sanitize_key( (string) ( $_POST['slider_id'] ?? '' ) );

		if ( ! isset( $sliders[ $id ] ) ) {
			return array( array( 'type' => 'error', 'text' => __( 'Slider não encontrado.', 'contorno' ) ) );
		}

		$rows = isset( $_POST['slide'] ) && is_array( $_POST['slide'] )
			? wp_unslash( $_POST['slide'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- saneado em contorno_slider_sanitize_slide().
			: array();

		$slides = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$slides[] = $row;
			}
		}

		$settings_raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? wp_unslash( $_POST['settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- saneado em contorno_slider_sanitize_settings().
			: array();

		// A UI mostra segundos (amigavel); internamente continua em ms.
		$settings = array(
			'interval'       => (int) round( ( (float) ( $settings_raw['interval_seconds'] ?? 6 ) ) * 1000 ),
			'speed'          => (int) round( ( (float) ( $settings_raw['speed_seconds'] ?? 0.6 ) ) * 1000 ),
			'autoplay'       => 'yes' === ( $settings_raw['autoplay'] ?? 'yes' ),
			'show_dots'      => 'yes' === ( $settings_raw['show_dots'] ?? 'yes' ),
			'pause_on_hover' => 'yes' === ( $settings_raw['pause_on_hover'] ?? 'yes' ),
		);

		$sliders[ $id ] = contorno_slider_sanitize(
			array(
				'id'       => $id,
				'name'     => sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? $sliders[ $id ]['name'] ) ) ),
				'active'   => ! empty( $_POST['active'] ),
				'settings' => $settings,
				'slides'   => $slides,
			)
		);
		contorno_sliders_save( $sliders );

		return array( array( 'type' => 'success', 'text' => __( 'Slider atualizado.', 'contorno' ) ) );
	}

	return array();
}

/**
 * @param array<string,mixed> $slide
 */
function contorno_render_slider_slide_row( array $slide, string $index ): void {
	$field = static fn ( string $sub ): string => 'slide[' . $index . '][' . $sub . ']';
	?>
	<div class="contorno-slider-slide-row" draggable="true" data-contorno-sortable-row>
		<div class="contorno-slider-slide-row__handle">
			<span class="contorno-sort__handle" data-contorno-sort-handle role="button" tabindex="0"
				aria-label="<?php esc_attr_e( 'Reordenar — arraste, ou use as setas do teclado', 'contorno' ); ?>">⠿</span>
			<input type="hidden" name="<?php echo esc_attr( $field( 'order' ) ); ?>" value="<?php echo esc_attr( (string) ( $slide['order'] ?? 0 ) ); ?>" data-contorno-sort-order />
		</div>

		<div class="contorno-slider-slide-row__grid">
			<div class="contorno-slider-slide-row__field">
				<span class="contorno-field__label"><?php esc_html_e( 'Desktop', 'contorno' ); ?></span>
				<?php
				$desktop_id = (int) ( $slide['image_desktop_id'] ?? 0 );
				contorno_render_media_picker(
					$field( 'image_desktop_id' ),
					$desktop_id > 0 ? (string) $desktop_id : '',
					$desktop_id > 0 ? (string) wp_get_attachment_image_url( $desktop_id, 'medium' ) : ''
				);
				?>
			</div>

			<div class="contorno-slider-slide-row__field">
				<span class="contorno-field__label"><?php esc_html_e( 'Mobile (opcional)', 'contorno' ); ?></span>
				<?php
				$mobile_id = (int) ( $slide['image_mobile_id'] ?? 0 );
				contorno_render_media_picker(
					$field( 'image_mobile_id' ),
					$mobile_id > 0 ? (string) $mobile_id : '',
					$mobile_id > 0 ? (string) wp_get_attachment_image_url( $mobile_id, 'medium' ) : '',
					array( 'empty_label' => __( 'Sem imagem — usará a desktop', 'contorno' ) )
				);
				?>
			</div>

			<div class="contorno-slider-slide-row__field contorno-slider-slide-row__field--link">
				<span class="contorno-field__label"><?php esc_html_e( 'Link (opcional)', 'contorno' ); ?></span>
				<input type="url" class="large-text" name="<?php echo esc_attr( $field( 'link_url' ) ); ?>" value="<?php echo esc_attr( (string) ( $slide['link_url'] ?? '' ) ); ?>" placeholder="https://…" />
			</div>

			<div class="contorno-slider-slide-row__field">
				<span class="contorno-field__label"><?php esc_html_e( 'Abrir em', 'contorno' ); ?></span>
				<?php
				contorno_render_segmented(
					'contorno-slide-' . $index . '-target',
					$field( 'link_target' ),
					array(
						'self'  => __( 'Mesma aba', 'contorno' ),
						'blank' => __( 'Nova aba', 'contorno' ),
					),
					(string) ( $slide['link_target'] ?? 'self' )
				);
				?>
			</div>

			<div class="contorno-slider-slide-row__field">
				<span class="contorno-field__label"><?php esc_html_e( 'Status', 'contorno' ); ?></span>
				<?php
				contorno_render_segmented(
					'contorno-slide-' . $index . '-active',
					$field( 'active' ),
					array(
						'1' => __( 'Ativo', 'contorno' ),
						''  => __( 'Inativo', 'contorno' ),
					),
					! empty( $slide['active'] ) ? '1' : ''
				);
				?>
			</div>
		</div>

		<button type="button" class="contorno-slider-slide-row__remove" data-contorno-slider-slide-remove data-confirm="<?php echo esc_attr__( 'Excluir este slide? Essa ação não pode ser desfeita.', 'contorno' ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
			<?php esc_html_e( 'Excluir slide', 'contorno' ); ?>
		</button>
	</div>
	<?php
}

function contorno_render_slider_editor( string $id ): void {
	$slider = contorno_slider_get( $id );

	if ( null === $slider ) {
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Slider não encontrado.', 'contorno' ) );
		return;
	}

	$tab      = isset( $_GET['tab'] ) && 'settings' === $_GET['tab'] ? 'settings' : 'slides'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$settings = $slider['settings'];
	?>
	<p><a href="<?php echo esc_url( contorno_sliders_page_url() ); ?>">&larr; <?php esc_html_e( 'Todos os sliders', 'contorno' ); ?></a></p>

	<form method="post">
		<?php wp_nonce_field( 'contorno_sliders' ); ?>
		<input type="hidden" name="contorno_sliders_action" value="save" />
		<input type="hidden" name="slider_id" value="<?php echo esc_attr( $id ); ?>" />

		<table class="form-table" role="presentation" style="max-width:640px">
			<tr>
				<th scope="row"><label for="contorno-slider-name"><?php esc_html_e( 'Nome', 'contorno' ); ?></label></th>
				<td><input type="text" id="contorno-slider-name" name="name" class="regular-text" value="<?php echo esc_attr( (string) $slider['name'] ); ?>" required /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'contorno' ); ?></th>
				<td>
					<?php
					contorno_render_segmented(
						'contorno-slider-active',
						'active',
						array(
							'1' => __( 'Ativo', 'contorno' ),
							''  => __( 'Inativo', 'contorno' ),
						),
						! empty( $slider['active'] ) ? '1' : ''
					);
					?>
				</td>
			</tr>
		</table>

		<div class="nav-tab-wrapper" data-contorno-tabs>
			<button type="button" class="nav-tab<?php echo 'slides' === $tab ? ' nav-tab-active is-active' : ''; ?>" data-contorno-tab="slides"><?php esc_html_e( 'Slides', 'contorno' ); ?></button>
			<button type="button" class="nav-tab<?php echo 'settings' === $tab ? ' nav-tab-active is-active' : ''; ?>" data-contorno-tab="settings"><?php esc_html_e( 'Configurações', 'contorno' ); ?></button>
		</div>

		<div data-contorno-tab-panel="slides" <?php echo 'slides' === $tab ? '' : 'hidden'; ?>>
			<p class="description"><?php esc_html_e( 'Arraste pelo punho à esquerda para reordenar. O slide é imagem + link, sem overlay, título ou botão.', 'contorno' ); ?></p>

			<div class="contorno-slider-slides" data-contorno-slider-slides>
				<div class="contorno-slider-slides__rows" data-contorno-sortable data-contorno-slider-slides-rows>
					<?php if ( array() === $slider['slides'] ) : ?>
						<p class="description"><?php esc_html_e( 'Nenhum slide ainda — use "Adicionar slide" abaixo.', 'contorno' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $slider['slides'] as $index => $slide ) : ?>
						<?php contorno_render_slider_slide_row( $slide, (string) $index ); ?>
					<?php endforeach; ?>
				</div>

				<p><button type="button" class="button" data-contorno-slider-slides-add><?php esc_html_e( 'Adicionar slide', 'contorno' ); ?></button></p>

				<script type="text/html" data-contorno-slider-slides-template>
					<?php contorno_render_slider_slide_row( array( 'active' => true ), '__INDEX__' ); ?>
				</script>
			</div>
		</div>

		<div data-contorno-tab-panel="settings" <?php echo 'settings' === $tab ? '' : 'hidden'; ?>>
			<table class="form-table" role="presentation" style="max-width:640px">
				<tr>
					<th scope="row"><label for="contorno-slider-interval"><?php esc_html_e( 'Tempo entre slides', 'contorno' ); ?></label></th>
					<td>
						<input type="number" id="contorno-slider-interval" name="settings[interval_seconds]" min="2" max="20" step="0.5" value="<?php echo esc_attr( (string) ( $settings['interval'] / 1000 ) ); ?>" class="small-text" />
						<?php esc_html_e( 'segundos', 'contorno' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="contorno-slider-speed"><?php esc_html_e( 'Velocidade da transição', 'contorno' ); ?></label></th>
					<td>
						<input type="number" id="contorno-slider-speed" name="settings[speed_seconds]" min="0.1" max="3" step="0.1" value="<?php echo esc_attr( (string) ( $settings['speed'] / 1000 ) ); ?>" class="small-text" />
						<?php esc_html_e( 'segundos', 'contorno' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Autoplay', 'contorno' ); ?></th>
					<td>
						<?php
						contorno_render_segmented(
							'contorno-slider-autoplay',
							'settings[autoplay]',
							array(
								'yes' => __( 'Sim', 'contorno' ),
								'no'  => __( 'Não', 'contorno' ),
							),
							! empty( $settings['autoplay'] ) ? 'yes' : 'no'
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mostrar indicadores (dots)', 'contorno' ); ?></th>
					<td>
						<?php
						contorno_render_segmented(
							'contorno-slider-dots',
							'settings[show_dots]',
							array(
								'yes' => __( 'Sim', 'contorno' ),
								'no'  => __( 'Não', 'contorno' ),
							),
							! empty( $settings['show_dots'] ) ? 'yes' : 'no'
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Pausar no hover', 'contorno' ); ?></th>
					<td>
						<?php
						contorno_render_segmented(
							'contorno-slider-pause',
							'settings[pause_on_hover]',
							array(
								'yes' => __( 'Sim', 'contorno' ),
								'no'  => __( 'Não', 'contorno' ),
							),
							! empty( $settings['pause_on_hover'] ) ? 'yes' : 'no'
						);
						?>
					</td>
				</tr>
			</table>
		</div>

		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar alterações', 'contorno' ); ?></button></p>
	</form>
	<?php
}

function contorno_render_sliders_list(): void {
	$sliders = contorno_sliders_data();
	?>
	<h2><?php esc_html_e( 'Novo slider', 'contorno' ); ?></h2>
	<form method="post" class="contorno-sliders-new">
		<?php wp_nonce_field( 'contorno_sliders' ); ?>
		<input type="hidden" name="contorno_sliders_action" value="create" />
		<input type="text" name="name" class="regular-text" placeholder="<?php esc_attr_e( 'Ex.: Slider CTN, Slider Campanha Outubro…', 'contorno' ); ?>" required />
		<button type="submit" class="button button-primary"><?php esc_html_e( '+ Novo slider', 'contorno' ); ?></button>
	</form>

	<h2><?php esc_html_e( 'Sliders', 'contorno' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Nome', 'contorno' ); ?></th>
				<th><?php esc_html_e( 'Slides', 'contorno' ); ?></th>
				<th><?php esc_html_e( 'Status', 'contorno' ); ?></th>
				<th><?php esc_html_e( 'Ações', 'contorno' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $sliders ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'Nenhum slider ainda.', 'contorno' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $sliders as $slider ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $slider['name'] ); ?> <code style="opacity:.6"><?php echo esc_html( (string) $slider['id'] ); ?></code></td>
					<td>
						<?php
						printf(
							/* translators: %d: quantidade de slides */
							esc_html( _n( '%d slide', '%d slides', count( $slider['slides'] ), 'contorno' ) ),
							(int) count( $slider['slides'] )
						);
						?>
					</td>
					<td><?php echo $slider['active'] ? esc_html__( 'Ativo', 'contorno' ) : esc_html__( 'Inativo', 'contorno' ); ?></td>
					<td>
						<a href="<?php echo esc_url( contorno_sliders_page_url( array( 'slider' => $slider['id'] ) ) ); ?>"><?php esc_html_e( 'Editar', 'contorno' ); ?></a>

						<form method="post" style="display:inline-block;margin-left:10px">
							<?php wp_nonce_field( 'contorno_sliders' ); ?>
							<input type="hidden" name="contorno_sliders_action" value="duplicate" />
							<input type="hidden" name="id" value="<?php echo esc_attr( (string) $slider['id'] ); ?>" />
							<button type="submit" class="button-link"><?php esc_html_e( 'Duplicar', 'contorno' ); ?></button>
						</form>

						<form method="post" style="display:inline-block;margin-left:10px">
							<?php wp_nonce_field( 'contorno_sliders' ); ?>
							<input type="hidden" name="contorno_sliders_action" value="delete" />
							<input type="hidden" name="id" value="<?php echo esc_attr( (string) $slider['id'] ); ?>" />
							<label><input type="checkbox" name="confirm" value="1" /> <?php esc_html_e( 'confirmo excluir mesmo em uso', 'contorno' ); ?></label>
							<button type="submit" class="button-link-delete"><?php esc_html_e( 'Excluir', 'contorno' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function contorno_render_sliders_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'contorno' ) );
	}

	$notices = contorno_sliders_handle_post();
	$slider_id = isset( $_GET['slider'] ) ? sanitize_key( wp_unslash( (string) $_GET['slider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap contorno-attributes-page">
		<h1><?php esc_html_e( 'Slider Contorno', 'contorno' ); ?></h1>

		<p class="description" style="max-width:820px">
			<?php esc_html_e( 'Banners rotativos simples (imagem + link, sem escurecimento nem texto sobreposto). Crie quantos sliders quiser e insira cada um onde precisar via o bloco "CONTORNO — Slider" no WPBakery.', 'contorno' ); ?>
		</p>

		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?>">
				<p><?php echo esc_html( $notice['text'] ); ?></p>
			</div>
		<?php endforeach; ?>

		<?php if ( '' !== $slider_id ) : ?>
			<?php contorno_render_slider_editor( $slider_id ); ?>
		<?php else : ?>
			<?php contorno_render_sliders_list(); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * CSS/JS da tela.
 */
add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ): void {
		if ( ! str_contains( $hook, CONTORNO_SLIDERS_PAGE ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'contorno-admin-fields',
			contorno_core_url( 'assets/css/admin-fields.css' ),
			array(),
			contorno_core_asset_version( 'assets/css/admin-fields.css' )
		);

		wp_enqueue_script(
			'contorno-admin-fields',
			contorno_core_url( 'assets/js/admin-fields.js' ),
			array(),
			contorno_core_asset_version( 'assets/js/admin-fields.js' ),
			true
		);
	}
);
