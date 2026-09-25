<?php
/**
 * Testes do catalogo de atributos — rodados por scripts/test-attributes.sh.
 *
 * Carrega o codigo REAL do plugin (helpers.php e attributes/catalog.php) com
 * um punhado de stubs do WordPress, e usa o dataset como fonte do "antes".
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

$plugin = rtrim( (string) ( $argv[1] ?? '' ), '/\\' );

if ( '' === $plugin || ! is_dir( $plugin ) ) {
	fwrite( STDERR, "Uso: php test-attributes.php <caminho-do-plugin>\n" );
	exit( 1 );
}

define( 'ABSPATH', true );
define( 'CONTORNO_CORE_DIR', $plugin . '/' );
define( 'CONTORNO_CPT_UNIT', 'unidade' );
define( 'CONTORNO_CPT_CTN', 'ctn' );
const CONTORNO_META_PREFIX = '_contorno_';

// --- Stubs minimos do WordPress -------------------------------------------

function __( string $text, string $domain = '' ): string { return $text; }
function esc_attr( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function apply_filters( string $hook, $value, ...$args ) { return $value; }
function is_singular( $x = null ): bool { return false; }
function is_post_type_archive( $x = null ): bool { return false; }
function is_page(): bool { return false; }
function get_the_ID() { return 0; }
$GLOBALS['test_attachments'] = array(
	901 => array( 'type' => 'attachment', 'is_image' => true ),
	902 => array( 'type' => 'attachment', 'is_image' => false ), // ex.: um PDF.
	// 903 nao existe.
);

function get_post_type( $id = null ): string {
	if ( null !== $id && isset( $GLOBALS['test_attachments'][ (int) $id ] ) ) {
		return $GLOBALS['test_attachments'][ (int) $id ]['type'];
	}

	return CONTORNO_CPT_UNIT;
}

function wp_attachment_is_image( $id ): bool {
	return $GLOBALS['test_attachments'][ (int) $id ]['is_image'] ?? false;
}

function absint( $value ): int {
	return abs( (int) $value );
}

function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	return isset( $GLOBALS['test_attachments'][ (int) $id ] ) ? 'https://example.test/attachment-' . (int) $id . '.jpg' : false;
}

function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) {
	if ( ! isset( $GLOBALS['test_attachments'][ (int) $id ] ) ) {
		return '';
	}

	return '<img src="https://example.test/attachment-' . (int) $id . '.jpg" alt="" class="' . esc_attr( (string) ( $attr['class'] ?? '' ) ) . '" />';
}
function get_post_meta( $id, $key, $single = false ) { return ''; }

function remove_accents( string $value ): string {
	return strtr(
		$value,
		array(
			'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
			'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
			'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n','²'=>'2','º'=>'o','ª'=>'a',
			'Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A','É'=>'E','Ê'=>'E','È'=>'E','Ë'=>'E',
			'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O',
			'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','Ç'=>'C','Ñ'=>'N',
		)
	);
}

function sanitize_text_field( string $value ): string {
	return trim( (string) preg_replace( '/[\r\n\t]+/', ' ', strip_tags( $value ) ) );
}

function sanitize_key( string $value ): string {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}

function sanitize_title( string $value ): string {
	$value = strtolower( remove_accents( $value ) );
	$value = (string) preg_replace( '/[^a-z0-9]+/', '-', $value );

	return trim( $value, '-' );
}

$GLOBALS['test_options'] = array();

function get_option( string $name, $default = false ) {
	return $GLOBALS['test_options'][ $name ] ?? $default;
}

function update_option( string $name, $value, $autoload = null ): bool {
	$GLOBALS['test_options'][ $name ] = $value;

	return true;
}

function contorno_meta_key( string $field ): string {
	return CONTORNO_META_PREFIX . $field;
}

/** Vive em includes/data/units.php, que puxaria o WP inteiro; copia fiel. */
function contorno_normalize_search( string $value ): string {
	return trim( (string) preg_replace( '/\s+/', ' ', strtolower( remove_accents( $value ) ) ) );
}

/** Unidade "carregada" no momento — alimenta o stub de contorno_field_list(). */
$GLOBALS['test_unit'] = array();

function contorno_field_list( string $name, ?int $post_id = null ): array {
	return array_values( (array) ( $GLOBALS['test_unit'][ $name ] ?? array() ) );
}

require_once $plugin . '/includes/helpers.php';
require_once $plugin . '/includes/attributes/icon-library.php';
require_once $plugin . '/includes/attributes/catalog.php';

// --- Infra de teste --------------------------------------------------------

$ok   = 0;
$fail = 0;

/**
 * @param mixed $extra
 */
function t( string $name, bool $cond, string $extra = '' ): void {
	global $ok, $fail;
	echo $cond ? '  [OK]       ' : '  [FALHOU]   ', $name, ( '' !== $extra ? '  -> ' . $extra : '' ), "\n";
	$cond ? $ok++ : $fail++;
}

$dataset = json_decode( (string) file_get_contents( $plugin . '/data/dataset.json' ), true );
$units   = (array) ( $dataset['units'] ?? array() );
$fields  = array( 'facilities' => 'highlight', 'differentials' => 'differential', 'modalities' => 'modality' );

echo "\n== Catálogo\n";

$catalog = contorno_attribute_catalog();
t( 'catálogo carrega a semente', count( $catalog ) > 0, count( $catalog ) . ' atributos' );

$by_type = array();
foreach ( $catalog as $item ) {
	$by_type[ $item['type'] ][] = $item;
}

foreach ( array( 'highlight' => 29, 'differential' => 21, 'modality' => 5 ) as $type => $expected ) {
	t( sprintf( '%s: %d atributos', $type, $expected ), count( $by_type[ $type ] ?? array() ) === $expected, (string) count( $by_type[ $type ] ?? array() ) );
}

$duplicates = array();
$seen       = array();
foreach ( $catalog as $item ) {
	$id = $item['type'] . '|' . $item['key'];
	if ( isset( $seen[ $id ] ) ) {
		$duplicates[] = $id;
	}
	$seen[ $id ] = true;
}
t( 'nenhuma chave duplicada dentro do tipo', array() === $duplicates, implode( ', ', $duplicates ) );

$empty = array_filter( $catalog, static fn ( array $i ): bool => '' === $i['key'] || '' === $i['label'] );
t( 'nenhum atributo sem chave ou sem nome', array() === $empty );

$icons   = contorno_attribute_icon_choices();
$strange = array_filter( $catalog, static fn ( array $i ): bool => ! in_array( $i['icon'], $icons, true ) );
t( 'todos os ícones vêm da allowlist', array() === $strange, implode( ', ', array_column( $strange, 'icon' ) ) );

$svg = array_filter( $catalog, static fn ( array $i ): bool => $i['label'] !== strip_tags( (string) $i['label'] ) );
t( 'nenhum rótulo com HTML/SVG', array() === $svg );

echo "\n== Mapeamento das 70 unidades (dry-run)\n";

$unmapped = array();
$mapped   = 0;
$per_unit = array();

foreach ( $units as $unit ) {
	$data = $unit['fields'] ?? $unit;
	$slug = (string) ( $unit['slug'] ?? '?' );

	foreach ( $fields as $field => $type ) {
		$values = array();
		foreach ( (array) ( $data[ $field ] ?? array() ) as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$values[] = trim( $value );
			}
		}

		$keys = array();
		foreach ( $values as $value ) {
			$attribute = contorno_attribute_resolve( $type, $value );

			if ( null === $attribute ) {
				$unmapped[] = $slug . ' / ' . $field . ' / ' . $value;
				continue;
			}

			++$mapped;
			$keys[] = array( 'key' => (string) $attribute['key'], 'label' => (string) $attribute['label'], 'from' => $value );
		}

		$per_unit[ $slug ][ $field ] = array( 'before' => $values, 'after' => $keys );
	}
}

t( 'todos os valores atuais têm destino no catálogo', array() === $unmapped, implode( ' | ', array_slice( $unmapped, 0, 5 ) ) );
t( '100% dos valores mapeados', $mapped === 640 + 280 + 275, $mapped . ' de 1195' );

echo "\n== Equivalência antes/depois\n";

$lost      = array();
$reordered = array();
$diverged  = array();

foreach ( $per_unit as $slug => $unit_fields ) {
	foreach ( $unit_fields as $field => $pair ) {
		// Nenhum item some: um valor de entrada, um atributo de saída.
		if ( count( $pair['before'] ) !== count( $pair['after'] ) ) {
			$lost[] = $slug . '/' . $field;
			continue;
		}

		foreach ( $pair['before'] as $index => $value ) {
			$after = $pair['after'][ $index ];

			// A ordem de cada unidade é preservada posição a posição.
			if ( $after['from'] !== $value ) {
				$reordered[] = $slug . '/' . $field;
			}

			// O texto exibido só muda quando o valor era uma variante de caixa/acento.
			if ( contorno_compare_key( $after['label'] ) !== contorno_compare_key( $value ) ) {
				$diverged[] = sprintf( '%s/%s: "%s" -> "%s"', $slug, $field, $value, $after['label'] );
			}
		}
	}
}

t( 'nenhuma unidade perde atributo', array() === $lost, implode( ', ', $lost ) );
t( 'ordem de cada unidade preservada', array() === $reordered, implode( ', ', array_slice( $reordered, 0, 3 ) ) );
t( 'nenhum rótulo muda de significado', array() === $diverged, implode( ' | ', array_slice( $diverged, 0, 3 ) ) );

// Divergências apenas de caixa/acento são esperadas e contabilizadas.
$case_only = 0;
foreach ( $per_unit as $unit_fields ) {
	foreach ( $unit_fields as $pair ) {
		foreach ( $pair['after'] as $after ) {
			if ( $after['label'] !== $after['from'] ) {
				++$case_only;
			}
		}
	}
}
t( 'normalizações de caixa/acento contabilizadas', $case_only > 0, $case_only . ' ocorrências reescritas para o rótulo canônico' );

echo "\n== Idempotência\n";

$twice = array();
foreach ( $per_unit as $slug => $unit_fields ) {
	foreach ( $unit_fields as $field => $pair ) {
		$type = $fields[ $field ];

		foreach ( $pair['after'] as $after ) {
			$again = contorno_attribute_resolve( $type, $after['key'] );

			if ( null === $again || $again['key'] !== $after['key'] ) {
				$twice[] = $slug . '/' . $field . '/' . $after['key'];
			}
		}
	}
}
t( 'rodar de novo sobre chaves não muda nada', array() === $twice, implode( ', ', array_slice( $twice, 0, 3 ) ) );

echo "\n== Catálogo em uso\n";

// Renomear NAO muda a chave — e o que evita editar 70 unidades.
$items = contorno_attribute_catalog();
foreach ( $items as $index => $item ) {
	if ( 'highlight' === $item['type'] && 'vestiarios' === $item['key'] ) {
		$items[ $index ]['label'] = 'Vestiários completos';
	}
}
contorno_attribute_save_catalog( $items );

$renamed = contorno_attribute_get( 'highlight', 'vestiarios' );
t( 'chave permanece estável ao renomear', null !== $renamed && 'Vestiários completos' === $renamed['label'] );

$GLOBALS['test_unit'] = array( 'facilities' => array( 'vestiarios' ) );
$rendered             = contorno_unit_attribute_items( 'facilities' );
t( 'unidade renderiza o novo nome sem ser editada', 'Vestiários completos' === ( $rendered[0]['label'] ?? '' ) );

// Desativar nao apaga: some da oferta, continua em quem ja tinha.
$items = contorno_attribute_catalog();
foreach ( $items as $index => $item ) {
	if ( 'highlight' === $item['type'] && 'ducha-aquecida' === $item['key'] ) {
		$items[ $index ]['active'] = false;
	}
}
contorno_attribute_save_catalog( $items );

$offered = contorno_attributes_by_type( 'highlight', false );
$has     = array_filter( $offered, static fn ( array $i ): bool => 'ducha-aquecida' === $i['key'] );
t( 'atributo inativo não é oferecido para nova seleção', array() === $has );

$GLOBALS['test_unit'] = array( 'facilities' => array( 'ducha-aquecida' ) );
$rendered             = contorno_unit_attribute_items( 'facilities' );
t( 'atributo inativo já selecionado continua aparecendo', 'Ducha aquecida' === ( $rendered[0]['label'] ?? '' ) );

// Valor fora do catalogo nao e descartado.
$GLOBALS['test_unit'] = array( 'facilities' => array( 'Sauna seca' ) );
$rendered             = contorno_unit_attribute_items( 'facilities' );
t(
	'valor fora do catálogo continua sendo exibido',
	'Sauna seca' === ( $rendered[0]['label'] ?? '' ) && false === ( $rendered[0]['known'] ?? true )
);

echo "\n== Ordem ao salvar no painel\n";

$GLOBALS['test_unit'] = array( 'facilities' => array( 'acesso-wifi', 'vestiarios', 'aulas-coletivas' ) );

// O metabox envia na ordem do catalogo; a unidade mantem a ordem dela.
$saved = contorno_attributes_keep_unit_order( 1, 'facilities', array( 'aulas-coletivas', 'vestiarios', 'acesso-wifi' ) );
t( 'salvar sem mexer preserva a ordem da unidade', array( 'acesso-wifi', 'vestiarios', 'aulas-coletivas' ) === $saved, implode( ', ', $saved ) );

$saved = contorno_attributes_keep_unit_order( 1, 'facilities', array( 'cardio-x', 'aulas-coletivas', 'vestiarios', 'acesso-wifi' ) );
t( 'atributo novo entra no fim', array( 'acesso-wifi', 'vestiarios', 'aulas-coletivas', 'cardio-x' ) === $saved, implode( ', ', $saved ) );

$saved = contorno_attributes_keep_unit_order( 1, 'facilities', array( 'acesso-wifi' ) );
t( 'desmarcar remove só o desmarcado', array( 'acesso-wifi' ) === $saved, implode( ', ', $saved ) );

echo "\n== Biblioteca de icones (Lucide, ampliada) e imagem personalizada\n";

$legacy_choices = array( 'wifi', 'car', 'dumbbell', 'sparkles' ); // ja existiam antes desta rodada.
$new_choices    = contorno_attribute_icon_choices();

t(
	'chaves legadas continuam na allowlist',
	array() === array_diff( $legacy_choices, $new_choices )
);
t(
	'biblioteca ampliada entrou na allowlist (ex.: "coffee", que nao existia antes)',
	in_array( 'coffee', $new_choices, true )
);
t( 'allowlist tem muito mais que os ~30 icones antigos', count( $new_choices ) > 1000, (string) count( $new_choices ) );

// Icone legado continua vindo do traço original de helpers.php, nao da biblioteca nova.
t( 'contorno_icon("wifi") usa o traço de helpers.php', str_contains( contorno_icon( 'wifi' ), contorno_icon_paths()['wifi'] ) );
t( 'contorno_icon("coffee") resolve pela biblioteca nova (nao cai no fallback sparkles)', ! str_contains( contorno_icon( 'coffee' ), contorno_icon_paths()['sparkles'] ) );

// Nunca aceita chave fora da allowlist — cai em sparkles.
$item = contorno_attribute_sanitize_item( array( 'type' => 'highlight', 'label' => 'Teste', 'icon' => '<svg onload=alert(1)>' ) );
t( 'icone fora da allowlist (tentativa de HTML/SVG arbitrário) cai em sparkles', 'sparkles' === $item['icon'] );

// Imagem personalizada: so aceita attachment que existe E e imagem.
$item = contorno_attribute_sanitize_item( array( 'type' => 'highlight', 'label' => 'Chuveiro', 'icon' => 'shower', 'icon_type' => 'image', 'image_id' => 901 ) );
t( 'imagem valida: icon_type vira "image" e image_id é gravado', 'image' === $item['icon_type'] && 901 === $item['image_id'] );

$item = contorno_attribute_sanitize_item( array( 'type' => 'highlight', 'label' => 'PDF', 'icon' => 'shower', 'icon_type' => 'image', 'image_id' => 902 ) );
t( 'attachment que existe mas NÃO é imagem: volta pro ícone', 'icon' === $item['icon_type'] && 0 === $item['image_id'] );

$item = contorno_attribute_sanitize_item( array( 'type' => 'highlight', 'label' => 'Inexistente', 'icon' => 'shower', 'icon_type' => 'image', 'image_id' => 903 ) );
t( 'attachment inexistente (referência arbitrária): volta pro ícone', 'icon' === $item['icon_type'] && 0 === $item['image_id'] );

$item = contorno_attribute_sanitize_item( array( 'type' => 'highlight', 'label' => 'Sem imagem', 'icon' => 'shower', 'icon_type' => 'image', 'image_id' => 0 ) );
t( 'icon_type "image" sem image_id: volta pro ícone', 'icon' === $item['icon_type'] );

// Atributo legado (sem icon_type/image_id no registro, como os 55 da semente): continua icone normal.
$legacy_item = contorno_attribute_sanitize_item( array( 'type' => 'highlight', 'label' => 'Legado', 'icon' => 'wifi' ) );
t( 'item sem icon_type/image_id (formato antigo) assume "icon" por padrão', 'icon' === $legacy_item['icon_type'] && 0 === $legacy_item['image_id'] );

// contorno_attribute_icon(): resolve imagem > icone, e nunca os dois juntos.
$html_image = contorno_attribute_icon( array( 'icon' => 'shower', 'icon_type' => 'image', 'image_id' => 901, 'label' => 'Chuveiro' ) );
t( 'contorno_attribute_icon() com imagem válida não renderiza <svg>', ! str_contains( $html_image, '<svg' ) );

$html_icon = contorno_attribute_icon( array( 'icon' => 'wifi', 'icon_type' => 'icon', 'image_id' => 0 ) );
t( 'contorno_attribute_icon() sem imagem renderiza o <svg> normalmente', str_contains( $html_icon, '<svg' ) );

echo "\n";
printf( "%d passaram, %d falharam\n\n", $ok, $fail );

exit( $fail > 0 ? 1 : 0 );
