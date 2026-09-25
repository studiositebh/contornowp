<?php
/**
 * Testes do checkout nativo — rodados por scripts/test-evo-native-checkout.sh.
 *
 * Carrega o codigo REAL do contorno-evo-sync com stubs do WordPress e um
 * cliente HTTP falso. Nenhum teste toca a rede, o banco ou o staging: as
 * respostas da EVO vem de fixtures declaradas aqui embaixo.
 *
 * O que estes testes existem para impedir (cada um nasceu de um risco real do
 * fluxo de pagamento, nao de cobertura por cobertura):
 *
 *   - preco vindo do navegador virar preco cobrado;
 *   - idMembership/idBranch vindos da query mudarem o que e vendido;
 *   - PAN ou CVV chegarem ao WordPress;
 *   - duplo clique, refresh ou retry gerarem duas vendas;
 *   - resposta ambigua da EVO virar fallback (e cobranca dobrada);
 *   - mensagem tecnica, token ou dado pessoal vazarem para o visitante;
 *   - checkout nativo ligar sozinho antes da homologacao;
 *   - o checkout externo ser removido.
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

$plugin = rtrim( (string) ( $argv[1] ?? '' ), '/\\' );
$core   = rtrim( (string) ( $argv[2] ?? '' ), '/\\' );

if ( '' === $plugin || ! is_dir( $plugin ) ) {
	fwrite( STDERR, "Uso: php test-evo-native-checkout.php <contorno-evo-sync> <contorno-core>\n" );
	exit( 1 );
}

/* =============================================================
 * Stubs do WordPress
 * =========================================================== */

define( 'ABSPATH', true );
define( 'CONTORNO_EVO_VERSION', 'test' );
define( 'CONTORNO_EVO_DEFAULT_BASE_URL', 'https://evo-integracao-api.w12app.com.br' );
define( 'CONTORNO_EVO_META_BRANCH', '_contorno_evo_sync_branch' );
define( 'CONTORNO_EVO_META_MEMBERSHIPS', '_contorno_evo_memberships' );
define( 'CONTORNO_EVO_META_SNAPSHOT', '_contorno_evo_snapshot' );
define( 'CONTORNO_EVO_META_LINKS', '_contorno_evo_links' );
define( 'CONTORNO_CPT_UNIT', 'unidade' );
define( 'CONTORNO_CPT_CTN', 'ctn' );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['opt'] = array();
$GLOBALS['tr']  = array();
$GLOBALS['log'] = array();

function __( string $t, string $d = '' ): string { return $t; }
function _n( string $s, string $p, int $n, string $d = '' ): string { return 1 === $n ? $s : $p; }
function esc_url_raw( string $u, array $p = array() ): string { return $u; }
function esc_html( string $t ): string { return $t; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_unslash( $v ) { return $v; }
function home_url( string $p = '' ): string { return 'https://contornowp.voceconecta.com.br' . $p; }
function rest_url( string $p = '' ): string { return home_url( '/wp-json/' . $p ); }
function wp_create_nonce( string $a ): string { return 'nonce-' . $a; }
function wp_verify_nonce( string $n, string $a ) { return $n === 'nonce-' . $a ? 1 : false; }
function apply_filters( string $h, $v, ...$a ) {
	// Sem pausa entre chamadas: o ritmo real (1,6 s por requisicao mais
	// backoff de ate 60 s) faria esta suite levar minutos e deixar de ser
	// rodada. O valor de producao continua sendo o padrao do cliente.
	return 'contorno_evo_pacing' === $h ? 0.0 : $v;
}
function sanitize_text_field( string $v ): string { return trim( (string) preg_replace( '/[\r\n\t]+/', ' ', strip_tags( $v ) ) ); }
function sanitize_textarea_field( string $v ): string { return trim( strip_tags( $v ) ); }
function sanitize_key( string $v ): string { return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) ); }
function sanitize_title( string $v ): string { return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $v ) ), '-' ); }
function sanitize_email( string $v ): string { return filter_var( trim( $v ), FILTER_VALIDATE_EMAIL ) ?: ''; }
function is_email( string $v ) { return (bool) filter_var( $v, FILTER_VALIDATE_EMAIL ); }
function wp_parse_url( string $u, int $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function untrailingslashit( string $v ): string { return rtrim( $v, '/' ); }
function add_query_arg( $a, $u = '' ) {
	if ( ! is_array( $a ) ) { $a = array( $a => func_get_arg( 1 ) ); $u = func_get_arg( 2 ) ?? ''; }
	return $u . ( str_contains( (string) $u, '?' ) ? '&' : '?' ) . http_build_query( $a );
}
function get_option( string $n, $d = false ) { return $GLOBALS['opt'][ $n ] ?? $d; }
function add_option( string $n, $v, $x = '', $a = true ) { $GLOBALS['opt'][ $n ] = $v; return true; }
function update_option( string $n, $v, $a = null ) { $GLOBALS['opt'][ $n ] = $v; return true; }
function get_transient( string $k ) { return $GLOBALS['tr'][ $k ] ?? false; }
function set_transient( string $k, $v, int $t = 0 ) { $GLOBALS['tr'][ $k ] = $v; return true; }
function delete_transient( string $k ) { unset( $GLOBALS['tr'][ $k ] ); return true; }
function get_post( $id ) { return null; }
function get_the_title( $p = null ): string { return 'Lourdes'; }
function get_page_by_path( string $p ) { return null; }
function get_permalink( $p = null ): string { return home_url( '/matricula/confirmacao/' ); }
function get_post_field( string $f, $id ) { return 'lourdes'; }
function get_post_meta( $id, string $k, bool $s = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, string $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, string $k ) { unset( $GLOBALS['meta'][ $id ][ $k ] ); return true; }
function contorno_meta_key( string $n ): string { return '_contorno_' . $n; }
function contorno_field_text( string $n, $id = null ): string { return (string) ( $GLOBALS['fields'][ $n ] ?? '' ); }
function contorno_format_price( $v ): string { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); }
function contorno_evo_branch_for_slug( string $s ): string { return 'lourdes' === $s ? '21' : ''; }
function wp_safe_remote_request( string $u, array $a ) { return $GLOBALS['http']( $u, $a ); }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function is_wp_error( $t ): bool { return $t instanceof WP_Error; }
function wp_get_raw_referer() { return 'https://contornowp.voceconecta.com.br/matricula/'; }
function wp_salt( string $scheme = 'auth' ): string { return 'salt-de-teste-' . $scheme; }

class WP_Error {
	public function __construct( private string $code = '', private string $message = '' ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}

class WP_Post {
	public int $ID = 0;
	public string $post_name = '';
	public string $post_type = 'unidade';
	public string $post_status = 'publish';
}

class WP_REST_Response {
	public function __construct( public mixed $data = null, public int $status = 200 ) {}
}

class WP_REST_Request {
	public function __construct( private array $params = array() ) {}
	public function get_param( string $k ) { return $this->params[ $k ] ?? null; }
	public function set_param( string $k, $v ): void { $this->params[ $k ] = $v; }
}

function register_rest_route( ...$a ) {}
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function current_user_can( string $c ): bool { return false; }
function wp_doing_cron(): bool { return false; }
function is_admin(): bool { return false; }
function get_posts( array $a ): array { return array(); }

/** Unidade/CTN de teste: Lourdes, filial 21, tres planos reais do dataset. */
$GLOBALS['meta']   = array();
$GLOBALS['fields'] = array( 'evo_branch_id' => '21' );

function contorno_get_unit_by_slug( string $slug ) {
	if ( 'lourdes' !== $slug ) {
		return null;
	}

	$post            = new WP_Post();
	$post->ID        = 101;
	$post->post_name = 'lourdes';

	return $post;
}

function contorno_get_ctn_by_slug( string $slug ) { return null; }

/* =============================================================
 * Codigo real sob teste
 * =========================================================== */

require_once $plugin . '/includes/class-crypto.php';
require_once $plugin . '/includes/class-settings.php';
require_once $plugin . '/includes/class-log.php';
require_once $plugin . '/includes/class-client.php';
require_once $plugin . '/includes/class-mapping.php';
require_once $plugin . '/includes/class-gateway.php';
require_once $plugin . '/includes/class-checkout.php';
require_once $plugin . '/includes/class-checkout-rest.php';

/* =============================================================
 * Fixtures da EVO
 * =========================================================== */

const LOURDES_BLACK = 5809;

/** GET /api/v3/membership — Black de Lourdes, com promocao vigente. */
function fx_membership( float $value = 219.90, float $promo = 9.90, bool $inactive = false ): array {
	return array(
		'idMembership'            => LOURDES_BLACK,
		'idBranch'                => 21,
		'nameMembership'          => 'Black',
		'displayName'             => 'Black',
		'value'                   => $value,
		'valuePromotionalPeriod'  => $promo,
		'monthsPromotionalPeriod' => 1,
		'duration'                => 12,
		'durationType'            => 'Mensal',
		'maxAmountInstallments'   => 12,
		'minPeriodStayMembership' => 12,
		'inactive'                => $inactive,
		'enrollmentRequired'      => true,
		'urlSale'                 => 'https://checkout.contornodocorpo.com.br/contornodocorpo/21/site/landing-page/checkout/5809/0',
	);
}

function fx_gateway(): array {
	return array(
		'gatewayType'       => 7,
		'showCardType'      => true,
		'tokenizeBackend'   => false,
		'validationEnabled' => false,
		'gatewayData'       => array(
			'publicKey'    => 'pk_test_publica',
			// Campos que NAO podem chegar ao navegador.
			'secretKey'    => 'sk_SEGREDO_NAO_PODE_VAZAR',
			'apiPassword'  => 'senha-do-adquirente',
			'merchantId'   => '9988',
		),
	);
}

/**
 * Roteador HTTP falso. Cada teste troca $GLOBALS['routes'] pelo que quer.
 */
function fx_http(): callable {
	return static function ( string $url, array $args ) {
		$GLOBALS['calls'][] = array(
			'method'  => $args['method'] ?? 'GET',
			'url'     => $url,
			'body'    => isset( $args['body'] ) ? json_decode( (string) $args['body'], true ) : null,
			'headers' => $args['headers'] ?? array(),
		);

		foreach ( $GLOBALS['routes'] as $pattern => $reply ) {
			if ( str_contains( $url, $pattern ) ) {
				return is_callable( $reply ) ? $reply( $url, $args ) : $reply;
			}
		}

		return array( 'response' => array( 'code' => 404 ), 'body' => '{"mensagens":["nao mapeado"]}' );
	};
}

function fx_json( mixed $data, int $code = 200 ): array {
	return array( 'response' => array( 'code' => $code ), 'body' => (string) json_encode( $data ) );
}

/** Estado limpo entre testes. */
function fx_reset( array $routes = array() ): void {
	$GLOBALS['tr']     = array();
	$GLOBALS['calls']  = array();
	$GLOBALS['routes'] = $routes;
	$GLOBALS['http']   = fx_http();
	$GLOBALS['opt']    = array(
		Contorno_Evo_Settings::OPTION => array(
			'dns'                      => 'contornodocorpo',
			'checkout_mode'            => 'pilot',
			'checkout_allowlist'       => array( 'lourdes' ),
			'checkout_payment_card'    => 2,
			'checkout_codes_confirmed' => true,
			'checkout_evopay_script'   => 'https://evo-pay.w12app.com.br/componente.js',
			'checkout_require_address' => false,
		),
	);

	// Token em claro por constante seria mais simples, mas o codigo real le
	// pela option cifrada; usamos o mesmo caminho da producao.
	$GLOBALS['opt'][ Contorno_Evo_Settings::OPTION_TOKEN ] = Contorno_Evo_Crypto::encrypt( 'token-de-teste' );

	$GLOBALS['meta'] = array(
		101 => array(
			CONTORNO_EVO_META_BRANCH => 21,
			'_contorno_plans'        => array(
				array( 'id' => 'black', 'name' => 'Black', 'price' => 9.9, 'checkout_url' => 'https://checkout.contornodocorpo.com.br/contornodocorpo/21/site/landing-page/checkout/5809/0' ),
				array( 'id' => 'premium', 'name' => 'Premium', 'price' => 219.9, 'checkout_url' => 'https://checkout.contornodocorpo.com.br/contornodocorpo/21/site/landing-page/checkout/486/0' ),
				array( 'id' => 'fit', 'name' => 'Fit', 'price' => 49.9, 'checkout_url' => 'https://checkout.contornodocorpo.com.br/contornodocorpo/21/site/landing-page/checkout/5697/0' ),
			),
		),
	);
}

/** Rotas padrao: membership, gateway, sem membro/prospect, venda aprovada. */
function fx_routes_happy(): array {
	return array(
		'/api/v3/membership'            => fx_json( array( fx_membership() ) ),
		'/api/v2/configuration/gateway' => fx_json( fx_gateway() ),
		'/api/v1/members/basic'         => fx_json( array() ),
		// A MESMA rota serve para buscar (GET, lista) e criar (POST, devolve o
		// id). A fixture precisa distinguir, senao a criacao de prospect
		// recebe uma lista vazia e o fluxo morre por "sem idProspect".
		'/api/v1/prospects'             => static fn ( string $url, array $args ) => 'POST' === ( $args['method'] ?? 'GET' )
			? fx_json( 5551 )
			: fx_json( array() ),
		'/api/v2/sales'                 => fx_json( array( 'idSale' => 778899 ) ),
		'/api/v1/sales/by-session-id'   => fx_json( 778899 ),
	);
}

/* =============================================================
 * Runner
 * =========================================================== */

$ok   = 0;
$fail = 0;

function t( string $name, bool $cond, string $extra = '' ): void {
	global $ok, $fail;
	echo $cond ? '  [OK]       ' : '  [FALHOU]   ', $name, '' !== $extra ? "  -> $extra" : '', "\n";
	$cond ? $ok++ : $fail++;
}

function section( string $title ): void {
	echo "\n== $title\n";
}

function request( array $params ): WP_REST_Request {
	return new WP_REST_Request( $params + array( 'nonce' => 'nonce-' . Contorno_Evo_Checkout_Rest::NONCE, 'website' => '' ) );
}

/** Payload de cartao como o EVO Pay devolve: token, sem PAN e sem CVV. */
function fx_card(): array {
	return array(
		'token'               => 'tok_evo_1234567890',
		'truncatedCardNumber' => '411111******1111',
		'brand'               => 'Visa',
		'cardHolderName'      => 'MARIA DE SOUZA',
		'cardExpirationYear'  => 2030,
		'cardExpirationMonth' => 5,
	);
}

function fx_person(): array {
	return array(
		'firstName'     => 'Maria',
		'lastName'      => 'de Souza',
		'email'         => 'maria@example.com',
		'phone'         => '(31) 99999-1234',
		// CPF valido gerado para teste (digitos verificadores corretos).
		'document'      => '529.982.247-25',
		'acceptedTerms' => true,
	);
}

$_SERVER['HTTP_ORIGIN'] = 'https://contornowp.voceconecta.com.br';
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

/* -------------------------------------------------------------
 * 1. Resolucao server-side / fraude de preco e de membership
 * ----------------------------------------------------------- */

section( 'Resolucao server-side: o navegador nao escolhe preco nem membership' );

fx_reset( fx_routes_happy() );

$resolved = Contorno_Evo_Checkout::resolve( 'lourdes', 'black' );
t( 'slug + id local resolvem idBranch 21', $resolved['ok'] && 21 === $resolved['id_branch'], 'achou ' . $resolved['id_branch'] );
t( 'slug + id local resolvem idMembership 5809 (Black de Lourdes)', 5809 === $resolved['id_membership'], 'achou ' . $resolved['id_membership'] );

$resolved = Contorno_Evo_Checkout::resolve( 'lourdes', 'premium' );
t( 'Premium de Lourdes resolve 486 (nao e erro de sequencia)', 486 === $resolved['id_membership'], 'achou ' . $resolved['id_membership'] );

$resolved = Contorno_Evo_Checkout::resolve( 'lourdes', 'fit' );
t( 'Fit de Lourdes resolve 5697', 5697 === $resolved['id_membership'], 'achou ' . $resolved['id_membership'] );

t( 'plano inexistente e recusado', ! Contorno_Evo_Checkout::resolve( 'lourdes', 'nao-existe' )['ok'] );
t( 'unidade inexistente e recusada', ! Contorno_Evo_Checkout::resolve( 'unidade-fantasma', 'black' )['ok'] );

// Tampering: o atacante manda os parametros classicos junto da selecao.
fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open(
	request(
		array(
			'slug'            => 'lourdes',
			'plan'            => 'black',
			// Tudo abaixo e ruido: nenhuma dessas chaves e lida em lugar nenhum.
			'price'           => 1,
			'membershipValue' => 1,
			'idMembership'    => 999,
			'idBranch'        => 999,
			'checkout'        => 'https://atacante.example/roubo',
			'totalInstallments' => 99,
		)
	)
);

t( 'checkout abre apesar dos parametros forjados', ! empty( $opened->data['ok'] ) );
t( 'preco exibido vem da EVO (9,90 promocional), nao do ?price=1', abs( (float) $opened->data['summary']['firstValue'] - 9.90 ) < 0.001, 'veio ' . ( $opened->data['summary']['firstValue'] ?? '?' ) );
t( 'valor cheio vem da EVO (219,90), nao do CPT', abs( (float) $opened->data['summary']['value'] - 219.90 ) < 0.001 );
t( 'parcelamento vem da EVO (12x), nao do ?totalInstallments=99', 12 === (int) $opened->data['summary']['maxInstallments'] );

$state = Contorno_Evo_Checkout::load( (string) $opened->data['token'] );
t( 'sessao gravou idMembership 5809, ignorando ?idMembership=999', 5809 === (int) $state['id_membership'] );
t( 'sessao gravou idBranch 21, ignorando ?idBranch=999', 21 === (int) $state['id_branch'] );

$serialized = (string) json_encode( $opened->data );
t( 'resposta de abertura nao devolve URL de checkout para o navegador', ! str_contains( $serialized, 'atacante.example' ) && ! str_contains( $serialized, 'landing-page/checkout' ) );

/* -------------------------------------------------------------
 * 2. Credenciais e segredos
 * ----------------------------------------------------------- */

section( 'Nenhuma credencial chega ao navegador' );

t( 'abertura nao contem o token da EVO', ! str_contains( $serialized, 'token-de-teste' ) );
t( 'abertura nao contem o DNS da EVO', ! str_contains( $serialized, 'contornodocorpo' ) );
t( 'abertura nao contem cabecalho Authorization', ! str_contains( strtolower( $serialized ), 'authorization' ) );

$public = $opened->data['gateway'];
$public_json = (string) json_encode( $public );
t( 'gatewayData publico traz a chave publica', 'pk_test_publica' === ( $public['data']['publicKey'] ?? '' ) );
t( 'gatewayData publico NAO traz secretKey', ! str_contains( $public_json, 'SEGREDO' ) && ! isset( $public['data']['secretKey'] ) );
t( 'gatewayData publico NAO traz apiPassword', ! str_contains( $public_json, 'senha-do-adquirente' ) );
t( 'gatewayType e repassado como numero, sem interpretacao', 7 === (int) $public['gatewayType'] );
t( 'validationEnabled da EVO e respeitado (false)', false === $public['validationEnabled'] );

$log_json = (string) json_encode( Contorno_Evo_Log::all() );
t( 'campos nao mapeados do gateway entram no log apenas por NOME', str_contains( $log_json, 'secretkey' ) || str_contains( $log_json, 'secretKey' ) );
t( 'o VALOR do segredo nao entra no log', ! str_contains( $log_json, 'SEGREDO' ) && ! str_contains( $log_json, 'senha-do-adquirente' ) );

/* -------------------------------------------------------------
 * 3. Cartao: nada bruto entra
 * ----------------------------------------------------------- */

section( 'Cartao: o WordPress recebe token, nunca PAN nem CVV' );

t( 'payload tokenizado legitimo passa', ! Contorno_Evo_Checkout::rejects_raw_card( fx_card() ) );
t( 'cvv e recusado', Contorno_Evo_Checkout::rejects_raw_card( array( 'token' => 'tok', 'cvv' => '123' ) ) );
t( 'securityCode e recusado', Contorno_Evo_Checkout::rejects_raw_card( array( 'token' => 'tok', 'securityCode' => '999' ) ) );
t( 'cardNumber e recusado', Contorno_Evo_Checkout::rejects_raw_card( array( 'cardNumber' => '4111111111111111' ) ) );
t( 'PAN escondido em campo de nome inocente e recusado', Contorno_Evo_Checkout::rejects_raw_card( array( 'token' => '4111 1111 1111 1111' ) ) );
t( 'PAN com separadores e recusado', Contorno_Evo_Checkout::rejects_raw_card( array( 'brand' => '4111-1111-1111-1111' ) ) );
t( 'truncatedCardNumber mascarado continua valido', ! Contorno_Evo_Checkout::rejects_raw_card( array( 'token' => 'tok', 'truncatedCardNumber' => '411111******1111' ) ) );

fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];

$paid = Contorno_Evo_Checkout_Rest::pay(
	request(
		array(
			'token'        => $token,
			'person'       => fx_person(),
			'card'         => fx_card() + array( 'cvv' => '123', 'cardNumber' => '4111111111111111' ),
			'installments' => 3,
		)
	)
);

t( 'venda com PAN/CVV no payload e recusada', empty( $paid->data['ok'] ) );
$sale_calls = array_filter( $GLOBALS['calls'], static fn ( $c ) => str_contains( $c['url'], '/api/v2/sales' ) && 'POST' === $c['method'] );
t( 'nenhuma venda foi enviada a EVO nesse caso', array() === $sale_calls );
$all_calls = (string) json_encode( $GLOBALS['calls'] );
t( 'o PAN nunca saiu do WordPress', ! str_contains( $all_calls, '4111111111111111' ) );
t( 'o CVV nunca saiu do WordPress', ! str_contains( $all_calls, '"cvv"' ) );
t( 'o PAN nao entrou no log', ! str_contains( (string) json_encode( Contorno_Evo_Log::all() ), '4111111111111111' ) );

/* -------------------------------------------------------------
 * 4. Venda aprovada — payload
 * ----------------------------------------------------------- */

section( 'Venda aprovada: payload conforme NewSaleViewModel' );

fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];

$paid = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => $token, 'person' => fx_person(), 'card' => fx_card(), 'installments' => 3 ) ) );

t( 'venda concluida', ! empty( $paid->data['ok'] ) && 'success' === ( $paid->data['status'] ?? '' ), (string) ( $paid->data['error'] ?? '' ) );

$sale = null;
$prospect = null;
foreach ( $GLOBALS['calls'] as $call ) {
	if ( 'POST' === $call['method'] && str_contains( $call['url'], '/api/v2/sales' ) ) { $sale = $call; }
	if ( 'POST' === $call['method'] && str_contains( $call['url'], '/api/v1/prospects' ) ) { $prospect = $call; }
}

t( 'POST /api/v2/sales foi chamado', null !== $sale );
t( 'header culture: pt-BR presente', 'pt-BR' === ( $sale['headers']['culture'] ?? '' ) );
t( 'idBranch correto no payload', 21 === (int) ( $sale['body']['idBranch'] ?? 0 ) );
t( 'idMembership correto no payload', 5809 === (int) ( $sale['body']['idMembership'] ?? 0 ) );
t( 'membershipValue NAO e enviado (a EVO aplica o valor oficial)', ! array_key_exists( 'membershipValue', (array) $sale['body'] ) );
t( 'payment usa o codigo configurado, nunca um chute no codigo-fonte', 2 === (int) ( $sale['body']['payment'] ?? 0 ) );
t( 'totalInstallments respeita o teto da EVO', 3 === (int) ( $sale['body']['totalInstallments'] ?? 0 ) );
t( 'sessionId proprio acompanha a venda', str_starts_with( (string) ( $sale['body']['sessionId'] ?? '' ), 'ctn-' ) );
t( 'cardData leva o token', 'tok_evo_1234567890' === ( $sale['body']['cardData']['token'] ?? '' ) );
t( 'cardData nao leva campo fora da lista fechada', array() === array_diff( array_keys( (array) $sale['body']['cardData'] ), array_merge( Contorno_Evo_Checkout::CARD_FIELDS, array( 'totalInstallments' ) ) ) );
t( 'prospect novo foi criado antes da venda', null !== $prospect && (int) ( $sale['body']['idProspect'] ?? 0 ) > 0 );
t( 'prospect registra a origem Site', 'Site' === ( $prospect['body']['marketingType'] ?? '' ) );
t( 'prospect pede validacao de CPF duplicado', ! empty( $prospect['body']['validateCpfDuplication'] ) );
t( 'endereco nao e enviado quando nao e exigido', ! array_key_exists( 'zipCode', (array) $prospect['body'] ) );

t( 'redirect leva somente o token opaco na URL', str_contains( (string) $paid->data['redirect'], 'ck=' . $token ) );
$redirect = (string) $paid->data['redirect'];
t( 'redirect nao expoe e-mail, CPF, idProspect nem idSale', ! str_contains( $redirect, 'maria@' ) && ! str_contains( $redirect, '52998224725' ) && ! str_contains( $redirect, '778899' ) );

/* -------------------------------------------------------------
 * 5. Prospect x membro existente
 * ----------------------------------------------------------- */

section( 'Prospect x membro existente: nao duplicar cadastro' );

// A) ja e membro
fx_reset( fx_routes_happy() );
$GLOBALS['routes']['/api/v1/members/basic'] = fx_json( array( array( 'idMember' => 4242, 'firstName' => 'Maria' ) ) );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];
$found  = Contorno_Evo_Checkout_Rest::identify( request( array( 'token' => $token, 'email' => 'maria@example.com', 'document' => '52998224725', 'phone' => '31999991234' ) ) );

t( 'membro existente e reconhecido', 'member' === ( $found->data['kind'] ?? '' ) );
t( 'resposta nao devolve dados do cadastro de quem digitou o CPF', ! str_contains( (string) json_encode( $found->data ), 'Maria' ) );

$paid = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => $token, 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );
$created_prospect = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v1/prospects' ) );
t( 'membro existente NAO gera prospect novo', array() === $created_prospect );
$sale = null;
foreach ( $GLOBALS['calls'] as $c ) { if ( 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) ) { $sale = $c; } }
t( 'venda de membro usa memberData.idMember', 4242 === (int) ( $sale['body']['memberData']['idMember'] ?? 0 ) );
t( 'venda de membro nao manda idProspect', ! array_key_exists( 'idProspect', (array) $sale['body'] ) );

// B) ja e prospect
fx_reset( fx_routes_happy() );
$GLOBALS['routes']['/api/v1/prospects'] = static function ( string $url, array $args ) {
	// POST cria; GET consulta. A fixture precisa distinguir os dois.
	return 'POST' === ( $args['method'] ?? 'GET' )
		? fx_json( 555 )
		: fx_json( array( array( 'idProspect' => 3131 ) ) );
};
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];
$found  = Contorno_Evo_Checkout_Rest::identify( request( array( 'token' => $token, 'email' => 'maria@example.com', 'document' => '52998224725' ) ) );
t( 'prospect existente e reconhecido', 'prospect' === ( $found->data['kind'] ?? '' ) );

Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => $token, 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );
$created_prospect = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v1/prospects' ) );
t( 'prospect existente NAO gera outro prospect', array() === $created_prospect );
$sale = null;
foreach ( $GLOBALS['calls'] as $c ) { if ( 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) ) { $sale = $c; } }
t( 'venda reusa o idProspect encontrado', 3131 === (int) ( $sale['body']['idProspect'] ?? 0 ) );

// C) prospect ja convertido em membro
fx_reset( fx_routes_happy() );
$GLOBALS['routes']['/api/v1/prospects'] = fx_json( array( array( 'idProspect' => 3131, 'idMember' => 9090 ) ) );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$found  = Contorno_Evo_Checkout_Rest::identify( request( array( 'token' => (string) $opened->data['token'], 'document' => '52998224725' ) ) );
t( 'prospect ja convertido vale como membro', 'member' === ( $found->data['kind'] ?? '' ) );

// D) consulta indisponivel NAO pode virar "nao existe"
fx_reset( fx_routes_happy() );
$GLOBALS['routes']['/api/v1/members/basic'] = fx_json( array( 'mensagens' => array( 'erro' ) ), 500 );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$found  = Contorno_Evo_Checkout_Rest::identify( request( array( 'token' => (string) $opened->data['token'], 'document' => '52998224725' ) ) );
t( 'falha na consulta de membro para o fluxo em vez de criar duplicado', empty( $found->data['ok'] ) && 'evo_indisponivel' === $found->data['error'] );

/* -------------------------------------------------------------
 * 6. Idempotencia
 * ----------------------------------------------------------- */

section( 'Idempotencia: duplo clique, refresh e retry nao vendem duas vezes' );

fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];
$body   = array( 'token' => $token, 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 );

$first  = Contorno_Evo_Checkout_Rest::pay( request( $body ) );
$second = Contorno_Evo_Checkout_Rest::pay( request( $body ) );
$third  = Contorno_Evo_Checkout_Rest::pay( request( $body ) );

$sales = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) );
t( 'tres envios do mesmo pedido geram UMA venda', 1 === count( $sales ), count( $sales ) . ' vendas' );
t( 'primeira tentativa conclui', ! empty( $first->data['ok'] ) );
t( 'reenvio responde "ja concluida", nao vende de novo', ! empty( $second->data['ok'] ) && 'success' === $second->data['status'] );
t( 'terceiro reenvio tambem e inofensivo', ! empty( $third->data['ok'] ) );

// Trava enquanto processa: simula o duplo clique real, com a segunda chamada
// entrando enquanto a EVO ainda nao respondeu a primeira.
fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];
$reentrant = null;
$GLOBALS['routes']['/api/v2/sales'] = static function ( string $url, array $args ) use ( &$reentrant, $token ) {
	$reentrant = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => $token, 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );

	return fx_json( array( 'idSale' => 778899 ) );
};
Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => $token, 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );
$sales = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) );
t( 'clique concorrente e barrado pela trava', null !== $reentrant && 'em_andamento' === ( $reentrant->data['error'] ?? '' ), (string) ( $reentrant->data['error'] ?? 'sem resposta' ) );
t( 'e nao gerou segunda chamada de venda', 1 === count( $sales ), count( $sales ) . ' vendas' );

// Refresh: a pessoa paga, recarrega a pagina (sessao NOVA, sessionId novo) e
// tenta pagar de novo. A trava por sessao nao cobre isso; a impressao digital
// cobre.
fx_reset( fx_routes_happy() );
$first_open = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => (string) $first_open->data['token'], 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );

$second_open = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
t( 'o refresh realmente abre outra sessao', $first_open->data['token'] !== $second_open->data['token'] );
$again = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => (string) $second_open->data['token'], 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );

$sales = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) );
t( 'pagar apos refresh NAO gera segunda venda', 1 === count( $sales ), count( $sales ) . ' vendas' );
t( 'a segunda tentativa responde "ja concluida"', ! empty( $again->data['ok'] ) && 'success' === $again->data['status'] );

$fingerprints = array_filter( array_keys( $GLOBALS['tr'] ), static fn ( $k ) => str_contains( $k, 'done_' ) );
t( 'a impressao digital nao guarda o CPF', array() !== $fingerprints && ! str_contains( implode( '', $fingerprints ), '52998224725' ) );

// Outro CPF, mesmo plano: e outra pessoa, e tem de conseguir comprar.
$other = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$other_paid = Contorno_Evo_Checkout_Rest::pay(
	request(
		array(
			'token'        => (string) $other->data['token'],
			// CPF valido diferente.
			'person'       => array_merge( fx_person(), array( 'document' => '11144477735', 'email' => 'joao@example.com' ) ),
			'card'         => fx_card(),
			'installments' => 1,
		)
	)
);
$sales = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) );
t( 'outra pessoa com o mesmo plano nao e bloqueada', ! empty( $other_paid->data['ok'] ) && 2 === count( $sales ), count( $sales ) . ' vendas' );

/* -------------------------------------------------------------
 * 7. Resposta ambigua
 * ----------------------------------------------------------- */

section( 'Resposta ambigua: nunca reenviar, nunca cair para o checkout externo' );

// Timeout, e a venda existia.
fx_reset( fx_routes_happy() );
$GLOBALS['routes']['/api/v2/sales'] = static fn () => new WP_Error( 'http_request_failed', 'Operation timed out' );
$GLOBALS['routes']['/api/v1/sales/by-session-id'] = fx_json( 445566 );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$paid   = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => (string) $opened->data['token'], 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );

$sales = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) );
t( 'POST /sales nao e reenviado automaticamente no timeout', 1 === count( $sales ), count( $sales ) . ' tentativas' );
t( 'by-session-id encontra a venda e o fluxo conclui', ! empty( $paid->data['ok'] ) && 'success' === $paid->data['status'] );
t( 'nao houve fallback para o checkout externo', empty( $paid->data['fallback'] ) );

// Timeout, e nao havia venda: pode tentar de novo.
fx_reset( fx_routes_happy() );
$GLOBALS['routes']['/api/v2/sales'] = static fn () => new WP_Error( 'http_request_failed', 'Operation timed out' );
$GLOBALS['routes']['/api/v1/sales/by-session-id'] = fx_json( 0 );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$paid   = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => (string) $opened->data['token'], 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );
t( 'venda inexistente apos timeout permite nova tentativa', 'evo_indisponivel' === ( $paid->data['error'] ?? '' ) );
t( 'e nao oferece checkout externo (a duvida ja foi resolvida, mas sem venda)', empty( $paid->data['fallback'] ) );

// Timeout E by-session-id tambem falha: indeterminado, sem fallback, travado.
fx_reset( fx_routes_happy() );
$GLOBALS['routes']['/api/v2/sales'] = static fn () => new WP_Error( 'http_request_failed', 'Operation timed out' );
$GLOBALS['routes']['/api/v1/sales/by-session-id'] = fx_json( array( 'mensagens' => array( 'indisponivel' ) ), 503 );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];
$paid   = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => $token, 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );

t( 'estado indeterminado e comunicado como indeterminado', 'indeterminado' === ( $paid->data['error'] ?? '' ) );
t( 'indeterminado NAO oferece fallback (evitaria cobranca dupla)', empty( $paid->data['fallback'] ) );
$retry = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => $token, 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );
t( 'nova tentativa em estado indeterminado e barrada', 'indeterminado' === ( $retry->data['error'] ?? '' ) );
$sales = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) );
t( 'nenhuma venda extra foi enviada', 1 === count( $sales ), count( $sales ) . ' tentativas' );

/* -------------------------------------------------------------
 * 8. Preco mudou entre abrir e pagar
 * ----------------------------------------------------------- */

section( 'Preco mudou entre a abertura e o pagamento' );

fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];

// A promocao acabou: a EVO passa a cobrar o valor cheio.
$GLOBALS['routes']['/api/v3/membership'] = fx_json( array( fx_membership( 219.90, 0.0 ) ) );
$paid = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => $token, 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );

t( 'venda nao e enviada com o preco antigo', 'preco_mudou' === ( $paid->data['error'] ?? '' ) );
$sales = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) );
t( 'nenhuma venda foi criada', array() === $sales );
t( 'o resumo novo volta para a tela', abs( (float) ( $paid->data['summary']['firstValue'] ?? 0 ) - 219.90 ) < 0.001 );

/* -------------------------------------------------------------
 * 9. Membership inexistente / inativo / filial trocada
 * ----------------------------------------------------------- */

section( 'Membership inexistente, inativo ou de outra filial' );

fx_reset( fx_routes_happy() );
$GLOBALS['routes']['/api/v3/membership'] = fx_json( array() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
t( 'membership ausente na EVO nao abre o checkout nativo', empty( $opened->data['ok'] ) && 'membership_inexistente' === $opened->data['error'] );
t( 'e oferece o checkout externo (nada foi criado)', ! empty( $opened->data['fallback'] ) );

fx_reset( fx_routes_happy() );
$GLOBALS['routes']['/api/v3/membership'] = fx_json( array( fx_membership( 219.90, 9.90, true ) ) );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
t( 'membership inativo nao abre o checkout nativo', 'membership_inativo' === ( $opened->data['error'] ?? '' ) );

fx_reset( fx_routes_happy() );
// A EVO devolve o plano homonimo de OUTRA filial.
$GLOBALS['routes']['/api/v3/membership'] = fx_json( array( array( 'idMembership' => 5809, 'idBranch' => 44, 'value' => 5.0, 'nameMembership' => 'Black' ) ) );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
t( 'plano de filial diferente e recusado, nao vendido a 5,00', empty( $opened->data['ok'] ) );

/* -------------------------------------------------------------
 * 10. Erros da EVO
 * ----------------------------------------------------------- */

section( 'Erros da EVO: 400, 401, 403, 429, 500, JSON invalido' );

foreach ( array( 400 => 'cartao_recusado', 401 => 'evo_indisponivel', 403 => 'evo_indisponivel', 429 => 'evo_indisponivel' ) as $code => $expected ) {
	fx_reset( fx_routes_happy() );
	$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
	$GLOBALS['routes']['/api/v2/sales'] = fx_json( array( 'mensagens' => array( 'detalhe tecnico interno com token-de-teste' ) ), $code );
	$paid = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => (string) $opened->data['token'], 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );

	t( "HTTP $code vira erro de negocio tratado", $expected === ( $paid->data['error'] ?? '' ), (string) ( $paid->data['error'] ?? '' ) );
	t( "HTTP $code nao expoe resposta tecnica ao visitante", ! str_contains( (string) $paid->data['message'], 'detalhe tecnico' ) && ! str_contains( (string) $paid->data['message'], 'HTTP' ) );
	t( "HTTP $code nao expoe o token da EVO ao visitante", ! str_contains( (string) json_encode( $paid->data ), 'token-de-teste' ) );
}

fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$GLOBALS['routes']['/api/v2/sales'] = array( 'response' => array( 'code' => 200 ), 'body' => '<html>erro do proxy</html>' );
$paid = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => (string) $opened->data['token'], 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );
t( 'JSON invalido nao e tratado como sucesso', empty( $paid->data['ok'] ) || 'success' !== ( $paid->data['status'] ?? '' ) );

$log_json = (string) json_encode( Contorno_Evo_Log::all() );
t( 'log tecnico nao guarda o token da EVO', ! str_contains( $log_json, 'token-de-teste' ) );
t( 'log tecnico nao guarda CPF', ! str_contains( $log_json, '52998224725' ) && ! str_contains( $log_json, '529.982.247-25' ) );
t( 'log tecnico nao guarda e-mail', ! str_contains( $log_json, 'maria@example.com' ) );
t( 'log tecnico nao guarda token de cartao', ! str_contains( $log_json, 'tok_evo_1234567890' ) );

/* -------------------------------------------------------------
 * 11. Nonce, honeypot, mesma origem, rate limit
 * ----------------------------------------------------------- */

section( 'Nonce, honeypot, mesma origem e rate limit' );

fx_reset( fx_routes_happy() );
$bad = Contorno_Evo_Checkout_Rest::open( new WP_REST_Request( array( 'slug' => 'lourdes', 'plan' => 'black', 'nonce' => 'forjado' ) ) );
t( 'nonce invalido e recusado', empty( $bad->data['ok'] ) && 'sessao_expirada' === $bad->data['error'] );

fx_reset( fx_routes_happy() );
$bad = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black', 'website' => 'http://spam.example' ) ) );
t( 'honeypot preenchido e recusado', empty( $bad->data['ok'] ) );

fx_reset( fx_routes_happy() );
$_SERVER['HTTP_ORIGIN'] = 'https://atacante.example';
$bad = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
t( 'requisicao de outra origem e recusada (CSRF)', empty( $bad->data['ok'] ) );
$_SERVER['HTTP_ORIGIN'] = 'https://contornowp.voceconecta.com.br';

fx_reset( fx_routes_happy() );
$limited = false;
for ( $i = 0; $i < 12; $i++ ) {
	$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
	$attempt = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => (string) ( $opened->data['token'] ?? '' ), 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );
	if ( 'limite' === ( $attempt->data['error'] ?? '' ) ) { $limited = true; break; }
}
t( 'tentativas repetidas de venda batem no rate limit', $limited );

fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$paid = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => (string) $opened->data['token'], 'person' => fx_person(), 'card' => fx_card(), 'installments' => 1 ) ) );
t( 'um erro normal de cartao nao bloqueia o cliente legitimo', ! empty( $paid->data['ok'] ) );

section( 'Sessao e dados pessoais' );

fx_reset( fx_routes_happy() );
$bad = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => 'nao-existe', 'person' => fx_person(), 'card' => fx_card() ) ) );
t( 'token invalido nao paga', 'sessao_expirada' === ( $bad->data['error'] ?? '' ) );

fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];
Contorno_Evo_Checkout_Rest::identify( request( array( 'token' => $token, 'email' => 'maria@example.com', 'document' => '52998224725', 'phone' => '31999991234' ) ) );
$stored = (string) json_encode( $GLOBALS['tr'][ Contorno_Evo_Checkout::key( $token ) ] );
t( 'sessao no servidor nao guarda e-mail', ! str_contains( $stored, 'maria@example.com' ) );
t( 'sessao no servidor nao guarda CPF', ! str_contains( $stored, '52998224725' ) );
t( 'sessao no servidor nao guarda telefone', ! str_contains( $stored, '31999991234' ) );
t( 'token de sessao tem 32 hex (nao adivinhavel)', 1 === preg_match( '/^[a-f0-9]{32}$/', $token ) );

section( 'Validacao de dados' );

t( 'CPF valido passa', Contorno_Evo_Checkout_Rest::valid_cpf( '52998224725' ) );
t( 'CPF com digito errado e recusado', ! Contorno_Evo_Checkout_Rest::valid_cpf( '52998224726' ) );
t( 'CPF de digitos repetidos e recusado', ! Contorno_Evo_Checkout_Rest::valid_cpf( '11111111111' ) );

fx_reset( fx_routes_happy() );
$opened = Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$token  = (string) $opened->data['token'];
foreach ( array(
	'e-mail invalido'      => array( 'email' => 'nao-e-email' ),
	'CPF invalido'         => array( 'document' => '11111111111' ),
	'telefone curto'       => array( 'phone' => '319999' ),
	'aceite ausente'       => array( 'acceptedTerms' => false ),
	'nome de uma letra'    => array( 'firstName' => 'M' ),
) as $label => $patch ) {
	$attempt = Contorno_Evo_Checkout_Rest::pay( request( array( 'token' => $token, 'person' => array_merge( fx_person(), $patch ), 'card' => fx_card(), 'installments' => 1 ) ) );
	t( "venda com $label e recusada", empty( $attempt->data['ok'] ) );
}
$sales = array_filter( $GLOBALS['calls'], static fn ( $c ) => 'POST' === $c['method'] && str_contains( $c['url'], '/api/v2/sales' ) );
t( 'nenhuma venda foi enviada com dados invalidos', array() === $sales );

/* -------------------------------------------------------------
 * 12. Feature flag
 * ----------------------------------------------------------- */

section( 'Feature flag: OFF por padrao, PILOT por allowlist' );

fx_reset( fx_routes_happy() );
$GLOBALS['opt'] = array(); // estado de instalacao limpa
Contorno_Evo_Settings::ensure_defaults();
t( 'instalacao nova nasce com checkout nativo OFF', 'off' === Contorno_Evo_Settings::checkout_mode() );
t( 'OFF nao habilita nenhuma unidade', ! Contorno_Evo_Settings::checkout_enabled_for( 'lourdes' ) );
t( 'sem codigo de pagamento confirmado o checkout nao esta pronto', ! Contorno_Evo_Settings::checkout_ready() );
t( 'a tela lista os bloqueios pendentes', array() !== Contorno_Evo_Settings::checkout_blockers() );

fx_reset( fx_routes_happy() );
t( 'PILOT habilita Lourdes', Contorno_Evo_Settings::checkout_enabled_for( 'lourdes' ) );
t( 'PILOT nao habilita outra unidade', ! Contorno_Evo_Settings::checkout_enabled_for( 'buritis' ) );

fx_reset( fx_routes_happy() );
$settings = Contorno_Evo_Settings::all();
$settings['checkout_codes_confirmed'] = false;
$GLOBALS['opt'][ Contorno_Evo_Settings::OPTION ] = $settings;
t( 'codigo de pagamento nao conferido desliga o nativo mesmo em PILOT', ! Contorno_Evo_Settings::checkout_enabled_for( 'lourdes' ) );

fx_reset( fx_routes_happy() );
$settings = Contorno_Evo_Settings::all();
$settings['checkout_payment_card'] = 99; // fora do enum documentado
$GLOBALS['opt'][ Contorno_Evo_Settings::OPTION ] = $settings;
t( 'codigo de pagamento fora do enum da EVO e ignorado', 0 === Contorno_Evo_Settings::payment_code_card() );

fx_reset( fx_routes_happy() );
$GLOBALS['opt'][ Contorno_Evo_Settings::OPTION ]['checkout_mode'] = 'on';
t( 'ON habilita qualquer unidade vinculada', Contorno_Evo_Settings::checkout_enabled_for( 'buritis' ) );

section( 'SSRF e destino das chamadas' );

t( 'base URL fora dos hosts da EVO volta para a oficial', CONTORNO_EVO_DEFAULT_BASE_URL === Contorno_Evo_Settings::sanitize_base_url( 'https://coletor.atacante.example' ) );
t( 'base URL em IP interno volta para a oficial', CONTORNO_EVO_DEFAULT_BASE_URL === Contorno_Evo_Settings::sanitize_base_url( 'https://169.254.169.254' ) );
t( 'script do EVO Pay em host de terceiro e rejeitado', '' === Contorno_Evo_Settings::sanitize_script_url( 'https://cdn.atacante.example/x.js' ) );
t( 'script do EVO Pay em http e rejeitado', '' === Contorno_Evo_Settings::sanitize_script_url( 'http://evo-pay.w12app.com.br/x.js' ) );
t( 'script do EVO Pay em host da EVO e aceito', '' !== Contorno_Evo_Settings::sanitize_script_url( 'https://evo-pay.w12app.com.br/componente.js' ) );

fx_reset( fx_routes_happy() );
Contorno_Evo_Checkout_Rest::open( request( array( 'slug' => 'lourdes', 'plan' => 'black' ) ) );
$off_host = array_filter( $GLOBALS['calls'], static fn ( $c ) => ! str_contains( $c['url'], 'w12app.com.br' ) );
t( 'toda chamada saiu para o host oficial da EVO', array() === $off_host );

section( 'PIX' );

$pix_hits = array();
foreach ( array( $plugin . '/includes/class-checkout.php', $plugin . '/includes/class-checkout-rest.php', $core . '/assets/js/enrollment-native.js' ) as $file ) {
	if ( is_file( $file ) && preg_match( '/qrcode|qr_code|copia e cola|emv|payment.*=.*7\b/i', (string) file_get_contents( $file ) ) ) {
		$pix_hits[] = basename( $file );
	}
}
t( 'nenhum QR Code de PIX e gerado localmente', array() === $pix_hits, implode( ', ', $pix_hits ) );

printf( "\n== Resultado: %d OK, %d falhas\n", $ok, $fail );
exit( $fail > 0 ? 1 : 0 );
