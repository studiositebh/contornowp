<?php
/**
 * Testes de "plano alterado na EVO aparece no site" — rodados por
 * scripts/test-evo-plans.sh.
 *
 * Carrega o codigo REAL de class-mapping, class-sync, class-frontend,
 * class-settings e class-cron com stubs do WordPress. Nao toca rede, banco
 * nem staging. Os nomes e valores vem da validacao de 16/10/2026.
 *
 * O que estes testes impedem:
 *   - preco do card (dataset) ganhar do preco da EVO (Fit R$ 49,90 = taxa);
 *   - card sem preco nas unidades novas;
 *   - nome interno da EVO ("ASSINATURA BLACK") substituir o nome do card;
 *   - plano inativado na EVO sumir sem o substituto de mesmo nome assumir;
 *   - substituto escolhido errado (inativo, de outro nome, ja usado, mais velho);
 *   - vinculo explicito do editor ser trocado;
 *   - contratos internos ativos (BOLSA, COLABORADOR, GYMPASS...) virarem card;
 *   - lote de cron grande demais ou unidade pulada no rodizio.
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

$plugin = rtrim( (string) ( $argv[1] ?? '' ), '/\\' );

if ( '' === $plugin || ! is_file( $plugin . '/includes/class-sync.php' ) ) {
	fwrite( STDERR, "Uso: php test-evo-plans.php <contorno-evo-sync>\n" );
	exit( 1 );
}

/* =============================================================
 * Stubs do WordPress / contorno-core
 * =========================================================== */

define( 'ABSPATH', true );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'CONTORNO_EVO_DEFAULT_BASE_URL', 'https://evo-integracao-api.w12app.com.br' );
define( 'CONTORNO_EVO_META_MEMBERSHIPS', '_contorno_evo_memberships' );
define( 'CONTORNO_EVO_META_LINKS', '_contorno_evo_links' );
define( 'CONTORNO_EVO_META_BRANCH', '_contorno_evo_sync_branch' );
define( 'CONTORNO_EVO_CRON_HOOK', 'contorno_evo_sync_cron' );

$GLOBALS['meta'] = array();
$GLOBALS['opt']  = array();

function __( string $t, string $d = '' ): string { return $t; }
function _n( string $s, string $p, int $n, string $d = '' ): string { return 1 === $n ? $s : $p; }
function remove_accents( string $s ): string { return strtr( $s, array( 'á' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c', 'Á' => 'A', 'É' => 'E' ) ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_post_meta( int $id, string $key, bool $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function get_option( string $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function is_admin(): bool { return false; }
function wp_doing_cron(): bool { return false; }
function add_filter( ...$a ): void {}
function add_action( ...$a ): void {}
function contorno_meta_key( string $name ): string { return '_contorno_' . $name; }
function contorno_field_text( string $name, int $post_id, string $default = '' ): string { return $default; }
function contorno_format_price( float $v ): string { return 'R$ ' . number_format( $v, 2, ',', '.' ); }

require $plugin . '/includes/class-settings.php';
require $plugin . '/includes/class-mapping.php';
require $plugin . '/includes/class-sync.php';
require $plugin . '/includes/class-frontend.php';
require $plugin . '/includes/class-cron.php';

$fail = 0;
$n    = 0;
function check( bool $cond, string $label ): void {
	global $fail, $n;
	++$n;
	if ( $cond ) {
		echo "  [OK]       $label\n";
	} else {
		echo "  [FALHOU]   $label\n";
		$fail = 1;
	}
}

/** Membership no formato gravado pelo sync (normalize + status). */
function m( int $id, string $name, float $value, string $status = 'active', float $promo = 0.0, int $months = 0, string $updated = '' ): array {
	return array( 'id_membership' => $id, 'id_branch' => 24, 'name' => $name, 'display_name' => $name, 'value' => $value, 'promo_value' => $promo, 'promo_months' => $months, 'promo_days' => 0, 'status' => $status, 'update_date' => $updated, 'description' => '', 'url_sale' => '', 'duration' => 0, 'max_installments' => 0 );
}

function checkout( int $branch, int $id ): string {
	return "https://checkout.contornodocorpo.com.br/contornodocorpo/$branch/site/landing-page/checkout/$id/0";
}

/* =============================================================
 * Precos e nomes (Barbacena, filial 24)
 * =========================================================== */

echo "Preco e nome do card\n";

$barbacena = array(
	5812 => m( 5812, 'ASSINATURA BLACK', 179.9, 'active', 9.9, 1 ),
	5756 => m( 5756, 'ASSINATURA SLIM ', 149.9 ),
	5700 => m( 5700, 'ASSINATURA FIT', 199.9 ),
	23   => m( 23, 'PREMIUM ', 199.9 ),
	3558 => m( 3558, 'COLABORADOR', 0.0 ),
	139  => m( 139, 'CLIENTE GYMPASS', 0.0 ),
	6564 => m( 6564, 'ASSINATURA BLACK', 169.9 ),
);
$GLOBALS['meta'][1][ CONTORNO_EVO_META_MEMBERSHIPS ] = json_encode( array_values( $barbacena ) );

$local = array(
	array( 'id' => 'black', 'name' => 'Black', 'price' => 9.9, 'checkout_url' => checkout( 24, 5812 ) ),
	array( 'id' => 'slim', 'name' => 'Slim', 'price' => 169.9, 'checkout_url' => checkout( 24, 5756 ) ),
	array( 'id' => 'fit', 'name' => 'Fit', 'price' => 49.9, 'price_label' => 'R$ 209,90 /mês Taxa de matrícula: R$ 49,90', 'checkout_url' => checkout( 24, 5700 ) ),
);

$out = Contorno_Evo_Frontend::merge( $local, 1 );
$by  = array_column( $out, null, 'id' );

check( 3 === count( $out ), 'so os 3 cards do site: contratos internos ativos nao viram card (' . count( $out ) . ')' );
check( 'Black' === $by['black']['name'] && 'Fit' === $by['fit']['name'], 'nome do card do site e mantido (nao vira "ASSINATURA ...")' );
check( 199.9 === (float) $by['fit']['price'], 'Fit: preco da EVO R$ 199,90 no lugar da taxa de R$ 49,90' );
check( 149.9 === (float) $by['slim']['price'], 'Slim: preco da EVO R$ 149,90 no lugar de R$ 169,90' );
check( 9.9 === (float) $by['black']['price'] && 179.9 === (float) $by['black']['price_from'], 'Black: promo R$ 9,90 com "de R$ 179,90"' );
check( str_contains( (string) $by['black']['price_note'], '179,90' ), 'Black: nota "no 1º mes, depois R$ 179,90"' );

// Unidade nova: card sem preco nem rotulo (Lafaiete, filial 11).
$GLOBALS['meta'][2][ CONTORNO_EVO_META_MEMBERSHIPS ] = json_encode( array( m( 5688, 'ASSINATURA FIT', 169.9 ) ) );
$out = Contorno_Evo_Frontend::merge( array( array( 'id' => 'fit', 'name' => 'FIT', 'price' => 0, 'price_label' => '', 'checkout_url' => checkout( 11, 5688 ) ) ), 2 );
check( 169.9 === (float) $out[0]['price'], 'card sem preco ganha o preco da EVO' );

// Sem dados gravados: lista local intacta (nunca pagina sem plano).
check( $local === Contorno_Evo_Frontend::merge( $local, 99 ), 'sem sync ainda: cards locais intactos' );

// Opcao explicita de mostrar contratos sem card continua funcionando.
$GLOBALS['opt']['contorno_evo_settings'] = array( 'show_unlisted_plans' => true );
$out = Contorno_Evo_Frontend::merge( $local, 1 );
check( count( $out ) > 3, 'show_unlisted_plans ligado acrescenta contratos sem card' );
unset( $GLOBALS['opt']['contorno_evo_settings'] );

// Opcao antiga gravada no banco (add_new_plans = true) nao pode reativar isso.
$GLOBALS['opt']['contorno_evo_settings'] = array( 'add_new_plans' => true );
check( 3 === count( Contorno_Evo_Frontend::merge( $local, 1 ) ), 'add_new_plans antigo (true no banco) e ignorado' );
unset( $GLOBALS['opt']['contorno_evo_settings'] );

// Unidade nova sem nenhum card (Lagoa da Prata, Campos de Ourique).
$GLOBALS['meta'][4][ CONTORNO_EVO_META_MEMBERSHIPS ] = json_encode( array_values( $barbacena + array( 7000 => m( 7000, 'ASSINATURA FIT', 189.9, 'inactive' ) ) ) );
$out = Contorno_Evo_Frontend::merge( array(), 4 );
check( array( 'Black', 'Slim', 'Fit' ) === array_column( $out, 'name' ), 'unidade sem cards: Black, Slim e Fit da EVO, nessa ordem' );
check( array( 'evo-black', 'evo-slim', 'evo-fit' ) === array_column( $out, 'id' ), 'ids estaveis para a matricula (evo-black...)' );
check( 6564 === $out[0]['evo_membership_id'], 'Black: dois ativos sem data -> o mais novo (maior id)' );
check( 199.9 === (float) $out[2]['price'], 'Fit: o ativo (inativo de R$ 189,90 ignorado)' );
check( ! in_array( 'Premium', array_column( $out, 'name' ), true ) && 3 === count( $out ), 'sem PREMIUM/COLABORADOR/GYMPASS' );
check( array() === Contorno_Evo_Frontend::merge( array(), 98 ), 'unidade sem cards e sem sync: continua vazia' );

check( 'CTN Slim' === Contorno_Evo_Frontend::pretty_name( 'ASSINATURA CTN SLIM ' ), 'nome de plano novo: "ASSINATURA CTN SLIM " -> "CTN Slim"' );
check( 'Prime' === Contorno_Evo_Frontend::pretty_name( 'PRIME ' ), 'nome de plano novo: "PRIME " -> "Prime"' );

/* =============================================================
 * Plano substituto (Divinopolis Sidil, filial 61)
 * =========================================================== */

echo "\nPlano inativado na EVO -> substituto de mesmo nome\n";

$sidil = array(
	5450 => m( 5450, 'ASSINATURA BLACK', 179.9, 'inactive', 9.9, 1 ),
	5789 => m( 5789, 'ASSINATURA SLIM ', 169.9 ),
	5271 => m( 5271, 'ASSINATURA FIT', 199.9, 'inactive' ),
	5733 => m( 5733, 'ASSINATURA FIT', 209.9, 'active', 0.0, 0, '2026-09-01' ),
	5524 => m( 5524, 'PRIME ', 249.9 ),
);
$plans = array(
	array( 'id' => 'black', 'name' => 'Black', 'checkout_url' => checkout( 61, 5450 ) ),
	array( 'id' => 'slim', 'name' => 'Slim', 'checkout_url' => checkout( 61, 5789 ) ),
	array( 'id' => 'fit', 'name' => 'FIT', 'checkout_url' => checkout( 61, 5271 ) ),
);

$r = Contorno_Evo_Sync::relink( $plans, array(), $sidil );
check( 5733 === ( $r['links']['fit'] ?? 0 ), 'Fit 5271 inativo -> 5733 (ASSINATURA FIT ativo)' );
check( ! isset( $r['links']['black'] ), 'Black 5450 inativo sem Black ativo: nao inventa substituto' );
check( ! isset( $r['links']['slim'] ), 'Slim ativo: vinculo intocado' );
check( 1 === count( $r['changes'] ) && 5271 === $r['changes'][0]['from'], 'uma troca registrada (para o log)' );

$GLOBALS['meta'][3][ CONTORNO_EVO_META_MEMBERSHIPS ] = json_encode( array_values( $sidil ) );
$GLOBALS['meta'][3][ CONTORNO_EVO_META_LINKS ]       = $r['links'];
$out = array_column( Contorno_Evo_Frontend::merge( $plans, 3 ), null, 'id' );
check( isset( $out['fit'] ) && 209.9 === (float) $out['fit']['price'], 'site: card Fit continua, agora com R$ 209,90' );
check( ! isset( $out['black'] ), 'site: Black inativo sem substituto sai do site' );
check( 5733 === Contorno_Evo_Mapping::membership_for_plan( $plans[2], Contorno_Evo_Mapping::links( 3 ) ), 'checkout usa o mesmo vinculo novo (5733)' );

// Mais de um candidato: vence o updateDate mais recente, depois o maior id.
$many = array(
	1 => m( 1, 'ASSINATURA BLACK', 179.9, 'inactive' ),
	2 => m( 2, 'ASSINATURA BLACK', 159.9, 'active', 0.0, 0, '2026-01-10' ),
	3 => m( 3, 'Assinatura Black', 149.9, 'active', 0.0, 0, '2026-08-20' ),
	4 => m( 4, 'ASSINATURA BLACK', 139.9, 'inactive', 0.0, 0, '2026-10-01' ),
	5 => m( 5, 'BOLSA BLACK (12 MESES)', 0.0, 'active', 0.0, 0, '2026-10-02' ),
);
$r = Contorno_Evo_Sync::relink( array( array( 'id' => 'black', 'checkout_url' => checkout( 1, 1 ) ) ), array(), $many );
check( 3 === ( $r['links']['black'] ?? 0 ), 'varios candidatos: o atualizado mais recente (ignora inativo e "BOLSA BLACK")' );

// Candidato ja usado por outro card nao e reaproveitado.
$r = Contorno_Evo_Sync::relink(
	array( array( 'id' => 'a', 'checkout_url' => checkout( 1, 1 ) ), array( 'id' => 'b', 'checkout_url' => checkout( 1, 3 ) ) ),
	array(),
	$many
);
check( 2 === ( $r['links']['a'] ?? 0 ), 'membership ja usado por outro card nao e candidato' );

// Plano que sumiu da EVO (missing-from-evo) tambem passa para o substituto.
$gone = array( 10 => m( 10, 'ASSINATURA SLIM', 129.9, 'missing-from-evo' ), 11 => m( 11, 'ASSINATURA SLIM', 139.9 ) );
$r    = Contorno_Evo_Sync::relink( array( array( 'id' => 'slim', 'checkout_url' => checkout( 1, 10 ) ) ), array(), $gone );
check( 11 === ( $r['links']['slim'] ?? 0 ), 'plano ausente da EVO -> substituto' );

// Vinculo explicito do editor nunca e trocado.
$r = Contorno_Evo_Sync::relink( array( array( 'id' => 'fit', 'evo_membership_id' => 5271 ) ), array(), $sidil );
check( array() === $r['changes'], 'vinculo explicito (evo_membership_id) nao e trocado' );

/* =============================================================
 * Lotes do cron
 * =========================================================== */

echo "\nLotes do cron\n";

$ids  = range( 101, 166 ); // 66 unidades
$seen = array();
$cur  = 0;
$runs = 0;
do {
	$b    = Contorno_Evo_Cron::batch( $ids, $cur, 60 );
	$seen = array_merge( $seen, $b['batch'] );
	$cur  = $b['cursor'];
	++$runs;
	$max  = max( $max ?? 0, count( $b['batch'] ) );
} while ( 0 !== $cur && $runs < 100 );

check( $ids === $seen, '60 min: todas as 66 unidades, cada uma uma vez, em ordem' );
check( $runs <= 12, "ciclo cabe no intervalo: $runs lotes de 5 min <= 60 min" );
check( $max <= 6, "lote pequeno: no maximo $max unidades (~30 chamadas)" );
check( array( 'batch' => array( 101, 102, 103, 104, 105, 106 ), 'cursor' => 6 ) === Contorno_Evo_Cron::batch( $ids, 999, 60 ), 'cursor invalido (unidade removida) volta ao inicio' );
check( array( 'batch' => array(), 'cursor' => 0 ) === Contorno_Evo_Cron::batch( array(), 5, 60 ), 'sem unidades: lote vazio' );
check( 11 === count( Contorno_Evo_Cron::batch( $ids, 0, 1 )['batch'] ), 'intervalo abaixo do minimo usa o minimo (30 min)' );
check( 11 === count( Contorno_Evo_Cron::batch( $ids, 0, 30 )['batch'] ), '30 min: lotes de 11' );

/* =============================================================
 * Agenda automatica (contorno-core/includes/data/evo.php)
 * =========================================================== */

echo "\nAgenda: aparece sozinha quando a filial tem aulas na EVO\n";

$GLOBALS['fields'] = array();
$GLOBALS['weeks']  = array();
function contorno_field( string $name, int $post_id ) { return $GLOBALS['fields'][ $post_id ][ $name ] ?? ''; }
function get_the_ID() { return 0; }
function get_post_field( string $f, int $id ) { return 'slug-' . $id; }
final class Contorno_Evo_Schedule {
	public static function week( int $branch ): ?array { return $GLOBALS['weeks'][ $branch ] ?? null; }
}
// contorno_field_text ja existe (stub acima, so devolve o padrao): o evo.php
// real e carregado num escopo onde os campos vem de $GLOBALS['fields'].
$core = dirname( $plugin ) . '/contorno-core/includes/data/evo.php';
$src  = (string) file_get_contents( $core );
$src  = str_replace( array( '<?php', 'contorno_field_text(' ), array( '', 'test_field_text(' ), $src );
function test_field_text( string $name, int $post_id, string $default = '' ): string { return (string) ( $GLOBALS['fields'][ $post_id ][ $name ] ?? $default ); }
eval( $src ); // phpcs:ignore -- teste: carrega o arquivo real com o stub de campo.

$GLOBALS['fields'][10] = array( 'evo_branch_id' => '11' );                          // Lafaiete: chave desligada
$GLOBALS['fields'][11] = array( 'evo_branch_id' => '1' );                           // Sao Lucas: sem aulas na EVO
$GLOBALS['fields'][12] = array( 'evo_branch_id' => '8', 'classes_enabled' => '1' ); // Betim: chave ligada
$GLOBALS['fields'][13] = array( 'evo_branch_id' => '11', 'classes_mode' => 'iframe' );
$GLOBALS['weeks'][11]  = array( 'total' => 18 );
$GLOBALS['weeks'][8]   = array( 'total' => 26 );

check( str_contains( contorno_schedule_url( 10 ), '/contornodocorpo/11/' ), 'chave desligada + 18 aulas na EVO: agenda aparece' );
check( '' === contorno_schedule_url( 11 ), 'filial sem aulas na EVO: sem secao' );
check( str_contains( contorno_schedule_url( 12 ), '/contornodocorpo/8/' ), 'chave ligada: como antes' );
check( '' === contorno_schedule_url( 13 ), 'modo "sempre iframe" continua exigindo a chave' );
unset( $GLOBALS['weeks'][11] );
check( '' === contorno_schedule_url( 10 ), 'sem cache ainda (cron nao rodou): sem secao, sem chamar a API' );

/* =============================================================
 * Padroes
 * =========================================================== */

echo "\nPadroes\n";
$d = Contorno_Evo_Settings::defaults();
check( true === $d['auto_sync'], 'sincronizacao automatica ligada por padrao' );
check( 60 === $d['interval_minutes'], 'ciclo padrao de 60 min' );
check( false === $d['show_unlisted_plans'] && ! isset( $d['add_new_plans'] ), 'contratos sem card desligados por padrao' );

echo "\n$n verificacoes, " . ( $fail ? 'COM FALHAS' : 'todas OK' ) . "\n";
exit( $fail );
