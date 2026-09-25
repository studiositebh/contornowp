<?php
/**
 * Catalogo central de atributos das unidades (destaques, diferenciais e modalidades).
 *
 * Por que uma OPTION e nao um CPT/taxonomia: estes registros sao configuracao
 * administrativa, nao conteudo. Nao precisam de URL, feed, revisao, busca nem
 * REST; sao poucas dezenas de linhas lidas em toda pagina de unidade. Uma
 * option com autoload resolve em uma consulta ja feita pelo WordPress, sem
 * criar /atributo/<slug>/ nem 55 registros na tabela de posts.
 *
 * A unidade guarda apenas CHAVES ("cadeira-de-massagem"). Rotulo, icone e
 * ordem vivem aqui — renomear um atributo nao toca em nenhuma unidade.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_ATTRIBUTES_OPTION = 'contorno_unit_attributes';
const CONTORNO_ATTRIBUTES_PAGE   = 'contorno-atributos';

/**
 * Tipos do catalogo e o campo da unidade que cada um alimenta.
 *
 * @return array<string,array{label:string,field:string,help:string}>
 */
function contorno_attribute_types(): array {
	return array(
		'highlight'    => array(
			'label' => __( 'Destaques', 'contorno' ),
			'field' => 'facilities',
			'help'  => __( 'Cards com ícone na página da unidade (seção "Destaques Contorno").', 'contorno' ),
		),
		'differential' => array(
			'label' => __( 'Diferenciais', 'contorno' ),
			'field' => 'differentials',
			'help'  => __( 'Lista da seção "Diferenciais da unidade".', 'contorno' ),
		),
		'modality'     => array(
			'label' => __( 'Modalidades', 'contorno' ),
			'field' => 'modalities',
			'help'  => __( 'Modalidades oferecidas. Usado em buscas e dados estruturados.', 'contorno' ),
		),
	);
}

/**
 * Campo da unidade correspondente a um tipo ("highlight" => "facilities").
 */
function contorno_attribute_field( string $type ): string {
	return (string) ( contorno_attribute_types()[ $type ]['field'] ?? '' );
}

/**
 * Tipo correspondente a um campo ("facilities" => "highlight").
 */
function contorno_attribute_type_for_field( string $field ): string {
	foreach ( contorno_attribute_types() as $type => $definition ) {
		if ( $definition['field'] === $field ) {
			return (string) $type;
		}
	}

	return '';
}

/**
 * Icones disponiveis para o seletor — a allowlist E o registro de contorno_icon().
 *
 * O administrador nunca digita SVG: escolhe uma destas chaves.
 *
 * @return string[]
 */
function contorno_attribute_icon_choices(): array {
	static $choices = null;

	if ( null !== $choices ) {
		return $choices;
	}

	// Allowlist real: chaves legadas + biblioteca ampliada (Lucide, local).
	$choices = array_values(
		array_unique(
			array_merge(
				array_keys( contorno_icon_paths() ),
				array_keys( contorno_icon_library_paths() )
			)
		)
	);

	return $choices;
}

/**
 * Semente do catalogo, versionada em data/attributes.json.
 *
 * Gerada a partir do dataset: um item por valor distinto das 70 unidades, com
 * o icone que contorno_icon_for_label() ja devolvia — por isso o frontend nao
 * muda ao passar a ler do catalogo.
 *
 * @return array<int,array<string,mixed>>
 */
function contorno_attribute_seed(): array {
	static $seed = null;

	if ( null !== $seed ) {
		return $seed;
	}

	$path = CONTORNO_CORE_DIR . 'data/attributes.json';
	$seed = array();

	if ( is_readable( $path ) ) {
		$raw     = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) && isset( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
			foreach ( $decoded['items'] as $item ) {
				$clean = contorno_attribute_sanitize_item( (array) $item );
				if ( '' !== $clean['key'] ) {
					$seed[] = $clean;
				}
			}
		}
	}

	return $seed;
}

/**
 * Catalogo completo, ja saneado e ordenado.
 *
 * @return array<int,array<string,mixed>>
 */
function contorno_attribute_catalog(): array {
	$stored = get_option( CONTORNO_ATTRIBUTES_OPTION, null );

	if ( ! is_array( $stored ) || ! isset( $stored['items'] ) || ! is_array( $stored['items'] ) ) {
		return contorno_attribute_seed();
	}

	$items = array();
	foreach ( $stored['items'] as $item ) {
		$clean = contorno_attribute_sanitize_item( (array) $item );
		if ( '' !== $clean['key'] ) {
			$items[] = $clean;
		}
	}

	return $items;
}

/**
 * Grava o catalogo. Autoload ligado: e lido em toda pagina de unidade.
 *
 * @param array<int,array<string,mixed>> $items
 */
function contorno_attribute_save_catalog( array $items ): bool {
	$clean = array();
	$seen  = array();

	foreach ( $items as $item ) {
		$entry = contorno_attribute_sanitize_item( (array) $item );

		if ( '' === $entry['key'] ) {
			continue;
		}

		// Chave e unica DENTRO do tipo: "aulas-coletivas" pode ser destaque e modalidade.
		$id = $entry['type'] . '|' . $entry['key'];
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}

		$seen[ $id ] = true;
		$clean[]     = $entry;
	}

	return (bool) update_option(
		CONTORNO_ATTRIBUTES_OPTION,
		array(
			'version' => 1,
			'items'   => $clean,
		),
		true
	);
}

/**
 * Saneamento de um registro do catalogo. Nenhum campo aceita HTML.
 *
 * @param array<string,mixed> $item
 *
 * @return array{key:string,type:string,label:string,icon:string,icon_type:string,image_id:int,order:int,active:bool,aliases:string[]}
 */
function contorno_attribute_sanitize_item( array $item ): array {
	$types = contorno_attribute_types();
	$type  = sanitize_key( (string) ( $item['type'] ?? '' ) );
	$type  = isset( $types[ $type ] ) ? $type : 'highlight';

	$label = sanitize_text_field( (string) ( $item['label'] ?? '' ) );
	$key   = sanitize_title( (string) ( $item['key'] ?? '' ) );

	if ( '' === $key && '' !== $label ) {
		$key = sanitize_title( $label );
	}

	$icon    = sanitize_key( (string) ( $item['icon'] ?? '' ) );
	$choices = contorno_attribute_icon_choices();
	$icon    = in_array( $icon, $choices, true ) ? $icon : 'sparkles';

	/*
	 * Imagem personalizada: SUBSTITUI o icone no frontend quando valida.
	 * Nunca confia no ID vindo do POST — so aceita se o attachment existe
	 * de verdade e e uma imagem (mesma regra da galeria de unidade).
	 */
	$icon_type = sanitize_key( (string) ( $item['icon_type'] ?? 'icon' ) );
	$icon_type = 'image' === $icon_type ? 'image' : 'icon';

	$image_id = absint( $item['image_id'] ?? 0 );
	if ( $image_id > 0 && ( 'attachment' !== get_post_type( $image_id ) || ! wp_attachment_is_image( $image_id ) ) ) {
		$image_id = 0;
	}

	if ( 'image' === $icon_type && 0 === $image_id ) {
		$icon_type = 'icon';
	}

	$aliases = array();
	foreach ( (array) ( $item['aliases'] ?? array() ) as $alias ) {
		$alias = sanitize_text_field( (string) $alias );
		if ( '' !== trim( $alias ) ) {
			$aliases[] = $alias;
		}
	}

	return array(
		'key'       => $key,
		'type'      => $type,
		'label'     => $label,
		'icon'      => $icon,
		'icon_type' => $icon_type,
		'image_id'  => $image_id,
		'order'     => (int) ( $item['order'] ?? 0 ),
		'active'    => ! empty( $item['active'] ),
		'aliases'   => array_values( array_unique( $aliases ) ),
	);
}

/**
 * Atributos de um tipo, na ordem do catalogo.
 *
 * @return array<int,array<string,mixed>>
 */
function contorno_attributes_by_type( string $type, bool $include_inactive = true ): array {
	$items = array_values(
		array_filter(
			contorno_attribute_catalog(),
			static function ( array $item ) use ( $type, $include_inactive ): bool {
				return $item['type'] === $type && ( $include_inactive || $item['active'] );
			}
		)
	);

	usort(
		$items,
		static fn( array $a, array $b ): int => ( $a['order'] <=> $b['order'] ) ?: strcmp( (string) $a['label'], (string) $b['label'] )
	);

	return $items;
}

/**
 * Localiza um atributo pela chave.
 *
 * @return array<string,mixed>|null
 */
function contorno_attribute_get( string $type, string $key ): ?array {
	foreach ( contorno_attribute_catalog() as $item ) {
		if ( $item['type'] === $type && $item['key'] === $key ) {
			return $item;
		}
	}

	return null;
}

/**
 * Resolve um valor gravado numa unidade: chave do catalogo OU rotulo legado.
 *
 * O casamento por rotulo ignora caixa, acento e pontuacao — e o que permite
 * migrar "Aulas coletivas" e "Aulas Coletivas" para o mesmo atributo sem
 * depender de quem digitou.
 *
 * @return array<string,mixed>|null
 */
function contorno_attribute_resolve( string $type, string $value ): ?array {
	$value = trim( $value );

	if ( '' === $value ) {
		return null;
	}

	$direct = contorno_attribute_get( $type, sanitize_title( $value ) );

	// sanitize_title() de uma chave devolve a propria chave; de um rotulo, o slug dele.
	if ( null !== $direct && ( $direct['key'] === $value || $direct['key'] === sanitize_title( $value ) ) ) {
		return $direct;
	}

	$needle = contorno_compare_key( $value );

	if ( '' === $needle ) {
		return null;
	}

	foreach ( contorno_attribute_catalog() as $item ) {
		if ( $item['type'] !== $type ) {
			continue;
		}

		if ( contorno_compare_key( (string) $item['label'] ) === $needle ) {
			return $item;
		}

		foreach ( (array) $item['aliases'] as $alias ) {
			if ( contorno_compare_key( (string) $alias ) === $needle ) {
				return $item;
			}
		}
	}

	return null;
}

/**
 * Itens de um campo de atributos de uma unidade, prontos para render.
 *
 * A ORDEM e a da unidade (como esta gravada no meta), nao a do catalogo: a
 * ordem dentro de cada unidade sempre teve significado editorial e continua
 * intacta. Valores ainda nao migrados (rotulo solto) continuam aparecendo,
 * com o icone que a heuristica antiga daria.
 *
 * @return array<int,array{key:string,label:string,icon:string,known:bool,active:bool}>
 */
function contorno_unit_attribute_items( string $field, ?int $post_id = null ): array {
	$type = contorno_attribute_type_for_field( $field );

	if ( '' === $type ) {
		return array();
	}

	$items = array();

	foreach ( contorno_field_list( $field, $post_id ) as $raw ) {
		$value = is_array( $raw ) ? (string) ( $raw['label'] ?? ( $raw['key'] ?? '' ) ) : (string) $raw;
		$value = trim( $value );

		if ( '' === $value ) {
			continue;
		}

		$attribute = contorno_attribute_resolve( $type, $value );

		if ( null === $attribute ) {
			// Fora do catalogo: preserva o texto e o comportamento antigo.
			$items[] = array(
				'key'    => '',
				'label'  => $value,
				'icon'   => contorno_icon_for_label( $value ),
				'known'  => false,
				'active' => true,
			);
			continue;
		}

		$items[] = array(
			'key'       => (string) $attribute['key'],
			'label'     => (string) $attribute['label'],
			'icon'      => (string) $attribute['icon'],
			'icon_type' => (string) ( $attribute['icon_type'] ?? 'icon' ),
			'image_id'  => (int) ( $attribute['image_id'] ?? 0 ),
			'known'     => true,
			'active'    => (bool) $attribute['active'],
		);
	}

	return $items;
}

/**
 * Unidades que usam um atributo.
 *
 * Consulta por meta LIKE na chave entre aspas — o meta e um JSON de strings.
 * Rotulos legados tambem contam, para que a tela de uso nao minta enquanto a
 * migracao nao rodou.
 *
 * @return int[] IDs de post.
 */
function contorno_attribute_usage_ids( string $type, string $key ): array {
	$attribute = contorno_attribute_get( $type, $key );

	if ( null === $attribute ) {
		return array();
	}

	$field = contorno_attribute_field( $type );

	if ( '' === $field ) {
		return array();
	}

	global $wpdb;

	/*
	 * BUG CORRIGIDO: a consulta antiga nao filtrava post_type nem status,
	 * entao contava toda linha de postmeta com essa chave — inclusive
	 * REVISOES. Desde que os campos passaram a registrar
	 * 'revisions_enabled' => true (registry.php), o WordPress passou a
	 * gravar uma copia do meta em CADA revisao (post_type='revision'), o
	 * que inflava "Usado em X unidades" muito acima das ~70 unidades
	 * reais. DISTINCT + JOIN em post_type=unidade (nunca revisao/lixeira/
	 * rascunho automatico) e a contagem correta.
	 */
	$meta_key = contorno_meta_key( $field );
	$rows     = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT DISTINCT pm.post_id
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			  AND pm.meta_value <> ''
			  AND p.post_type = %s
			  AND p.post_status NOT IN ( 'trash', 'auto-draft' )",
			$meta_key,
			CONTORNO_CPT_UNIT
		)
	);

	$needles = array( contorno_compare_key( (string) $attribute['key'] ), contorno_compare_key( (string) $attribute['label'] ) );
	foreach ( (array) $attribute['aliases'] as $alias ) {
		$needles[] = contorno_compare_key( (string) $alias );
	}
	$needles = array_values( array_filter( array_unique( $needles ) ) );

	$ids = array();

	foreach ( (array) $rows as $post_id ) {
		$post_id = (int) $post_id;
		$values  = json_decode( (string) get_post_meta( $post_id, $meta_key, true ), true );

		if ( ! is_array( $values ) ) {
			continue;
		}

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			if ( in_array( contorno_compare_key( $value ), $needles, true ) ) {
				$ids[] = $post_id;
				break;
			}
		}
	}

	return $ids;
}

/**
 * Reordena o que veio do metabox pela ordem que a unidade ja tinha.
 *
 * A grade de checkboxes e renderizada na ordem do CATALOGO; se gravassemos na
 * ordem de submissao, salvar uma unidade sem mexer em nada reordenaria os
 * cards dela no site. A ordem dentro da unidade e editorial e so muda quando
 * alguem marca/desmarca: o que ja existia mantem a posicao e o que entrou vai
 * para o fim, na ordem do catalogo.
 *
 * @param string[] $submitted Chaves marcadas no metabox.
 *
 * @return string[]
 */
function contorno_attributes_keep_unit_order( int $post_id, string $field, array $submitted ): array {
	$type = contorno_attribute_type_for_field( $field );

	$submitted = array_values(
		array_unique(
			array_filter(
				array_map( static fn ( $value ): string => trim( (string) $value ), $submitted ),
				static fn ( string $value ): bool => '' !== $value
			)
		)
	);

	if ( '' === $type ) {
		return $submitted;
	}

	$previous = array();
	foreach ( contorno_field_list( $field, $post_id ) as $value ) {
		if ( ! is_string( $value ) ) {
			continue;
		}

		$attribute  = contorno_attribute_resolve( $type, $value );
		$previous[] = null !== $attribute ? (string) $attribute['key'] : $value;
	}

	$ordered = array();

	foreach ( $previous as $value ) {
		if ( in_array( $value, $submitted, true ) ) {
			$ordered[] = $value;
		}
	}

	foreach ( $submitted as $value ) {
		if ( ! in_array( $value, $ordered, true ) ) {
			$ordered[] = $value;
		}
	}

	return $ordered;
}

/**
 * Gera uma chave livre dentro do tipo, a partir do rotulo.
 *
 * @param array<int,array<string,mixed>> $items Catalogo em edicao.
 */
function contorno_attribute_unique_key( string $label, string $type, array $items, string $ignore_key = '' ): string {
	$base = sanitize_title( $label );

	if ( '' === $base ) {
		$base = 'atributo';
	}

	$taken = array();
	foreach ( $items as $item ) {
		if ( ( $item['type'] ?? '' ) === $type && ( $item['key'] ?? '' ) !== $ignore_key ) {
			$taken[] = (string) $item['key'];
		}
	}

	$key   = $base;
	$index = 2;
	while ( in_array( $key, $taken, true ) ) {
		$key = $base . '-' . $index;
		++$index;
	}

	return $key;
}
