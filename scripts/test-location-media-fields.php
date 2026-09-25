<?php
/**
 * Testes das mascaras/normalizacao de CEP, telefone, coordenadas e galeria —
 * rodados por scripts/test-location-media-fields.sh.
 *
 * Carrega o codigo REAL (data/geo.php, helpers.php, meta/fields.php) com um
 * punhado de stubs do WordPress, e chama contorno_sanitize_field() do jeito
 * que o save_post do painel e o importador chamam.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

$plugin = rtrim( (string) ( $argv[1] ?? '' ), '/\\' );

if ( '' === $plugin || ! is_dir( $plugin ) ) {
	fwrite( STDERR, "Uso: php test-location-media-fields.php <caminho-do-plugin>\n" );
	exit( 1 );
}

define( 'ABSPATH', true );

// --- Stubs minimos do WordPress -------------------------------------------

function __( string $text, string $domain = '' ): string { return $text; }
function esc_html__( string $text, string $domain = '' ): string { return $text; }
function esc_attr__( string $text, string $domain = '' ): string { return $text; }
function sanitize_text_field( string $value ): string {
	return trim( (string) preg_replace( '/[\r\n\t]+/', ' ', strip_tags( $value ) ) );
}
function sanitize_textarea_field( string $value ): string { return trim( strip_tags( $value ) ); }
function esc_url_raw( string $value ): string { return $value; }
function wp_json_encode( $value, int $flags = 0 ) { return json_encode( $value, $flags ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

$GLOBALS['test_attachments'] = array(
	501 => array( 'type' => 'attachment', 'is_image' => true ),
	502 => array( 'type' => 'attachment', 'is_image' => false ), // PDF, por exemplo.
	// 503 nao existe.
);

function get_post_type( $id ): string {
	$id = (int) $id;
	return isset( $GLOBALS['test_attachments'][ $id ] ) ? $GLOBALS['test_attachments'][ $id ]['type'] : '';
}

function wp_attachment_is_image( $id ): bool {
	$id = (int) $id;
	return $GLOBALS['test_attachments'][ $id ]['is_image'] ?? false;
}

function absint( $value ): int {
	return abs( (int) $value );
}

/*
 * Copia fiel de contorno_cep_digits()/contorno_format_cep()
 * (includes/data/geo.php): esse arquivo tem codigo de topo que depende do
 * WordPress inteiro (cron, DAY_IN_SECONDS), entao nao da pra dar require
 * nele aqui — so as duas funcoes puras que este teste precisa.
 */
function contorno_cep_digits( string $value ): string {
	$trimmed = trim( $value );

	if ( '' === $trimmed || 1 !== preg_match( '/^[\d.\-\s]+$/', $trimmed ) ) {
		return '';
	}

	$digits = (string) preg_replace( '/\D/', '', $trimmed );

	return 8 === strlen( $digits ) ? $digits : '';
}

function contorno_format_cep( string $digits ): string {
	return 8 === strlen( $digits ) ? substr( $digits, 0, 5 ) . '-' . substr( $digits, 5 ) : $digits;
}

require $plugin . '/includes/helpers.php';
require $plugin . '/includes/meta/fields.php';

// --- Harness ----------------------------------------------------------------

$pass = 0;
$fail = 0;

function check( string $label, $actual, $expected ): void {
	global $pass, $fail;

	if ( $actual === $expected ) {
		++$pass;
		printf( "  [OK]       %-46s -> %s\n", $label, var_export( $actual, true ) );
	} else {
		++$fail;
		printf( "  [FALHOU]   %-46s -> %s (esperado %s)\n", $label, var_export( $actual, true ), var_export( $expected, true ) );
	}
}

echo "== CEP (contorno_sanitize_field, type=cep) ==\n";
$cep_def = array( 'type' => 'cep' );
check( 'so digitos, sem pontuacao', contorno_sanitize_field( '30360240', $cep_def ), '30360-240' );
check( 'colado com hifen', contorno_sanitize_field( '30360-240', $cep_def ), '30360-240' );
check( 'colado com espaco/ponto', contorno_sanitize_field( '30.360-240', $cep_def ), '30360-240' );
check( 'incompleto: preserva o texto, nao apaga', contorno_sanitize_field( '3036', $cep_def ), '3036' );
check( 'vazio continua vazio', contorno_sanitize_field( '', $cep_def ), '' );

echo "\n== Telefone/WhatsApp (type=phone) ==\n";
$phone_def = array( 'type' => 'phone' );
check( 'fixo formatado -> so digitos', contorno_sanitize_field( '(31) 4042-0177', $phone_def ), '3140420177' );
check( 'celular colado -> so digitos', contorno_sanitize_field( '31999999999', $phone_def ), '31999999999' );
check( 'com DDI 55 mantido (nao deduplicado aqui)', contorno_sanitize_field( '+55 31 99999-9999', $phone_def ), '5531999999999' );

echo "\n== Coordenadas (type=coordinate) ==\n";
$lat_def = array( 'type' => 'coordinate', 'min' => -90, 'max' => 90 );
$lng_def = array( 'type' => 'coordinate', 'min' => -180, 'max' => 180 );
check( 'latitude com virgula -> ponto', contorno_sanitize_field( '-19,9636285', $lat_def ), '-19.9636285' );
check( 'longitude com ponto mantem', contorno_sanitize_field( '-43.9492036', $lng_def ), '-43.9492036' );
check( 'latitude fora da faixa: preserva o digitado', contorno_sanitize_field( '95,5', $lat_def ), '95,5' );
check( 'letras: preserva o digitado, nao vira 0', contorno_sanitize_field( 'abc', $lat_def ), 'abc' );
check( 'dois sinais: preserva o digitado', contorno_sanitize_field( '--19.5', $lat_def ), '--19.5' );
check( 'vazio continua vazio', contorno_sanitize_field( '', $lat_def ), '' );

echo "\n== Galeria (type=media_list) ==\n";
$gallery_def = array( 'type' => 'media_list' );
check(
	'ID de imagem real entra',
	contorno_sanitize_field( array( '501' ), $gallery_def ),
	'["501"]'
);
check(
	'ID que existe mas NAO e imagem e descartado',
	contorno_sanitize_field( array( '502' ), $gallery_def ),
	'[]'
);
check(
	'ID inexistente e descartado (sem referencia arbitraria)',
	contorno_sanitize_field( array( '503' ), $gallery_def ),
	'[]'
);
check(
	'path legado continua aceito',
	contorno_sanitize_field( array( '/units/xml/barbacena/hero-01.jpg' ), $gallery_def ),
	'["/units/xml/barbacena/hero-01.jpg"]'
);
check(
	'mistura: imagem real + path legado + invalido descartado, ordem preservada',
	contorno_sanitize_field( array( '501', '/units/x.jpg', '503', '' ), $gallery_def ),
	'["501","/units/x.jpg"]'
);

echo "\n== Preço (contorno_sanitize_field, type=money) ==\n";
$money_def = array( 'type' => 'money' );
check( '"99" -> "99" (mesmo formato do type=number antigo)', contorno_sanitize_field( '99', $money_def ), '99' );
check( '"99,9" -> "99.9"', contorno_sanitize_field( '99,9', $money_def ), '99.9' );
check( '"1299,90" -> "1299.9"', contorno_sanitize_field( '1299,90', $money_def ), '1299.9' );
check( '"1.299,90" (ja com milhar) -> "1299.9"', contorno_sanitize_field( '1.299,90', $money_def ), '1299.9' );
check( '"R$ 99,90" (colado com prefixo) -> "99.9", nunca salva R$', contorno_sanitize_field( 'R$ 99,90', $money_def ), '99.9' );
check( '"R$ 1.299,90" -> "1299.9"', contorno_sanitize_field( 'R$ 1.299,90', $money_def ), '1299.9' );
check( '"99.90" (formato solto com ponto decimal) -> "99.9"', contorno_sanitize_field( '99.90', $money_def ), '99.9' );
check( 'vazio continua vazio', contorno_sanitize_field( '', $money_def ), '' );
check( 'sinal de menos e ignorado (preco nunca e negativo)', contorno_sanitize_field( '-50', $money_def ), '50' );
check( 'lixo sem numero: preserva o texto, nao zera', contorno_sanitize_field( 'abc', $money_def ), 'abc' );

printf( "\n%d passaram, %d falharam\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
