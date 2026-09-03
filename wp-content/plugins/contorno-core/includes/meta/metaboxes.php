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

			contorno_update_field( $post_id, (string) $name, $submitted[ $name ] );
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
