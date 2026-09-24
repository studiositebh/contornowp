<?php
/**
 * UI de administracao dos campos estruturados, gerada a partir do esquema.
 *
 * Implementacao nativa — sem dependencia de plugin de custom fields.
 * Se ACF for adotado depois, basta desativar estes metaboxes com o filtro
 * `contorno_render_native_metaboxes`; a leitura continua funcionando pela
 * ponte em inc/meta/fields.php.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function contorno_should_render_native_metaboxes(): bool {
	return (bool) apply_filters( 'contorno_render_native_metaboxes', true );
}

/**
 * Ordem de exibicao das caixas no editor.
 *
 * O esquema (registry.php) e organizado por dominio; aqui definimos a ordem
 * de LEITURA para quem administra a academia no dia a dia: primeiro o que
 * muda com frequencia (dados gerais e status comercial), depois cadastro,
 * conteudo, comercial, integracoes e por fim SEO.
 *
 * Grupos ausentes desta lista entram no fim, na ordem do esquema.
 *
 * @return string[]
 */
function contorno_metabox_order( string $post_type ): array {
	$order = array(
		CONTORNO_CPT_UNIT => array(
			'identidade',
			'status',
			'localizacao',
			'midia',
			'conteudo',
			'planos',
			'aulas',
			'integracao',
			'editorial',
			'seo',
		),
		CONTORNO_CPT_CTN => array(
			'identidade',
			'hero',
			'puv',
			'sobre',
			'estrutura',
			'videos',
			'localizacao',
			'planos',
			'aulas',
			'cta',
			'editorial',
			'seo',
		),
	);

	return (array) apply_filters( 'contorno_metabox_order', $order[ $post_type ] ?? array(), $post_type );
}

add_action(
	'add_meta_boxes',
	static function ( string $post_type ): void {
		if ( ! contorno_should_render_native_metaboxes() ) {
			return;
		}

		$schema = contorno_field_schema();

		if ( ! isset( $schema[ $post_type ] ) ) {
			return;
		}

		$groups = $schema[ $post_type ];
		$order  = contorno_metabox_order( $post_type );

		// Ordem preferida primeiro; o que sobrar mantem a ordem do esquema.
		$keys = array_values( array_filter( $order, static fn ( string $key ): bool => isset( $groups[ $key ] ) ) );
		foreach ( array_keys( $groups ) as $key ) {
			if ( ! in_array( $key, $keys, true ) ) {
				$keys[] = (string) $key;
			}
		}

		foreach ( $keys as $index => $group_key ) {
			$group = $groups[ $group_key ];

			add_meta_box(
				'contorno-' . $group_key,
				(string) ( $group['label'] ?? $group_key ),
				static function ( WP_Post $post ) use ( $group ): void {
					contorno_render_metabox_group( $post, $group );
				},
				$post_type,
				'normal',
				// 'high' garante que as caixas do Contorno fiquem acima das de
				// terceiros (WPBakery, Resumo) na primeira abertura da tela.
				0 === $index ? 'high' : 'default'
			);
		}
	}
);

/**
 * @param array<string,mixed> $group
 */
function contorno_render_metabox_group( WP_Post $post, array $group ): void {
	wp_nonce_field( 'contorno_save_fields', 'contorno_fields_nonce' );

	if ( ! empty( $group['help'] ) ) {
		printf( '<p class="contorno-group-help">%s</p>', esc_html( (string) $group['help'] ) );
	}

	echo '<div class="contorno-fields">';

	foreach ( (array) ( $group['fields'] ?? array() ) as $name => $definition ) {
		contorno_render_field( $post->ID, (string) $name, (array) $definition );
	}

	echo '</div>';
}

/**
 * @param array<string,mixed> $definition
 */
function contorno_render_field( int $post_id, string $name, array $definition ): void {
	$type        = (string) ( $definition['type'] ?? 'text' );
	$label       = (string) ( $definition['label'] ?? $name );
	$help        = (string) ( $definition['help'] ?? '' );
	$placeholder = (string) ( $definition['placeholder'] ?? '' );
	$input_name  = 'contorno[' . $name . ']';
	$input_id    = 'contorno-field-' . $name;
	$value       = contorno_field( $name, $post_id );

	echo '<div class="contorno-field contorno-field--' . esc_attr( $type ) . '">';
	printf( '<label class="contorno-field__label" for="%s">%s</label>', esc_attr( $input_id ), esc_html( $label ) );

	switch ( $type ) {
		case 'textarea':
			printf(
				'<textarea id="%s" name="%s" rows="4" class="large-text" placeholder="%s">%s</textarea>',
				esc_attr( $input_id ),
				esc_attr( $input_name ),
				esc_attr( $placeholder ),
				esc_textarea( is_scalar( $value ) ? (string) $value : '' )
			);
			break;

		case 'checkbox':
			printf(
				'<label class="contorno-field__checkbox"><input type="hidden" name="%1$s" value="" /><input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s /> %4$s</label>',
				esc_attr( $input_name ),
				esc_attr( $input_id ),
				checked( (bool) $value, true, false ),
				esc_html__( 'Ativo', 'contorno' )
			);
			break;

		case 'select':
			printf( '<select id="%s" name="%s">', esc_attr( $input_id ), esc_attr( $input_name ) );
			foreach ( (array) ( $definition['options'] ?? array() ) as $option_value => $option_label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( (string) $option_value ),
					selected( (string) $value, (string) $option_value, false ),
					esc_html( (string) $option_label )
				);
			}
			echo '</select>';
			break;

		case 'number':
			printf(
				'<input type="number" step="%s" id="%s" name="%s" value="%s" class="regular-text" />',
				esc_attr( (string) ( $definition['step'] ?? 'any' ) ),
				esc_attr( $input_id ),
				esc_attr( $input_name ),
				esc_attr( is_scalar( $value ) && '' !== (string) $value ? (string) $value : '' )
			);
			break;

		case 'media':
			$preview = contorno_resolve_media( $value, 'medium' );
			echo '<div class="contorno-media" data-contorno-media>';
			printf(
				'<input type="text" id="%s" name="%s" value="%s" class="large-text" data-contorno-media-input placeholder="%s" />',
				esc_attr( $input_id ),
				esc_attr( $input_name ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				esc_attr__( 'ID do anexo ou caminho /brand/arquivo.webp', 'contorno' )
			);
			printf(
				'<button type="button" class="button" data-contorno-media-pick>%s</button>',
				esc_html__( 'Selecionar da biblioteca', 'contorno' )
			);
			if ( '' !== $preview ) {
				printf( '<img src="%s" alt="" class="contorno-media__preview" data-contorno-media-preview />', esc_url( $preview ) );
			} else {
				echo '<img src="" alt="" class="contorno-media__preview" data-contorno-media-preview hidden />';
			}
			echo '</div>';
			break;

		case 'attributes':
			contorno_render_attributes_field(
				$input_name,
				$input_id,
				(string) ( $definition['attribute_type'] ?? '' ),
				is_array( $value ) ? array_map( 'strval', $value ) : array()
			);
			break;

		case 'list':
		case 'media_list':
			$lines = is_array( $value ) ? implode( "\n", array_map( 'strval', $value ) ) : '';
			printf(
				'<textarea id="%s" name="%s" rows="6" class="large-text code" data-contorno-list>%s</textarea>',
				esc_attr( $input_id ),
				esc_attr( $input_name ),
				esc_textarea( $lines )
			);
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Um item por linha.', 'contorno' )
			);
			break;

		case 'repeater':
			contorno_render_repeater( $name, (array) ( $definition['subfields'] ?? array() ), is_array( $value ) ? $value : array() );
			break;

		case 'url':
		case 'text':
		default:
			printf(
				'<input type="%s" id="%s" name="%s" value="%s" class="large-text" placeholder="%s" />',
				'url' === $type ? 'url' : 'text',
				esc_attr( $input_id ),
				esc_attr( $input_name ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				esc_attr( $placeholder )
			);
			break;
	}

	if ( '' !== $help ) {
		printf( '<p class="description">%s</p>', esc_html( $help ) );
	}

	echo '</div>';
}

/**
 * Campo de atributos: grade de checkboxes vinda do catalogo central.
 *
 * Nunca ha digitacao livre. Tres situacoes convivem na mesma grade:
 *  - atributo ativo do catalogo: checkbox normal;
 *  - atributo inativo AINDA selecionado nesta unidade: aparece marcado, com
 *    aviso — desativar no catalogo nao apaga nada de ninguem;
 *  - valor fora do catalogo (unidade ainda nao migrada): vai como campo
 *    oculto e e listado abaixo, para nao sumir num salvamento.
 *
 * @param string[] $selected Valores gravados na unidade (chaves ou rotulos legados).
 */
function contorno_render_attributes_field( string $input_name, string $input_id, string $type, array $selected ): void {
	if ( '' === $type || ! isset( contorno_attribute_types()[ $type ] ) ) {
		return;
	}

	$catalog = contorno_attributes_by_type( $type );
	$manage  = admin_url( 'admin.php?page=' . CONTORNO_ATTRIBUTES_PAGE . '&tipo=' . rawurlencode( $type ) );

	// Casa o que a unidade tem com o catalogo, preservando o que nao casar.
	$checked = array();
	$legacy  = array();

	foreach ( $selected as $value ) {
		$attribute = contorno_attribute_resolve( $type, (string) $value );

		if ( null === $attribute ) {
			$legacy[] = (string) $value;
			continue;
		}

		$checked[ (string) $attribute['key'] ] = true;
	}

	echo '<div class="contorno-attributes" data-contorno-attributes>';

	printf(
		'<p class="contorno-attributes__tools"><input type="search" class="contorno-attributes__search" data-contorno-attributes-search placeholder="%s" aria-label="%s" /><a class="contorno-attributes__manage" href="%s">%s</a></p>',
		esc_attr__( 'Filtrar…', 'contorno' ),
		esc_attr__( 'Filtrar atributos', 'contorno' ),
		esc_url( $manage ),
		esc_html__( 'Gerenciar atributos', 'contorno' )
	);

	// Garante que desmarcar tudo grave vazio (checkbox nao enviado nao existe no POST).
	printf( '<input type="hidden" name="%s[]" value="" />', esc_attr( $input_name ) );

	if ( array() === $catalog ) {
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Nenhum atributo cadastrado ainda. Cadastre em Contorno → Atributos das unidades.', 'contorno' )
		);
	}

	echo '<div class="contorno-attributes__grid">';

	foreach ( $catalog as $index => $attribute ) {
		$key       = (string) $attribute['key'];
		$is_on     = isset( $checked[ $key ] );
		$is_hidden = ! $attribute['active'] && ! $is_on; // Inativo so some para quem ainda nao usa.

		if ( $is_hidden ) {
			continue;
		}

		printf(
			'<label class="contorno-attributes__item%1$s" data-contorno-attributes-item data-search="%2$s"><input type="checkbox" name="%3$s[]" id="%4$s" value="%5$s" %6$s /><span class="contorno-attributes__icon">%7$s</span><span class="contorno-attributes__label">%8$s%9$s</span></label>',
			$attribute['active'] ? '' : ' is-inactive',
			esc_attr( contorno_compare_key( (string) $attribute['label'] ) . ' ' . $key ),
			esc_attr( $input_name ),
			esc_attr( $input_id . '-' . $index ),
			esc_attr( $key ),
			checked( $is_on, true, false ),
			contorno_icon( (string) $attribute['icon'], 'contorno-attributes__svg' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( (string) $attribute['label'] ),
			$attribute['active'] ? '' : ' <em>' . esc_html__( '(inativo)', 'contorno' ) . '</em>'
		);
	}

	echo '</div>';

	if ( array() !== $legacy ) {
		echo '<div class="contorno-attributes__legacy">';
		printf(
			'<p class="description"><strong>%s</strong> %s</p>',
			esc_html__( 'Fora do catálogo:', 'contorno' ),
			esc_html__( 'estes valores vieram do cadastro antigo e continuam gravados. Rode a migração dos atributos ou cadastre-os no catálogo.', 'contorno' )
		);
		echo '<ul>';
		foreach ( $legacy as $value ) {
			printf(
				'<li><code>%s</code><input type="hidden" name="%s[]" value="%s" /></li>',
				esc_html( $value ),
				esc_attr( $input_name ),
				esc_attr( $value )
			);
		}
		echo '</ul></div>';
	}

	echo '</div>';
}

/**
 * @param array<string,array<string,mixed>> $subfields
 * @param array<int,mixed>                  $rows
 */
function contorno_render_repeater( string $name, array $subfields, array $rows ): void {
	echo '<div class="contorno-repeater" data-contorno-repeater data-field="' . esc_attr( $name ) . '">';
	echo '<div class="contorno-repeater__rows" data-contorno-repeater-rows>';

	$index = 0;
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		contorno_render_repeater_row( $name, $subfields, $row, (string) $index );
		++$index;
	}

	echo '</div>';

	// Template para novas linhas — __INDEX__ e trocado pelo JS.
	echo '<script type="text/html" data-contorno-repeater-template>';
	contorno_render_repeater_row( $name, $subfields, array(), '__INDEX__' );
	echo '</script>';

	printf(
		'<p><button type="button" class="button button-secondary" data-contorno-repeater-add>%s</button></p>',
		esc_html__( 'Adicionar item', 'contorno' )
	);
	echo '</div>';
}

/**
 * @param array<string,array<string,mixed>> $subfields
 * @param array<string,mixed>               $row
 */
function contorno_render_repeater_row( string $name, array $subfields, array $row, string $index ): void {
	echo '<div class="contorno-repeater__row" data-contorno-repeater-row>';
	printf(
		'<button type="button" class="button-link contorno-repeater__remove" data-contorno-repeater-remove aria-label="%s">&times;</button>',
		esc_attr__( 'Remover item', 'contorno' )
	);

	foreach ( $subfields as $sub_name => $sub_definition ) {
		$sub_type   = (string) ( $sub_definition['type'] ?? 'text' );
		$sub_label  = (string) ( $sub_definition['label'] ?? $sub_name );
		$input_name = 'contorno[' . $name . '][' . $index . '][' . $sub_name . ']';
		$sub_value  = $row[ $sub_name ] ?? '';

		echo '<div class="contorno-repeater__cell contorno-repeater__cell--' . esc_attr( $sub_type ) . '">';
		printf( '<span class="contorno-field__label">%s</span>', esc_html( $sub_label ) );

		switch ( $sub_type ) {
			case 'checkbox':
				printf(
					'<label><input type="hidden" name="%1$s" value="" /><input type="checkbox" name="%1$s" value="1" %2$s /></label>',
					esc_attr( $input_name ),
					checked( (bool) $sub_value, true, false )
				);
				break;

			case 'list':
				$lines = is_array( $sub_value ) ? implode( "\n", array_map( 'strval', $sub_value ) ) : (string) $sub_value;
				printf(
					'<textarea name="%s" rows="4" class="large-text code">%s</textarea>',
					esc_attr( $input_name ),
					esc_textarea( $lines )
				);
				break;

			case 'number':
				printf(
					'<input type="number" step="%s" name="%s" value="%s" />',
					esc_attr( (string) ( $sub_definition['step'] ?? 'any' ) ),
					esc_attr( $input_name ),
					esc_attr( is_scalar( $sub_value ) ? (string) $sub_value : '' )
				);
				break;

			case 'media':
				echo '<span class="contorno-media" data-contorno-media>';
				printf(
					'<input type="text" name="%s" value="%s" data-contorno-media-input />',
					esc_attr( $input_name ),
					esc_attr( is_scalar( $sub_value ) ? (string) $sub_value : '' )
				);
				printf(
					'<button type="button" class="button button-small" data-contorno-media-pick>%s</button>',
					esc_html__( 'Selecionar', 'contorno' )
				);
				echo '</span>';
				break;

			default:
				printf(
					'<input type="%s" name="%s" value="%s" />',
					'url' === $sub_type ? 'url' : 'text',
					esc_attr( $input_name ),
					esc_attr( is_scalar( $sub_value ) ? (string) $sub_value : '' )
				);
				break;
		}

		echo '</div>';
	}

	echo '</div>';
}

/**
 * Persistencia.
 */
add_action(
	'save_post',
	static function ( int $post_id, WP_Post $post ): void {
		if ( ! contorno_should_render_native_metaboxes() ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST['contorno_fields_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['contorno_fields_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'contorno_save_fields' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = contorno_flat_fields( $post->post_type );
		if ( array() === $fields ) {
			return;
		}

		$submitted = isset( $_POST['contorno'] ) && is_array( $_POST['contorno'] )
			? wp_unslash( $_POST['contorno'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitizado por contorno_sanitize_field().
			: array();

		foreach ( $fields as $name => $definition ) {
			// Grupos ausentes do POST (metabox fechado por outra tela) nao sao apagados.
			if ( ! array_key_exists( $name, $submitted ) ) {
				continue;
			}

			$value = $submitted[ $name ];

			if ( 'attributes' === (string) ( $definition['type'] ?? '' ) ) {
				$value = contorno_attributes_keep_unit_order( $post_id, (string) $name, (array) $value );
			}

			contorno_update_field( $post_id, (string) $name, $value );
		}
	},
	10,
	2
);

/**
 * CSS/JS da UI de campos.
 */
add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! isset( contorno_field_schema()[ $screen->post_type ] ) ) {
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
