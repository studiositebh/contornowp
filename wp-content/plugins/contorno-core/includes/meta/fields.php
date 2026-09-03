<?php
/**
 * Leitura, escrita e sanitizacao dos campos estruturados.
 *
 * Ponte de plugins: se ACF (ou Meta Box) estiver ativo e possuir o campo,
 * o valor dele tem prioridade. Assim o tema funciona SEM plugin de custom
 * fields, e continua funcionando caso um seja adotado depois.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sistema de custom fields detectado.
 *
 * @return 'acf'|'metabox'|'native'
 */
function contorno_custom_fields_provider(): string {
	if ( function_exists( 'get_field' ) && class_exists( 'ACF' ) ) {
		return 'acf';
	}

	if ( function_exists( 'rwmb_meta' ) ) {
		return 'metabox';
	}

	return 'native';
}

/**
 * Le um campo estruturado.
 *
 * @return mixed Escalar, bool, float ou array (para list/repeater/media_list).
 */
function contorno_field( string $name, ?int $post_id = null, mixed $default = null ): mixed {
	$post_id = $post_id ?: get_the_ID();

	if ( ! $post_id ) {
		return $default;
	}

	$post_type  = (string) get_post_type( $post_id );
	$definition = contorno_flat_fields( $post_type )[ $name ] ?? array();
	$type       = (string) ( $definition['type'] ?? 'text' );
	$is_json    = in_array( $type, contorno_json_field_types(), true );

	$raw = null;

	// 1) Plugin de custom fields, se existir e tiver o campo.
	$provider = contorno_custom_fields_provider();
	if ( 'acf' === $provider ) {
		$acf_value = get_field( $name, $post_id );
		if ( null !== $acf_value && '' !== $acf_value && array() !== $acf_value ) {
			$raw = $acf_value;
		}
	} elseif ( 'metabox' === $provider ) {
		$mb_value = rwmb_meta( contorno_meta_key( $name ), array(), $post_id );
		if ( null !== $mb_value && '' !== $mb_value && array() !== $mb_value ) {
			$raw = $mb_value;
		}
	}

	// 2) Meta nativo do tema.
	if ( null === $raw ) {
		$raw = get_post_meta( $post_id, contorno_meta_key( $name ), true );
	}

	if ( $is_json ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null === $default ? array() : $default;
		}
		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : ( null === $default ? array() : $default );
	}

	if ( '' === $raw || null === $raw ) {
		if ( null !== $default ) {
			return $default;
		}

		return $definition['default'] ?? '';
	}

	if ( 'checkbox' === $type ) {
		return (bool) $raw;
	}

	if ( 'number' === $type ) {
		return (float) $raw;
	}

	return $raw;
}

/**
 * Le um campo e devolve string pronta para escapar.
 */
function contorno_field_text( string $name, ?int $post_id = null, string $default = '' ): string {
	$value = contorno_field( $name, $post_id, $default );

	if ( is_array( $value ) || is_object( $value ) ) {
		return $default;
	}

	if ( is_bool( $value ) ) {
		return $value ? '1' : '';
	}

	$value = (string) $value;

	return '' !== $value ? $value : $default;
}

/**
 * Le um campo de lista/repeater sempre como array.
 *
 * @return array<int,mixed>
 */
function contorno_field_list( string $name, ?int $post_id = null ): array {
	$value = contorno_field( $name, $post_id, array() );

	return is_array( $value ) ? array_values( $value ) : array();
}

/**
 * Resolve um campo de midia para URL.
 *
 * Aceita ID de anexo (fluxo normal do WordPress) ou caminho/URL bruto
 * (fluxo do importador, antes de subir os arquivos para a Biblioteca).
 */
function contorno_field_image_url( string $name, ?int $post_id = null, string $size = 'full' ): string {
	$value = contorno_field( $name, $post_id, '' );

	if ( is_array( $value ) ) {
		$value = $value['url'] ?? ( $value['ID'] ?? ( $value[0] ?? '' ) );
	}

	return contorno_resolve_media( $value, $size );
}

/**
 * Resolve uma lista de midia para URLs.
 *
 * @return string[]
 */
function contorno_field_image_urls( string $name, ?int $post_id = null, string $size = 'contorno-gallery' ): array {
	$items = contorno_field_list( $name, $post_id );
	$urls  = array();

	foreach ( $items as $item ) {
		if ( is_array( $item ) ) {
			$item = $item['url'] ?? ( $item['ID'] ?? '' );
		}
		$url = contorno_resolve_media( $item, $size );
		if ( '' !== $url ) {
			$urls[] = $url;
		}
	}

	return $urls;
}

/**
 * Converte ID de anexo, caminho relativo ou URL absoluta em URL utilizavel.
 */
function contorno_resolve_media( mixed $value, string $size = 'full' ): string {
	if ( is_numeric( $value ) && (int) $value > 0 ) {
		$src = wp_get_attachment_image_url( (int) $value, $size );

		return is_string( $src ) ? $src : '';
	}

	if ( ! is_string( $value ) || '' === $value ) {
		return '';
	}

	if ( preg_match( '#^https?://#', $value ) ) {
		return $value;
	}

	// Caminho do projeto React (/units/..., /brand/..., /ctn/...).
	return contorno_asset_url( $value );
}

/**
 * Sanitiza um valor conforme o tipo declarado no esquema.
 *
 * @param array<string,mixed> $definition
 */
function contorno_sanitize_field( mixed $value, array $definition ): mixed {
	$type = (string) ( $definition['type'] ?? 'text' );

	switch ( $type ) {
		case 'url':
			return esc_url_raw( trim( (string) $value ) );

		case 'number':
			return '' === trim( (string) $value ) ? '' : (string) (float) $value;

		case 'checkbox':
			return $value ? '1' : '';

		case 'textarea':
			return sanitize_textarea_field( (string) $value );

		case 'select':
			$options = (array) ( $definition['options'] ?? array() );
			$value   = sanitize_text_field( (string) $value );

			return array_key_exists( $value, $options ) ? $value : (string) ( $definition['default'] ?? '' );

		case 'media':
			if ( is_numeric( $value ) ) {
				return (string) absint( $value );
			}

			return sanitize_text_field( (string) $value );

		case 'list':
		case 'media_list':
			$items = is_array( $value ) ? $value : preg_split( '/\R/', (string) $value );
			$items = array_map( static fn ( $item ): string => sanitize_text_field( (string) $item ), (array) $items );
			$items = array_values( array_filter( $items, static fn ( string $item ): bool => '' !== trim( $item ) ) );

			return (string) wp_json_encode( $items );

		case 'repeater':
			$rows      = is_array( $value ) ? $value : array();
			$subfields = (array) ( $definition['subfields'] ?? array() );
			$clean     = array();

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$clean_row = array();
				foreach ( $subfields as $sub_name => $sub_definition ) {
					$sub_value = $row[ $sub_name ] ?? '';
					$sub_type  = (string) ( $sub_definition['type'] ?? 'text' );

					if ( in_array( $sub_type, contorno_json_field_types(), true ) ) {
						// Subcampos de lista ficam como array real dentro do JSON do repeater.
						$decoded             = json_decode( (string) contorno_sanitize_field( $sub_value, $sub_definition ), true );
						$clean_row[ $sub_name ] = is_array( $decoded ) ? $decoded : array();
					} else {
						$clean_row[ $sub_name ] = contorno_sanitize_field( $sub_value, $sub_definition );
					}
				}

				// Descarta linhas totalmente vazias.
				$has_content = false;
				foreach ( $clean_row as $cell ) {
					if ( is_array( $cell ) ? array() !== $cell : '' !== (string) $cell ) {
						$has_content = true;
						break;
					}
				}

				if ( $has_content ) {
					$clean[] = $clean_row;
				}
			}

			return (string) wp_json_encode( $clean );

		case 'text':
		default:
			return sanitize_text_field( (string) $value );
	}
}

/**
 * Escreve um campo estruturado (usado pelo importador e pelos metaboxes).
 */
function contorno_update_field( int $post_id, string $name, mixed $value ): void {
	$post_type  = (string) get_post_type( $post_id );
	$definition = contorno_flat_fields( $post_type )[ $name ] ?? array( 'type' => 'text' );
	$clean      = contorno_sanitize_field( $value, $definition );

	if ( '' === $clean || '[]' === $clean ) {
		delete_post_meta( $post_id, contorno_meta_key( $name ) );

		return;
	}

	update_post_meta( $post_id, contorno_meta_key( $name ), $clean );
}
