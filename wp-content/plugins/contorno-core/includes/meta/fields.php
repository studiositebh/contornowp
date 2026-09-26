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
	$list  = is_array( $value ) ? array_values( $value ) : array();

	/**
	 * Permite que integracoes (ex.: Contorno EVO Sync) sobreponham dados de
	 * uma lista na leitura, sem gravar por cima do que o editor cadastrou.
	 *
	 * @param array<int,mixed> $list
	 * @param string           $name
	 * @param int              $post_id
	 */
	return (array) apply_filters( 'contorno_field_list', $list, $name, (int) ( $post_id ?: get_the_ID() ) );
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
 * Renderiza uma imagem RESPONSIVA a partir de um valor de campo.
 *
 * Quando o valor e um ID de anexo, usa wp_get_attachment_image(), que emite
 * srcset e sizes automaticamente — o navegador baixa a versao do tamanho
 * certo em vez do original. Isso evita servir um JPG de 2,8 MB num card de
 * 350px, que era o caso antes.
 *
 * Quando o valor e um caminho solto (asset ainda nao na Biblioteca), cai num
 * <img> simples: sem srcset, mas funcional.
 *
 * @param array<string,string> $attr Atributos extra (class, alt, loading...).
 */
function contorno_image_tag( mixed $value, string $size = 'contorno-card', array $attr = array() ): string {
	if ( is_array( $value ) ) {
		$value = $value['ID'] ?? ( $value['url'] ?? ( $value[0] ?? '' ) );
	}

	$defaults = array(
		'alt'      => '',
		'loading'  => 'lazy',
		'decoding' => 'async',
	);

	$attr = array_merge( $defaults, $attr );

	if ( is_numeric( $value ) && (int) $value > 0 ) {
		$html = wp_get_attachment_image( (int) $value, $size, false, $attr );

		if ( is_string( $html ) && '' !== $html ) {
			return $html;
		}
	}

	$url = contorno_resolve_media( $value, $size );

	if ( '' === $url ) {
		return '';
	}

	$parts = '';
	foreach ( $attr as $key => $val ) {
		if ( '' === (string) $val && 'alt' !== $key ) {
			continue;
		}
		$parts .= sprintf( ' %s="%s"', sanitize_key( (string) $key ), esc_attr( (string) $val ) );
	}

	return sprintf( '<img src="%s"%s />', esc_url( $url ), $parts );
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
 * Componente padrao de selecao de imagem (wp.media) — preview, trocar,
 * remover e nome do arquivo numa unica linha compacta quando ha espaco.
 * Nenhuma URL tecnica exposta ao usuario; JS unico (bindMediaPicker() em
 * admin-fields.js) para toda tela que usa este componente.
 *
 * Resolver o valor gravado (attachment ID, URL ou caminho legado) e tarefa
 * de quem chama — contorno_resolve_media() faz isso pros campos de meta,
 * mas quem so trabalha com attachment ID pode usar wp_get_attachment_image_url()
 * direto. Isto so desenha o picker a partir do que ja foi resolvido.
 *
 * @param string $stored_value O que vai no hidden input (reenviado como
 *                              esta ate o usuario trocar/remover pelo JS —
 *                              attachment ID novo ou string vazia).
 * @param string $preview_url  URL ja resolvida da imagem atual, ou ''.
 * @param array{
 *     select_label?: string,
 *     change_label?: string,
 *     remove_label?: string,
 *     empty_label?: string,
 *     value_mode?: string,
 * } $args value_mode 'id' (padrao — hidden guarda o attachment ID escolhido)
 *                     ou 'url' (hidden guarda a URL — repeaters legados que
 *                     ja gravam URL direto, ex.: logos de marca).
 */
function contorno_render_media_picker( string $input_name, string $stored_value, string $preview_url, array $args = array() ): void {
	$url       = $preview_url;
	$stored    = $stored_value;
	$has_value = '' !== $url;
	$filename  = $has_value ? wp_basename( $url ) : '';
	$mode      = 'url' === ( $args['value_mode'] ?? 'id' ) ? 'url' : 'id';

	$select_label = (string) ( $args['select_label'] ?? __( 'Selecionar imagem', 'contorno' ) );
	$change_label = (string) ( $args['change_label'] ?? __( 'Trocar imagem', 'contorno' ) );
	$remove_label = (string) ( $args['remove_label'] ?? __( 'Remover', 'contorno' ) );
	$empty_label  = (string) ( $args['empty_label'] ?? __( 'Nenhuma imagem selecionada', 'contorno' ) );

	printf(
		'<div class="contorno-media-picker" data-contorno-media-picker data-contorno-media-mode="%s" data-select-label="%s" data-change-label="%s" data-empty-label="%s">',
		esc_attr( $mode ),
		esc_attr( $select_label ),
		esc_attr( $change_label ),
		esc_attr( $empty_label )
	);

	printf(
		'<input type="hidden" name="%s" value="%s" data-contorno-media-input />',
		esc_attr( $input_name ),
		esc_attr( $stored )
	);

	echo '<div class="contorno-media-picker__row">';

	echo '<span class="contorno-media-picker__preview-wrap">';
	printf(
		'<img class="contorno-media-picker__preview" src="%s" alt="" data-contorno-media-preview %s />',
		esc_url( $url ),
		$has_value ? '' : 'hidden'
	);
	printf(
		'<span class="contorno-media-picker__preview-empty" data-contorno-media-empty-icon aria-hidden="true" %s>%s</span>',
		$has_value ? 'hidden' : '',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>'
	);
	// Lixeira sobreposta no canto da miniatura — so existe visualmente
	// quando ha imagem (JS mostra/esconde; some por completo sem imagem).
	printf(
		'<button type="button" class="contorno-media-picker__remove" data-contorno-media-remove aria-label="%s" title="%s" %s>%s</button>',
		esc_attr( $remove_label ),
		esc_attr( $remove_label ),
		$has_value ? '' : 'hidden',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>'
	);
	echo '</span>';

	printf(
		'<button type="button" class="button contorno-media-picker__pick" data-contorno-media-pick>%s</button>',
		esc_html( $has_value ? $change_label : $select_label )
	);
	printf(
		'<span class="contorno-media-picker__filename" data-contorno-media-filename>%s</span>',
		$has_value ? esc_html( $filename ) : esc_html( $empty_label )
	);

	echo '</div>';
	echo '</div>';
}

/**
 * Serializa um valor para guardar em meta.
 *
 * JSON_UNESCAPED_UNICODE e obrigatorio aqui. Sem ele, "musculacao" com cedilha
 * viraria "ç" no JSON, e o update_metadata() do WordPress aplica
 * wp_unslash() no valor — o que remove a barra e deixa "u00e7" literal na
 * tela. JSON_UNESCAPED_SLASHES evita o mesmo problema com "\/" em URLs.
 *
 * @param array<int|string,mixed> $value
 */
function contorno_encode_json( array $value ): string {
	$json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	return is_string( $json ) ? $json : '[]';
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
		case 'segmented': // Mesma allowlist do select — so muda o componente visual.
			$options = (array) ( $definition['options'] ?? array() );
			$value   = sanitize_text_field( (string) $value );

			return array_key_exists( $value, $options ) ? $value : (string) ( $definition['default'] ?? '' );

		case 'money':
			/*
			 * Normaliza pro MESMO formato decimal-com-ponto que 'number' ja
			 * gravava — contorno_format_price()/EVO/calculos continuam
			 * lendo exatamente como antes ((float) de "9.90" e "9.9" e o
			 * mesmo numero). "R$" e so apresentacao: nunca e gravado.
			 * Entrada que nao da pra interpretar como valor (nem BR
			 * "1.299,90" nem solto "99.90") preserva o texto digitado em
			 * vez de zerar — mesmo cuidado ja usado em cep/phone/coordinate.
			 *
			 * BUG CORRIGIDO: `(string) (float) $clean` arredondava a
			 * REPRESENTACAO pro menor numero de casas que ainda descreve o
			 * mesmo float — "9.90" virava "9.9" e "100.00" virava "100"
			 * (sem nenhuma casa decimal). Sempre gravar com
			 * number_format(...,2) garante as duas casas sempre, sem
			 * mudar o valor numerico gravado.
			 */
			$raw = trim( (string) $value );

			if ( '' === $raw ) {
				return '';
			}

			$clean = (string) preg_replace( '/[^0-9,.]/', '', $raw );

			if ( '' === $clean ) {
				return sanitize_text_field( $raw );
			}

			if ( str_contains( $clean, ',' ) ) {
				// Formato BR: ponto = separador de milhar, virgula = decimal.
				$clean = str_replace( '.', '', $clean );
				$clean = str_replace( ',', '.', $clean );
			} elseif ( 1 !== preg_match( '/^\d+\.\d{1,2}$/', $clean ) ) {
				// Sem virgula e sem um unico ponto decimal no fim (ex.:
				// "1.299" colado) — os pontos sao de milhar, nao decimal.
				$clean = str_replace( '.', '', $clean );
			}

			if ( ! is_numeric( $clean ) || (float) $clean < 0 ) {
				return sanitize_text_field( $raw );
			}

			return number_format( (float) $clean, 2, '.', '' );

		case 'media':
			if ( is_numeric( $value ) ) {
				return (string) absint( $value );
			}

			return sanitize_text_field( (string) $value );

		case 'cep':
			/*
			 * Normaliza para 00000-000 (mesmo formato que o dataset e a
			 * busca por CEP ja usam — contorno_cep_digits()/contorno_format_cep()
			 * em includes/data/geo.php). Entrada sem 8 digitos validos (CEP
			 * incompleto, estrangeiro, etc.) nao e apagada: fica como texto
			 * saneado, sem forcar um formato que nao existe.
			 */
			$digits = contorno_cep_digits( (string) $value );

			return '' !== $digits ? contorno_format_cep( $digits ) : sanitize_text_field( trim( (string) $value ) );

		case 'date':
			/*
			 * Grava sempre AAAA-MM-DD (o que <input type="date"> ja manda).
			 * Um valor antigo em texto livre (antes desta rodada) nao e
			 * apagado por nao bater o formato — so fica sem a formatacao
			 * bonita na exibicao (contorno_pre_sale_info_line() mostra o
			 * texto cru quando nao reconhece a data).
			 */
			$raw = trim( (string) $value );

			if ( '' === $raw ) {
				return '';
			}

			$date = DateTime::createFromFormat( 'Y-m-d', $raw );

			return ( $date instanceof DateTime && $date->format( 'Y-m-d' ) === $raw )
				? $raw
				: sanitize_text_field( $raw );

		case 'phone':
			/*
			 * So digitos — mesmo formato ja gravado hoje em phone/whatsapp e
			 * que contorno_format_phone() (includes/helpers.php) usa para
			 * exibir no site. A mascara e so no JS; aqui e a garantia real.
			 */
			return contorno_phone_digits( (string) $value );

		case 'coordinate':
			$raw = trim( (string) $value );

			if ( '' === $raw ) {
				return '';
			}

			// Aceita virgula OU ponto decimal na digitacao.
			$normalized = str_replace( ',', '.', $raw );

			if ( 1 !== preg_match( '/^-?\d{1,3}(?:\.\d+)?$/', $normalized ) ) {
				// Letras, sinais demais, etc.: preserva o que foi digitado em
				// vez de apagar um dado que pode so estar mal formatado.
				return sanitize_text_field( $raw );
			}

			$number = (float) $normalized;
			$min    = (float) ( $definition['min'] ?? -180 );
			$max    = (float) ( $definition['max'] ?? 180 );

			if ( $number < $min || $number > $max ) {
				return sanitize_text_field( $raw );
			}

			// Ate 7 casas decimais (~1cm de precisao), sem zero a mais no fim.
			return rtrim( rtrim( sprintf( '%.7F', $number ), '0' ), '.' );

		case 'attributes':
			/*
			 * Lista de CHAVES do catalogo, na ordem em que a unidade as tem.
			 *
			 * Rotulos legados (antes da migracao) passam intactos: o campo
			 * nunca descarta o que ja estava gravado so porque ainda nao virou
			 * chave. Quem resolve isso e contorno_unit_attribute_items().
			 */
			$items = is_array( $value ) ? $value : preg_split( '/\R/', (string) $value );
			$items = array_map( static fn ( $item ): string => trim( sanitize_text_field( (string) $item ) ), (array) $items );
			$items = array_values( array_unique( array_filter( $items, static fn ( string $item ): bool => '' !== $item ) ) );

			return contorno_encode_json( $items );

		case 'list':
			$items = is_array( $value ) ? $value : preg_split( '/\R/', (string) $value );
			$items = array_map( static fn ( $item ): string => sanitize_text_field( (string) $item ), (array) $items );
			$items = array_values( array_filter( $items, static fn ( string $item ): bool => '' !== trim( $item ) ) );

			return contorno_encode_json( $items );

		case 'media_list':
			/*
			 * Nunca confia no array que veio do navegador: cada item so entra
			 * se for um ID de anexo que REALMENTE existe e e uma imagem
			 * (wp_attachment_is_image), nunca uma referencia arbitraria a
			 * arquivo do servidor. Path/URL legado (pre-Biblioteca de Midia)
			 * continua aceito como estava, para nao quebrar unidade que ainda
			 * nao foi migrada — so passa por sanitize_text_field().
			 */
			$items = is_array( $value ) ? $value : preg_split( '/\R/', (string) $value );
			$clean = array();

			foreach ( (array) $items as $item ) {
				$item = is_scalar( $item ) ? trim( (string) $item ) : '';

				if ( '' === $item ) {
					continue;
				}

				if ( ctype_digit( $item ) ) {
					$id = absint( $item );

					if ( $id > 0 && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id ) ) {
						$clean[] = (string) $id;
					}

					continue;
				}

				$clean[] = sanitize_text_field( $item );
			}

			return contorno_encode_json( array_values( $clean ) );

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

			return contorno_encode_json( $clean );

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
