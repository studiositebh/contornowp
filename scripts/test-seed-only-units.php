<?php
/**
 * Testa que o dataset so semeia unidades NOVAS (importer.php), sem tocar no
 * que o cliente editou, e que a EVO continua mandando em preco/descricao.
 * Codigo REAL do importador e do overlay EVO; WordPress simulado em memoria.
 * Nada de banco, rede ou producao.   scripts/test-seed-only-units.sh
 */
declare( strict_types = 1 );

$root = dirname( __DIR__ ) . '/wp-content/plugins';
define( 'ABSPATH', true );
define( 'CONTORNO_CORE_DIR', $root . '/contorno-core/' );
define( 'CONTORNO_CPT_UNIT', 'unidade' );
define( 'CONTORNO_CPT_CTN', 'ctn' );
define( 'CONTORNO_TAX_CITY', 'unidade_cidade' );
define( 'CONTORNO_TAX_UNIT_KIND', 'unidade_tipo' );

// --- WordPress em memoria ---------------------------------------------------
$GLOBALS['posts'] = array();
$GLOBALS['meta']  = array();
$GLOBALS['next']  = 100;

class WP_Post {
	public int $ID = 0;
	public string $post_type = '';
	public string $post_name = '';
	public string $post_title = '';
	public string $post_excerpt = '';
	public int $menu_order = 0;
}
function __( string $t, string $d = '' ): string { return $t; }
function _n( string $a, string $b, int $n, string $d = '' ): string { return 1 === $n ? $a : $b; }
function apply_filters( string $h, $v, ...$a ) { return $v; }
function is_wp_error( $x ): bool { return false; }
function sanitize_title( string $s ): string { return strtolower( trim( $s ) ); }
function taxonomy_exists( string $t ): bool { return false; }
function wp_set_object_terms( ...$a ) {}
function has_post_thumbnail( int $id ): bool { return isset( $GLOBALS['meta'][ $id ]['_thumbnail_id'] ); }
function set_post_thumbnail( int $id, int $att ): void { $GLOBALS['meta'][ $id ]['_thumbnail_id'] = $att; }
function get_post_type( int $id ): string { return $GLOBALS['posts'][ $id ]->post_type; }
function get_posts( array $a ): array {
	return array_values( array_filter( $GLOBALS['posts'], fn( $p ) => $p->post_type === $a['post_type'] && $p->post_name === $a['name'] ) );
}
function wp_insert_post( array $a, $e = false ) {
	$p     = new WP_Post();
	$p->ID = ++$GLOBALS['next'];
	foreach ( array( 'post_type', 'post_name', 'post_title', 'post_excerpt', 'menu_order' ) as $k ) {
		$p->$k = $a[ $k ] ?? $p->$k;
	}
	$GLOBALS['posts'][ $p->ID ] = $p;
	return $p->ID;
}
function wp_update_post( array $a, $e = false ) {
	$p = $GLOBALS['posts'][ $a['ID'] ];
	foreach ( array( 'post_title', 'post_excerpt', 'menu_order' ) as $k ) {
		$p->$k = $a[ $k ] ?? $p->$k;
	}
	return $p->ID;
}
function update_post_meta( int $id, string $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; return true; }
function delete_post_meta( int $id, string $k ) { unset( $GLOBALS['meta'][ $id ][ $k ] ); return true; }
function get_post_meta( int $id, string $k, bool $single = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function metadata_exists( string $t, int $id, string $k ): bool { return isset( $GLOBALS['meta'][ $id ][ $k ] ); }
function contorno_flat_fields( string $t ): array { return array(); }
function contorno_field( string $n, ?int $id = null, $d = null ) { return $GLOBALS['meta'][ $id ][ '_contorno_' . $n ] ?? $d; }
// Mesmo contrato de meta/fields.php: valor vazio APAGA o meta.
function contorno_update_field( int $id, string $n, $v ): void {
	$clean = is_array( $v ) ? (string) json_encode( $v, JSON_UNESCAPED_UNICODE ) : (string) $v;
	if ( '' === $clean || '[]' === $clean ) {
		delete_post_meta( $id, '_contorno_' . $n );
		return;
	}
	update_post_meta( $id, '_contorno_' . $n, $clean );
}
function contorno_format_price( float $p ): string { return 'R$ ' . number_format( $p, 2, ',', '.' ); }

require_once CONTORNO_CORE_DIR . 'includes/migration/importer.php';

// --- Mini framework ---------------------------------------------------------
$fail = 0;
function check( string $name, bool $ok ): void {
	global $fail;
	echo ( $ok ? '[OK]     ' : '[FALHOU] ' ) . $name . "\n";
	if ( ! $ok ) {
		++$fail;
	}
}
function run_migrate( array $units, bool $force = false ): void {
	$m = new Contorno_Migration( $force, false );
	$r = new ReflectionMethod( $m, 'import_entities' );
	$r->invoke( $m, $units, 'unidade', 'units' );
}
function unit_id( string $slug ): int {
	return get_posts( array( 'post_type' => 'unidade', 'name' => $slug ) )[0]->ID;
}

$dataset = array(
	array(
		'slug'      => 'teste',
		'title'     => 'Teste Dataset',
		'excerpt'   => 'Resumo dataset',
		'menuOrder' => 5,
		'fields'    => array(
			'hours'      => 'Seg a sex 6h-22h',
			'badge'      => 'Novo',
			'phone'      => '3133334444',
			'maps_query' => 'Rua A 1',
			'plans'      => array( array( 'id' => 'black', 'name' => 'Black', 'benefits' => array( 'A' ) ) ),
		),
	),
);

// 1. Unidade nova recebe os dados iniciais.
run_migrate( $dataset );
$id = unit_id( 'teste' );
$p  = $GLOBALS['posts'][ $id ];
check( 'unidade nova: titulo/resumo/ordem do dataset', 'Teste Dataset' === $p->post_title && 'Resumo dataset' === $p->post_excerpt && 5 === $p->menu_order );
check( 'unidade nova: campos do dataset gravados', 'Seg a sex 6h-22h' === contorno_field( 'hours', $id ) && 'Novo' === contorno_field( 'badge', $id ) && null !== contorno_field( 'plans', $id ) );

// Simula o cliente editando no painel + metas da EVO.
$p->post_title   = 'Titulo do Cliente';
$p->post_excerpt = 'Resumo do cliente';
$p->menu_order   = 1;
contorno_update_field( $id, 'hours', 'Horario do cliente' );
contorno_update_field( $id, 'badge', 'Promo' );
contorno_update_field( $id, 'plans', array( array( 'id' => 'black', 'name' => 'Black', 'benefits' => array( 'Meu beneficio' ) ) ) );
contorno_update_field( $id, 'phone', '' );      // esvaziado de proposito
contorno_update_field( $id, 'maps_query', '' ); // esvaziado de proposito
update_post_meta( $id, '_contorno_evo_links', array( 'black' => 321 ) );
update_post_meta( $id, '_contorno_evo_sync_branch', 8 );
update_post_meta( $id, '_contorno_evo_memberships', '[{"id_membership":321}]' );
$before = json_encode( array( $GLOBALS['posts'][ $id ], $GLOBALS['meta'][ $id ] ) );

// 2/3. Unidade existente mantem edicoes; campo vazio continua vazio.
run_migrate( $dataset );
$p = $GLOBALS['posts'][ $id ];
check( 'existente: titulo, resumo e ordem do cliente preservados', 'Titulo do Cliente' === $p->post_title && 'Resumo do cliente' === $p->post_excerpt && 1 === $p->menu_order );
check( 'existente: horario, badge e planos locais preservados', 'Horario do cliente' === contorno_field( 'hours', $id ) && 'Promo' === contorno_field( 'badge', $id ) && str_contains( (string) contorno_field( 'plans', $id ), 'Meu beneficio' ) );
check( 'campo esvaziado de proposito continua vazio (telefone, mapa)', null === contorno_field( 'phone', $id ) && null === contorno_field( 'maps_query', $id ) );

// 4. Deploy repetido nao altera nada.
run_migrate( $dataset );
run_migrate( $dataset );
run_migrate( $dataset );
$after = json_encode( array( $GLOBALS['posts'][ $id ], $GLOBALS['meta'][ $id ] ) );
// So _wpb_vc_js_status pode ser criado (flag do WPBakery); o resto e identico.
$strip = static fn( string $s ): string => (string) preg_replace( '/,"_wpb_vc_js_status":"true"/', '', $s );
check( 'deploy repetido (3x): estado identico', $strip( $before ) === $strip( $after ) );
check( 'sem duplicar unidade', 1 === count( get_posts( array( 'post_type' => 'unidade', 'name' => 'teste' ) ) ) );

// Vinculos EVO.
check( 'vinculos EVO preservados (_contorno_evo_links e _contorno_evo_sync_branch)', array( 'black' => 321 ) === get_post_meta( $id, '_contorno_evo_links' ) && 8 === get_post_meta( $id, '_contorno_evo_sync_branch' ) );

// 5. EVO continua mandando em preco/descricao; o texto local fica.
require_once $root . '/contorno-evo-sync/includes/class-frontend.php';
$plan = array( 'id' => 'black', 'name' => 'Black', 'benefits' => array( 'Meu beneficio' ), 'price' => 99.0 );
$out  = Contorno_Evo_Frontend::apply( $plan, array( 'value' => 129.9, 'description' => 'Oficial EVO', 'url_sale' => 'https://x/y' ) );
check( 'EVO: preco, descricao e checkout vem da EVO', 129.9 === (float) $out['price'] && 'Oficial EVO' === $out['description'] && 'https://x/y' === $out['checkout_url'] );
check( 'EVO: beneficios locais intactos', array( 'Meu beneficio' ) === $out['benefits'] );

// --force continua sendo o unico caminho de reaplicar o dataset (so teste local).
run_migrate( $dataset, true );
check( '--force (somente teste local) reaplica o dataset', 'Teste Dataset' === $GLOBALS['posts'][ $id ]->post_title );

echo $fail ? "\n$fail FALHA(S)\n" : "\nTodos os testes passaram.\n";
exit( $fail ? 1 : 0 );
