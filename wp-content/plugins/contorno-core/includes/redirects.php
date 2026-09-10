<?php
/**
 * Redirects 301 versionados.
 *
 * Centralizados aqui de proposito: ficam no Git, sao revisaveis e nao
 * dependem de edicao manual do .htaccess no servidor.
 *
 * No React estas duas rotas NAO sao paginas — sao redirects declarados no
 * roteador (`beforeLoad: throw redirect(...)`). Portá-las como paginas seria
 * inventar conteudo que nunca existiu.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mapa de redirects.
 *
 * Chave  = caminho de origem, sem barra final e sem query.
 * Valor  = array com:
 *   'to'    caminho de destino
 *   'keep'  parametros de query preservados no destino
 *
 * @return array<string,array{to:string,keep:array<int,string>}>
 */
function contorno_redirect_map(): array {
	$map = array(
		// Legado: era o passo intermediario antes do checkout. Hoje o React
		// manda direto para /matricula/ preservando unidade e plano.
		'/pre-checkout' => array(
			'to'   => '/matricula/',
			'keep' => array( 'unidade', 'plano' ),
		),

		// Legado: pagina de agradecimento. Hoje a confirmacao e /matricula/confirmacao/.
		'/obrigado'     => array(
			'to'   => '/matricula/confirmacao/',
			'keep' => array(),
		),
	);

	/**
	 * Permite adicionar redirects sem editar o plugin.
	 *
	 * @param array<string,array{to:string,keep:array<int,string>}> $map
	 */
	return (array) apply_filters( 'contorno_redirect_map', $map );
}

/**
 * Aplica o redirect antes de qualquer saida.
 *
 * Roda em `template_redirect` para nao interferir no admin, no REST nem no
 * cron, e usa 301 (permanente) para o buscador consolidar a URL de destino.
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';

		if ( '' === $request ) {
			return;
		}

		$path = (string) wp_parse_url( $request, PHP_URL_PATH );
		$path = '/' . trim( $path, '/' );

		$map = contorno_redirect_map();

		if ( ! isset( $map[ $path ] ) ) {
			return;
		}

		$rule   = $map[ $path ];
		$target = home_url( $rule['to'] );

		// Preserva apenas os parametros declarados — nada de repassar query solta.
		$carry = array();
		foreach ( $rule['keep'] as $param ) {
			$value = isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $param ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' !== $value ) {
				$carry[ $param ] = $value;
			}
		}

		if ( array() !== $carry ) {
			$target = add_query_arg( $carry, $target );
		}

		// Guarda contra loop: se o destino resolve para o mesmo caminho, aborta.
		if ( '/' . trim( (string) wp_parse_url( $target, PHP_URL_PATH ), '/' ) === $path ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	},
	1
);
