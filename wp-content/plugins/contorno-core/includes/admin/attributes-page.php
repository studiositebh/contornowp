<?php
/**
 * Tela "Contorno > Atributos das unidades".
 *
 * Catalogo central de destaques, diferenciais e modalidades. Nao existe rota
 * publica para estes registros: sao configuracao, guardada numa option (ver o
 * cabecalho de includes/attributes/catalog.php).
 *
 * Toda escrita passa por capability manage_options + nonce + saneamento. O
 * icone vem de uma allowlist (as chaves de contorno_icon_paths()); nenhum
 * campo aceita HTML ou SVG digitado.
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
			__( 'Atributos das unidades', 'contorno' ),
			__( 'Atributos das unidades', 'contorno' ),
			'manage_options',
			CONTORNO_ATTRIBUTES_PAGE,
			'contorno_render_attributes_page'
		);
	},
	20
);

/**
 * Tipo ativo na aba, sempre um dos tipos conhecidos.
 */
function contorno_attributes_current_type(): string {
	$types = contorno_attribute_types();
	$type  = isset( $_GET['tipo'] ) ? sanitize_key( wp_unslash( (string) $_GET['tipo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	return isset( $types[ $type ] ) ? $type : (string) array_key_first( $types );
}

/**
 * URL da aba de um tipo.
 */
function contorno_attributes_page_url( string $type, array $extra = array() ): string {
	return add_query_arg(
		array_merge( array( 'page' => CONTORNO_ATTRIBUTES_PAGE, 'tipo' => $type ), $extra ),
		admin_url( 'admin.php' )
	);
}

/**
 * Processa salvar/ativar/excluir. Devolve os avisos a exibir.
 *
 * @return array<int,array{type:string,text:string}>
 */
function contorno_attributes_handle_post(): array {
	if ( ! isset( $_POST['contorno_attributes_action'] ) ) {
		return array();
	}

	check_admin_referer( 'contorno_attributes' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'contorno' ) );
	}

	$action   = sanitize_key( wp_unslash( (string) $_POST['contorno_attributes_action'] ) );
	$type     = sanitize_key( wp_unslash( (string) ( $_POST['tipo'] ?? '' ) ) );
	$types    = contorno_attribute_types();
	$type     = isset( $types[ $type ] ) ? $type : (string) array_key_first( $types );
	$items    = contorno_attribute_catalog();
	$notices  = array();

	if ( 'create' === $action ) {
		$label = sanitize_text_field( wp_unslash( (string) ( $_POST['label'] ?? '' ) ) );

		if ( '' === trim( $label ) ) {
			return array( array( 'type' => 'error', 'text' => __( 'Informe o nome do atributo.', 'contorno' ) ) );
		}

		foreach ( $items as $item ) {
			if ( $item['type'] === $type && contorno_compare_key( (string) $item['label'] ) === contorno_compare_key( $label ) ) {
				return array( array( 'type' => 'error', 'text' => sprintf( /* translators: %s: nome */ __( 'Já existe um atributo chamado “%s” neste grupo.', 'contorno' ), $label ) ) );
			}
		}

		$orders = array_map( static fn ( array $item ): int => (int) $item['order'], array_filter( $items, static fn ( array $item ): bool => $item['type'] === $type ) );

		$items[] = contorno_attribute_sanitize_item(
			array(
				'key'     => contorno_attribute_unique_key( $label, $type, $items ),
				'type'    => $type,
				'label'   => $label,
				'icon'    => sanitize_key( wp_unslash( (string) ( $_POST['icon'] ?? 'sparkles' ) ) ),
				'order'   => ( array() === $orders ? 0 : max( $orders ) ) + 10,
				'active'  => true,
				'aliases' => array(),
			)
		);

		contorno_attribute_save_catalog( $items );

		return array( array( 'type' => 'success', 'text' => sprintf( /* translators: %s: nome */ __( '“%s” cadastrado.', 'contorno' ), $label ) ) );
	}

	if ( 'save' === $action ) {
		$rows = isset( $_POST['atributo'] ) && is_array( $_POST['atributo'] )
			? wp_unslash( $_POST['atributo'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- saneado abaixo.
			: array();

		$changed = 0;

		foreach ( $items as $index => $item ) {
			if ( $item['type'] !== $type ) {
				continue;
			}

			$row = $rows[ $item['key'] ] ?? null;

			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );

			// A CHAVE nunca muda ao renomear: e ela que as 70 unidades guardam.
			$items[ $index ] = contorno_attribute_sanitize_item(
				array(
					'key'     => $item['key'],
					'type'    => $item['type'],
					'label'   => '' !== trim( $label ) ? $label : $item['label'],
					'icon'    => (string) ( $row['icon'] ?? $item['icon'] ),
					'order'   => (int) ( $row['order'] ?? $item['order'] ),
					'active'  => ! empty( $row['active'] ),
					'aliases' => $item['aliases'],
				)
			);

			if ( $items[ $index ] !== $item ) {
				++$changed;
			}
		}

		contorno_attribute_save_catalog( $items );

		return array(
			array(
				'type' => 'success',
				'text' => $changed > 0
					/* translators: %d: quantidade */
					? sprintf( _n( '%d atributo atualizado.', '%d atributos atualizados.', $changed, 'contorno' ), $changed )
					: __( 'Nada para atualizar.', 'contorno' ),
			),
		);
	}

	if ( 'delete' === $action ) {
		$key   = sanitize_title( wp_unslash( (string) ( $_POST['key'] ?? '' ) ) );
		$usage = contorno_attribute_usage_ids( $type, $key );

		if ( array() !== $usage && empty( $_POST['confirm'] ) ) {
			return array(
				array(
					'type' => 'error',
					/* translators: %d: quantidade de unidades */
					'text' => sprintf( __( 'Este atributo está sendo utilizado em %d unidades. Desative-o ou confirme a exclusão na linha dele.', 'contorno' ), count( $usage ) ),
				),
			);
		}

		$items = array_values( array_filter( $items, static fn ( array $item ): bool => ! ( $item['type'] === $type && $item['key'] === $key ) ) );
		contorno_attribute_save_catalog( $items );

		return array( array( 'type' => 'success', 'text' => __( 'Atributo excluído do catálogo.', 'contorno' ) ) );
	}

	return $notices;
}

/**
 * Seletor visual de icone: radios com o proprio SVG como rotulo.
 */
function contorno_attributes_icon_picker( string $input_name, string $current ): void {
	$icons = contorno_attribute_icon_choices();
	$id    = 'picker-' . md5( $input_name );

	echo '<div class="contorno-icon-picker" data-contorno-icon-picker>';
	printf(
		'<button type="button" class="button contorno-icon-picker__toggle" data-contorno-icon-toggle aria-expanded="false" aria-controls="%s"><span class="contorno-icon-picker__preview" data-contorno-icon-preview>%s</span> %s</button>',
		esc_attr( $id ),
		contorno_icon( $current ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html__( 'Escolher ícone', 'contorno' )
	);

	printf( '<div class="contorno-icon-picker__grid" id="%s" hidden>', esc_attr( $id ) );

	foreach ( $icons as $icon ) {
		printf(
			'<label class="contorno-icon-picker__option" title="%1$s"><input type="radio" name="%2$s" value="%3$s" %4$s />%5$s<span class="screen-reader-text">%1$s</span></label>',
			esc_attr( $icon ),
			esc_attr( $input_name ),
			esc_attr( $icon ),
			checked( $current, $icon, false ),
			contorno_icon( $icon ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	echo '</div></div>';
}

function contorno_render_attributes_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'contorno' ) );
	}

	$notices = contorno_attributes_handle_post();
	$type    = contorno_attributes_current_type();
	$types   = contorno_attribute_types();
	$items   = contorno_attributes_by_type( $type );
	?>
	<div class="wrap contorno-attributes-page">
		<h1><?php esc_html_e( 'Contorno — Atributos das unidades', 'contorno' ); ?></h1>

		<p class="description" style="max-width:820px">
			<?php esc_html_e( 'Catálogo central usado pelos checkboxes na edição de cada unidade. As unidades guardam a CHAVE do atributo: renomear aqui muda o texto em todas elas de uma vez, sem editar unidade por unidade.', 'contorno' ); ?>
		</p>

		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?>">
				<p><?php echo esc_html( $notice['text'] ); ?></p>
			</div>
		<?php endforeach; ?>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $types as $key => $definition ) : ?>
				<a href="<?php echo esc_url( contorno_attributes_page_url( (string) $key ) ); ?>"
					class="nav-tab <?php echo $key === $type ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( (string) $definition['label'] ); ?>
					<span class="contorno-attributes-page__count">(<?php echo (int) count( contorno_attributes_by_type( (string) $key ) ); ?>)</span>
				</a>
			<?php endforeach; ?>
		</h2>

		<p class="description">
			<?php echo esc_html( (string) $types[ $type ]['help'] ); ?>
			<?php esc_html_e( 'Arraste as linhas pelo punho à esquerda para definir a ordem e clique em Salvar.', 'contorno' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'contorno_attributes' ); ?>
			<input type="hidden" name="tipo" value="<?php echo esc_attr( $type ); ?>" />
			<input type="hidden" name="contorno_attributes_action" value="save" />

			<table class="widefat striped contorno-attributes-table">
				<thead>
					<tr>
						<th style="width:44px"><span class="screen-reader-text"><?php esc_html_e( 'Ordenar', 'contorno' ); ?></span></th>
						<th style="width:60px"><?php esc_html_e( 'Ícone', 'contorno' ); ?></th>
						<th><?php esc_html_e( 'Nome', 'contorno' ); ?></th>
						<th style="width:230px"><?php esc_html_e( 'Chave', 'contorno' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'Ativo', 'contorno' ); ?></th>
						<th style="width:220px"><?php esc_html_e( 'Uso', 'contorno' ); ?></th>
					</tr>
				</thead>
				<tbody data-contorno-sortable>
					<?php if ( array() === $items ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Nenhum atributo neste grupo ainda.', 'contorno' ); ?></td></tr>
					<?php endif; ?>
					<?php $position = 0; ?>

					<?php
					foreach ( $items as $item ) :
						$key   = (string) $item['key'];
						$usage = contorno_attribute_usage_ids( $type, $key );
						$field     = 'atributo[' . $key . ']';
						$position += 10;
						?>
						<tr draggable="true" data-contorno-sortable-row>
							<td class="contorno-sort">
								<?php
								/*
								 * A ordem e o LUGAR da linha na tabela: o handle arrasta e o
								 * JS renumera este hidden. Sem JS, a ordem atual e reenviada
								 * intacta — nunca embaralha por falta de script.
								 */
								?>
								<span class="contorno-sort__handle" data-contorno-sort-handle role="button" tabindex="0"
									aria-label="<?php echo esc_attr( sprintf( /* translators: %s: nome do atributo */ __( 'Reordenar %s — arraste, ou use as setas do teclado', 'contorno' ), (string) $item['label'] ) ); ?>">⠿</span>
								<input type="hidden" name="<?php echo esc_attr( $field . '[order]' ); ?>" value="<?php echo esc_attr( (string) $position ); ?>" data-contorno-sort-order />
							</td>
							<td><?php contorno_attributes_icon_picker( $field . '[icon]', (string) $item['icon'] ); ?></td>
							<td>
								<input type="text" class="large-text" name="<?php echo esc_attr( $field . '[label]' ); ?>" value="<?php echo esc_attr( (string) $item['label'] ); ?>" />
								<?php if ( array() !== (array) $item['aliases'] ) : ?>
									<p class="description">
										<?php esc_html_e( 'Também reconhece:', 'contorno' ); ?>
										<?php foreach ( (array) $item['aliases'] as $alias_index => $alias ) : ?>
											<?php echo $alias_index > 0 ? ', ' : ''; ?><code><?php echo esc_html( (string) $alias ); ?></code>
										<?php endforeach; ?>
									</p>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $key ); ?></code></td>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $field . '[active]' ); ?>" value="1" <?php checked( (bool) $item['active'], true ); ?> />
									<span class="screen-reader-text"><?php esc_html_e( 'Ativo', 'contorno' ); ?></span>
								</label>
							</td>
							<td>
								<?php if ( array() === $usage ) : ?>
									<em><?php esc_html_e( 'Nenhuma unidade', 'contorno' ); ?></em>
								<?php else : ?>
									<details>
										<summary>
											<?php
											printf(
												/* translators: %d: quantidade de unidades */
												esc_html( _n( 'Usado em %d unidade', 'Usado em %d unidades', count( $usage ), 'contorno' ) ),
												(int) count( $usage )
											);
											?>
										</summary>
										<ul class="contorno-attributes-table__usage">
											<?php foreach ( $usage as $unit_id ) : ?>
												<li><a href="<?php echo esc_url( (string) get_edit_post_link( $unit_id ) ); ?>"><?php echo esc_html( (string) get_the_title( $unit_id ) ); ?></a></li>
											<?php endforeach; ?>
										</ul>
									</details>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar alterações', 'contorno' ); ?></button></p>
		</form>

		<?php if ( array() !== $items ) : ?>
			<h2><?php esc_html_e( 'Excluir', 'contorno' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Prefira desativar: um atributo inativo deixa de ser oferecido para novas seleções, mas continua aparecendo nas unidades que já o usam.', 'contorno' ); ?></p>
			<form method="post" class="contorno-attributes-delete">
				<?php wp_nonce_field( 'contorno_attributes' ); ?>
				<input type="hidden" name="tipo" value="<?php echo esc_attr( $type ); ?>" />
				<input type="hidden" name="contorno_attributes_action" value="delete" />
				<select name="key">
					<?php foreach ( $items as $item ) : ?>
						<option value="<?php echo esc_attr( (string) $item['key'] ); ?>"><?php echo esc_html( (string) $item['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><input type="checkbox" name="confirm" value="1" /> <?php esc_html_e( 'Confirmo excluir mesmo se estiver em uso', 'contorno' ); ?></label>
				<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Excluir do catálogo', 'contorno' ); ?></button>
			</form>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Novo atributo', 'contorno' ); ?></h2>
		<form method="post" class="contorno-attributes-new">
			<?php wp_nonce_field( 'contorno_attributes' ); ?>
			<input type="hidden" name="tipo" value="<?php echo esc_attr( $type ); ?>" />
			<input type="hidden" name="contorno_attributes_action" value="create" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="contorno-new-label"><?php esc_html_e( 'Nome', 'contorno' ); ?></label></th>
					<td><input type="text" id="contorno-new-label" name="label" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Ícone', 'contorno' ); ?></th>
					<td><?php contorno_attributes_icon_picker( 'icon', 'sparkles' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Tipo', 'contorno' ); ?></th>
					<td><strong><?php echo esc_html( (string) $types[ $type ]['label'] ); ?></strong></td>
				</tr>
			</table>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Cadastrar', 'contorno' ); ?></button></p>
		</form>
	</div>
	<?php
}

/**
 * CSS/JS da tela do catalogo.
 */
add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ): void {
		if ( ! str_contains( $hook, CONTORNO_ATTRIBUTES_PAGE ) ) {
			return;
		}

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
