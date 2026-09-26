<?php
/**
 * Tela "Contorno > Slider da Home".
 *
 * Substitui o uso do Hero (WPBakery, com overlay/escurecimento) quando o
 * cliente so precisa de banners rotativos simples: imagem desktop/mobile,
 * link opcional e ordem, geridos aqui — nao no editor de pagina. Insira o
 * shortcode [contorno_home_slider] na Home (WPBakery > CONTORNO — Slider da
 * Home) onde os banners devem aparecer.
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
			__( 'Slider da Home', 'contorno' ),
			__( 'Slider da Home', 'contorno' ),
			'manage_options',
			CONTORNO_HOME_SLIDER_PAGE,
			'contorno_render_home_slider_page'
		);
	},
	20
);

/**
 * Processa salvar/excluir. Devolve os avisos a exibir.
 *
 * @return array<int,array{type:string,text:string}>
 */
function contorno_home_slider_handle_post(): array {
	if ( ! isset( $_POST['contorno_home_slider_action'] ) ) {
		return array();
	}

	check_admin_referer( 'contorno_home_slider' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'contorno' ) );
	}

	$action = sanitize_key( wp_unslash( (string) $_POST['contorno_home_slider_action'] ) );
	$data   = contorno_home_slider_data();

	if ( 'save' === $action ) {
		$rows = isset( $_POST['slide'] ) && is_array( $_POST['slide'] )
			? wp_unslash( $_POST['slide'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- saneado em contorno_home_slider_sanitize_slide().
			: array();

		$slides = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$slides[] = $row;
			}
		}

		$settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? wp_unslash( $_POST['settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- saneado em contorno_home_slider_sanitize_settings().
			: array();

		contorno_home_slider_save( $slides, $settings );

		return array( array( 'type' => 'success', 'text' => __( 'Slider da Home atualizado.', 'contorno' ) ) );
	}

	if ( 'create' === $action ) {
		$orders   = array_map( static fn ( array $slide ): int => (int) $slide['order'], $data['slides'] );
		$slides   = $data['slides'];
		$slides[] = contorno_home_slider_sanitize_slide(
			array(
				'order'  => ( array() === $orders ? 0 : max( $orders ) ) + 10,
				'active' => true,
			)
		);

		contorno_home_slider_save( $slides, $data['settings'] );

		return array( array( 'type' => 'success', 'text' => __( 'Slide adicionado — selecione a imagem desktop abaixo.', 'contorno' ) ) );
	}

	if ( 'delete' === $action ) {
		$index = absint( $_POST['index'] ?? 0 );

		unset( $data['slides'][ $index ] );

		contorno_home_slider_save( array_values( $data['slides'] ), $data['settings'] );

		return array( array( 'type' => 'success', 'text' => __( 'Slide removido.', 'contorno' ) ) );
	}

	return array();
}

/**
 * Seletor de imagem (Biblioteca de Mídia) reaproveitando o mesmo componente
 * JS/CSS do metabox de unidade (contorno-media / data-contorno-media, ligado
 * globalmente por bindMediaPicker() em admin-fields.js).
 */
function contorno_home_slider_media_picker( string $input_name, int $attachment_id, string $label ): void {
	$preview = $attachment_id > 0 ? contorno_resolve_media( $attachment_id, 'medium' ) : '';

	echo '<div class="contorno-media contorno-media--compact" data-contorno-media>';
	printf( '<span class="contorno-field__label">%s</span>', esc_html( $label ) );
	printf(
		'<input type="hidden" name="%s" value="%s" data-contorno-media-input />',
		esc_attr( $input_name ),
		esc_attr( $attachment_id > 0 ? (string) $attachment_id : '' )
	);
	echo '<div class="contorno-media__preview-wrap contorno-media__preview-wrap--small">';
	printf(
		'<img src="%s" alt="" class="contorno-media__preview" data-contorno-media-preview %s />',
		esc_url( $preview ),
		'' === $preview ? 'hidden' : ''
	);
	printf(
		'<p class="contorno-media__placeholder" data-contorno-media-placeholder %s>%s</p>',
		'' !== $preview ? 'hidden' : '',
		esc_html__( 'Nenhuma imagem.', 'contorno' )
	);
	echo '</div>';
	echo '<div class="contorno-media__actions">';
	printf(
		'<button type="button" class="button" data-contorno-media-pick>%s</button>',
		esc_html( $attachment_id > 0 ? __( 'Trocar', 'contorno' ) : __( 'Selecionar imagem', 'contorno' ) )
	);
	printf(
		'<button type="button" class="button button-link-delete" data-contorno-media-remove %s>%s</button>',
		$attachment_id > 0 ? '' : 'hidden',
		esc_html__( 'Remover', 'contorno' )
	);
	echo '</div>';
	echo '</div>';
}

function contorno_render_home_slider_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'contorno' ) );
	}

	$notices = contorno_home_slider_handle_post();
	$data    = contorno_home_slider_data();
	?>
	<div class="wrap contorno-attributes-page">
		<h1><?php esc_html_e( 'Slider da Home', 'contorno' ); ?></h1>

		<p class="description" style="max-width:820px">
			<?php
			esc_html_e(
				'Banners simples e rotativos, sem escurecimento nem texto sobreposto. Depois de salvar aqui, insira o bloco "CONTORNO — Slider da Home" no editor da página onde ele deve aparecer.',
				'contorno'
			);
			?>
		</p>

		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?>">
				<p><?php echo esc_html( $notice['text'] ); ?></p>
			</div>
		<?php endforeach; ?>

		<form method="post">
			<?php wp_nonce_field( 'contorno_home_slider' ); ?>
			<input type="hidden" name="contorno_home_slider_action" value="save" />

			<h2><?php esc_html_e( 'Configurações', 'contorno' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="contorno-slider-interval"><?php esc_html_e( 'Tempo entre slides', 'contorno' ); ?></label></th>
					<td>
						<input type="number" id="contorno-slider-interval" name="settings[interval]" min="2000" max="20000" step="500" value="<?php echo esc_attr( (string) $data['settings']['interval'] ); ?>" class="small-text" />
						<span class="description"><?php esc_html_e( 'milissegundos (2000 = 2s). Padrão: 6000.', 'contorno' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="contorno-slider-speed"><?php esc_html_e( 'Velocidade da transição', 'contorno' ); ?></label></th>
					<td>
						<input type="number" id="contorno-slider-speed" name="settings[speed]" min="150" max="2000" step="50" value="<?php echo esc_attr( (string) $data['settings']['speed'] ); ?>" class="small-text" />
						<span class="description"><?php esc_html_e( 'milissegundos do fade entre um slide e outro. Padrão: 600.', 'contorno' ); ?></span>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Slides', 'contorno' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Arraste pelo punho à esquerda para reordenar. Sem imagem mobile, a versão desktop é usada em qualquer tamanho de tela.', 'contorno' ); ?></p>

			<table class="widefat striped contorno-attributes-table contorno-home-slider-table">
				<thead>
					<tr>
						<th class="contorno-attributes-table__col--sort"><span class="screen-reader-text"><?php esc_html_e( 'Ordenar', 'contorno' ); ?></span></th>
						<th><?php esc_html_e( 'Imagens', 'contorno' ); ?></th>
						<th><?php esc_html_e( 'Link (opcional)', 'contorno' ); ?></th>
						<th class="contorno-attributes-table__col--active"><?php esc_html_e( 'Ativo', 'contorno' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Excluir', 'contorno' ); ?></span></th>
					</tr>
				</thead>
				<tbody data-contorno-sortable>
					<?php if ( array() === $data['slides'] ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'Nenhum slide ainda — use "Adicionar slide" abaixo.', 'contorno' ); ?></td></tr>
					<?php endif; ?>

					<?php foreach ( $data['slides'] as $index => $slide ) : ?>
						<tr draggable="true" data-contorno-sortable-row>
							<td class="contorno-sort">
								<span class="contorno-sort__handle" data-contorno-sort-handle role="button" tabindex="0"
									aria-label="<?php esc_attr_e( 'Reordenar — arraste, ou use as setas do teclado', 'contorno' ); ?>">⠿</span>
								<input type="hidden" name="slide[<?php echo (int) $index; ?>][order]" value="<?php echo esc_attr( (string) ( ( $index + 1 ) * 10 ) ); ?>" data-contorno-sort-order />
							</td>
							<td class="contorno-home-slider__images">
								<?php contorno_home_slider_media_picker( 'slide[' . $index . '][image_desktop_id]', (int) $slide['image_desktop_id'], __( 'Desktop', 'contorno' ) ); ?>
								<?php contorno_home_slider_media_picker( 'slide[' . $index . '][image_mobile_id]', (int) $slide['image_mobile_id'], __( 'Mobile (opcional)', 'contorno' ) ); ?>
							</td>
							<td>
								<input type="url" class="large-text" name="slide[<?php echo (int) $index; ?>][link_url]" value="<?php echo esc_attr( (string) $slide['link_url'] ); ?>" placeholder="https://…" />
								<p class="description" style="margin-bottom:6px"><?php esc_html_e( 'Abrir em:', 'contorno' ); ?></p>
								<?php
								contorno_render_segmented(
									'slide-' . $index . '-target',
									'slide[' . $index . '][link_target]',
									array(
										'self'  => __( 'Mesma aba', 'contorno' ),
										'blank' => __( 'Nova aba', 'contorno' ),
									),
									(string) $slide['link_target']
								);
								?>
							</td>
							<td class="contorno-attributes-table__col--active">
								<label>
									<input type="checkbox" name="slide[<?php echo (int) $index; ?>][active]" value="1" <?php checked( (bool) $slide['active'], true ); ?> />
									<span class="screen-reader-text"><?php esc_html_e( 'Ativo', 'contorno' ); ?></span>
								</label>
							</td>
							<td>
								<button type="submit" form="contorno-home-slider-delete-<?php echo (int) $index; ?>" class="button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Excluir este slide?', 'contorno' ) ); ?>');">
									<?php esc_html_e( 'Excluir', 'contorno' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar alterações', 'contorno' ); ?></button></p>
		</form>

		<form method="post">
			<?php wp_nonce_field( 'contorno_home_slider' ); ?>
			<input type="hidden" name="contorno_home_slider_action" value="create" />
			<p><button type="submit" class="button"><?php esc_html_e( 'Adicionar slide', 'contorno' ); ?></button></p>
		</form>

		<?php foreach ( $data['slides'] as $index => $slide ) : ?>
			<form method="post" id="contorno-home-slider-delete-<?php echo (int) $index; ?>" class="contorno-home-slider-delete-form">
				<?php wp_nonce_field( 'contorno_home_slider' ); ?>
				<input type="hidden" name="contorno_home_slider_action" value="delete" />
				<input type="hidden" name="index" value="<?php echo (int) $index; ?>" />
			</form>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * CSS/JS da tela — mesmos assets do catálogo de atributos (media picker,
 * sortable, segmented) e nada além disso.
 */
add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ): void {
		if ( ! str_contains( $hook, CONTORNO_HOME_SLIDER_PAGE ) ) {
			return;
		}

		// Mesmo bug do catalogo de atributos: sem isto, wp.media nunca
		// carrega aqui e os botoes de imagem nao abrem a Biblioteca de Midia.
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
