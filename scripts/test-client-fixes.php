<?php
/**
 * Testes das correcoes da homologacao: planos ("Ver mais" + centralizacao),
 * blog (sem cards ficticios) e link direto do mapa. Codigo REAL do plugin,
 * WordPress simulado. Sem banco, sem rede.   scripts/test-client-fixes.sh
 *
 * O comportamento visual (centralizacao, abrir/fechar) foi conferido em
 * navegador com o CSS e o JS reais; aqui ficam as garantias de codigo.
 */
declare( strict_types = 1 );

$plugin = dirname( __DIR__ ) . '/wp-content/plugins/contorno-core';
define( 'ABSPATH', true );
define( 'CONTORNO_CORE_DIR', $plugin . '/' );

$fail = 0;
function check( string $name, bool $ok, string $extra = '' ): void {
	global $fail;
	echo ( $ok ? '[OK]     ' : '[FALHOU] ' ) . $name . ( '' !== $extra && ! $ok ? "  ($extra)" : '' ) . "\n";
	if ( ! $ok ) {
		++$fail;
	}
}
function src( string $rel ): string {
	global $plugin;
	return str_replace( "\r\n", "\n", (string) file_get_contents( $plugin . '/' . $rel ) );
}

/* ------------------------------------------------------------------
 * 1. Planos
 * ---------------------------------------------------------------- */
echo "== Planos\n";
$units = src( 'includes/shortcodes/units.php' );
$js    = src( 'assets/js/units-carousel.js' );
$css   = src( 'assets/css/components.css' );

check( 'botao "Ver mais" com aria-expanded, aria-controls e rotulos mais/menos', str_contains( $units, 'data-contorno-plan-toggle' ) && str_contains( $units, 'aria-expanded="false"' ) && str_contains( $units, 'aria-controls=' ) && str_contains( $units, "'Ver mais'" ) && str_contains( $units, "'Ver menos'" ) );
check( 'botao nasce `hidden` (sem JavaScript todos os beneficios continuam visiveis)', str_contains( $units, ' hidden><?php esc_html_e( \'Ver mais\'' ) );
check( 'so os primeiros 3 beneficios ficam no card reduzido', str_contains( $units, "define( 'CONTORNO_PLAN_BENEFITS_VISIBLE', 3 )" ) && str_contains( $units, 'contorno-plan__extra' ) );
check( 'JS atualiza aria-expanded e o texto ao abrir e recolher', str_contains( $js, "setAttribute('aria-expanded'" ) && str_contains( $js, 'labelLess' ) && str_contains( $js, 'labelMore' ) );
check( 'CSS esconde o excedente so em cards com JS (.is-collapsible)', str_contains( $css, '.contorno-plan.is-collapsible:not(.is-open) .contorno-plan__extra' ) );
check( 'CSS desktop: linha de planos centralizada (flex + justify-content:center)', (bool) preg_match( '/@media \(min-width: 1024px\) \{[^@]*?\.contorno-plans \{[^}]*display: flex;[^}]*justify-content: center;/s', $css ) );
check( 'CSS nao usa mais grade fixa de 3 colunas no desktop', ! preg_match( '/\.contorno-plans \{[^}]*grid-template-columns: repeat\(3/s', $css ) );
check( 'preco, promocao, descricao e botao de contratacao continuam no card', str_contains( $units, 'contorno-plan__from' ) && str_contains( $units, 'contorno-plan__price' ) && str_contains( $units, 'contorno_plan_cta_target' ) && str_contains( $units, 'contorno-plan__description' ) );

/* ------------------------------------------------------------------
 * 2. Blog
 * ---------------------------------------------------------------- */
echo "== Blog\n";
$blog = src( 'includes/shortcodes/blog.php' );
check( 'cards ficticios de 2024 removidos', ! str_contains( $blog, 'São José dos Campos' ) && ! str_contains( $blog, 'contorno_blog_placeholder_cards' ) && ! str_contains( $blog, '/blog/inauguracao.jpg' ) );
check( 'nenhuma referencia a imagens assets/img/blog (pasta inexistente)', ! str_contains( $blog, 'assets/img/blog' ) && ! is_dir( $plugin . '/assets/img/blog' ) );

$GLOBALS['shortcodes'] = array();
$GLOBALS['posts']      = array();
function contorno_add_shortcode( string $tag, callable $cb ): void { $GLOBALS['shortcodes'][ $tag ] = $cb; }
function shortcode_atts( array $d, array $a, string $t = '' ): array { return array_merge( $d, $a ); }
function get_posts( array $a ): array { return $GLOBALS['posts']; }
function get_page_by_path( string $p ) { return null; }
function home_url( string $p = '' ): string { return 'https://site.test' . $p; }
function is_front_page(): bool { return false; }
function contorno_section_open( string $n, array $o = array() ): string { return '<section>'; }
function contorno_section_close(): string { return '</section>'; }
function contorno_section_header( ...$a ): string { return '<header></header>'; }
function contorno_button( string $l, string $u, string $v = '', array $o = array() ): string { return '<a href="' . $u . '">' . $l . '</a>'; }
function __( string $t, string $d = '' ): string { return $t; }
function esc_html_e( string $t, string $d = '' ): void { echo htmlspecialchars( $t ); }
function esc_html( $t ): string { return htmlspecialchars( (string) $t ); }
function esc_url( $t ): string { return (string) $t; }
function esc_attr( $t ): string { return htmlspecialchars( (string) $t ); }
function get_the_post_thumbnail_url( int $id, string $s = '' ) { return 'https://site.test/img-' . $id . '.jpg'; }
function get_the_terms( int $id, string $t ) { return false; }
function get_permalink( $id ) { return 'https://site.test/p/' . $id; }
function get_the_date( string $f = '', $id = 0 ): string { return '20 de outubro de 2026'; }
function get_the_title( $id = 0 ): string { return 'Titulo real ' . $id; }
function get_the_excerpt( $id = 0 ): string { return 'Resumo real'; }
function wp_strip_all_tags( string $s ): string { return strip_tags( $s ); }

require_once $plugin . '/includes/shortcodes/blog.php';
$render = $GLOBALS['shortcodes']['contorno_blog_preview'];

$html = $render( array() );
check( 'sem publicacoes: estado vazio apropriado, sem cards', str_contains( $html, 'contorno-blog-empty' ) && str_contains( $html, 'Ainda não há publicações' ) && ! str_contains( $html, 'contorno-post-card' ) );
check( 'sem publicacoes: o botao "Ver o blog" continua', str_contains( $html, 'Ver o blog' ) );

$GLOBALS['posts'] = array( (object) array( 'ID' => 7 ), (object) array( 'ID' => 8 ) );
$html             = $render( array() );
check( 'com publicacoes reais: mostra os cards do WordPress, sem estado vazio', substr_count( $html, 'contorno-post-card motion-card' ) === 2 && str_contains( $html, 'Titulo real 7' ) && ! str_contains( $html, 'contorno-blog-empty' ) );
check( 'com publicacoes: nenhuma matéria fixa de 2024', ! str_contains( $html, 'São José' ) && ! str_contains( $html, '2024' ) );

/* ------------------------------------------------------------------
 * 3. Link direto do mapa
 * ---------------------------------------------------------------- */
echo "== Mapa\n";
$GLOBALS['fields'] = array();
define( 'CONTORNO_CPT_UNIT', 'unidade' );
define( 'CONTORNO_CPT_CTN', 'ctn' );
function add_action( ...$a ): void {}
function add_filter( ...$a ): void {}
function wp_parse_url( string $u, int $c = -1 ) { return parse_url( $u, $c ); }
function esc_url_raw( string $u ): string { return $u; }
function contorno_field_text( string $n, ?int $id = null ): string { return (string) ( $GLOBALS['fields'][ $n ] ?? '' ); }
require_once $plugin . '/includes/data/units.php';

$fer = 'https://maps.app.goo.gl/HfumxqvZpptdSNMc8';
$xan = 'https://maps.app.goo.gl/bSG7eLCexbuA5oLP9';

$GLOBALS['fields'] = array( 'map_url' => $fer, 'maps_query' => 'Av. Joaquim José Diniz, 500' );
check( 'Fernão Dias: botao usa o link direto informado pelo cliente', $fer === contorno_maps_url( 1 ) );
$GLOBALS['fields'] = array( 'map_url' => $xan, 'maps_query' => 'Praça Belarmino de Souza, 110' );
check( 'Xangri-lá: botao usa o link direto informado pelo cliente', $xan === contorno_maps_url( 1 ) );
$GLOBALS['fields'] = array( 'maps_query' => 'Av. Jovino Fernandes, 1121' );
check( 'demais unidades: busca por endereco preservada', 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( 'Av. Jovino Fernandes, 1121' ) === contorno_maps_url( 1 ) );
$GLOBALS['fields'] = array( 'address' => 'Rua A, 10' );
check( 'sem link e sem maps_query: cai no endereco/titulo como antes', str_contains( contorno_maps_url( 1 ), 'query=' ) );
foreach ( array( 'http://maps.app.goo.gl/x', 'https://evil.example/maps', 'javascript:alert(1)', 'https://www.google.com/search?q=1', 'https://goo.gl/abc' ) as $bad ) {
	$GLOBALS['fields'] = array( 'map_url' => $bad, 'maps_query' => 'Rua B, 20' );
	check( 'link invalido ignorado (cai na busca): ' . $bad, str_contains( contorno_maps_url( 1 ), 'google.com/maps/search' ) );
}
$GLOBALS['fields'] = array( 'map_url' => 'https://www.google.com/maps/place/Foo/@-19.9,-43.9,17z', 'maps_query' => 'x' );
check( 'link google.com/maps completo tambem e aceito', str_starts_with( contorno_maps_url( 1 ), 'https://www.google.com/maps/place/' ) );
check( 'campo "Link direto do mapa" registrado para unidade no admin', str_contains( src( 'includes/meta/registry.php' ), "'map_url'      => array(" ) );

$dataset = json_decode( src( 'data/dataset.json' ), true );
$patches = array();
foreach ( (array) ( $dataset['oneTimePatches'] ?? array() ) as $p ) { $patches[ $p['slug'] ] = $p; }
$slugs = array_column( (array) $dataset['units'], 'title', 'slug' );
check( 'dataset: Fernão Dias recebe exatamente o link informado', ( $patches['fernao-dias']['value'] ?? '' ) === $fer && 'map_url' === ( $patches['fernao-dias']['field'] ?? '' ) );
check( 'dataset: Xangri-lá recebe exatamente o link informado', ( $patches['xangri-la']['value'] ?? '' ) === $xan && 'map_url' === ( $patches['xangri-la']['field'] ?? '' ) );
check( 'mapeamento confere: slug fernao-dias = "Fernão Dias" e xangri-la = "Xangri-Lá"', 'Fernão Dias' === ( $slugs['fernao-dias'] ?? '' ) && 'Xangri-Lá' === ( $slugs['xangri-la'] ?? '' ), json_encode( array( $slugs['fernao-dias'] ?? null, $slugs['xangri-la'] ?? null ) ) );
check( 'so essas duas unidades recebem ajuste de mapa', 2 === count( $patches ) );

echo $fail ? "\n$fail FALHA(S)\n" : "\nTodos os testes passaram.\n";
exit( $fail ? 1 : 0 );
