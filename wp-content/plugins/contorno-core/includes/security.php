<?php
/**
 * Endurecimento da superficie publica.
 *
 * Tudo aqui vive no plugin, e nao no .htaccess, por um motivo pratico: o
 * deploy automatico (scripts/cpanel-deploy.sh) sincroniza SOMENTE
 * wp-content/plugins/contorno-core, wp-content/plugins/contorno-evo-sync e
 * wp-content/themes/contorno. Um .htaccess versionado na raiz do repositorio
 * NUNCA chega ao servidor. O que precisa ir ao ar por git tem de estar em PHP.
 *
 * Cada bloco tem um filtro para desligar caso quebre alguma integracao:
 *   contorno_security_headers     array|false  cabecalhos da resposta publica
 *   contorno_disable_xmlrpc       bool         desliga /xmlrpc.php
 *   contorno_block_user_enum      bool         esconde a lista de usuarios
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 1. Cabecalhos de seguranca
 *
 * Conjunto deliberadamente conservador: nada aqui restringe de onde vem
 * script, estilo ou imagem, porque o site carrega WPBakery, Google Maps,
 * YouTube e o widget de agenda da EVO em iframe. A unica diretiva de CSP
 * usada e frame-ancestors, que so diz quem pode EMBUTIR o site — ela nao
 * interfere no que a pagina carrega.
 * ========================================================== */

/**
 * @return array<string,string>
 */
function contorno_security_headers(): array {
	return array(
		// Impede o navegador de "adivinhar" o tipo de um arquivo servido.
		'X-Content-Type-Options'  => 'nosniff',

		// Nao vaza a URL completa (com ?q=<CEP>) para terceiros.
		'Referrer-Policy'         => 'strict-origin-when-cross-origin',

		// Anti-clickjacking. frame-ancestors 'self' preserva o editor do
		// WPBakery, que carrega a propria pagina em iframe na mesma origem.
		'X-Frame-Options'         => 'SAMEORIGIN',
		'Content-Security-Policy' => "frame-ancestors 'self'",

		// O site nao usa nenhum destes. fullscreen fica de fora da lista
		// porque os iframes de video e da agenda EVO dependem dele.
		'Permissions-Policy'      => 'geolocation=(), camera=(), microphone=(), payment=(), usb=(), magnetometer=(), gyroscope=()',
	);
}

add_action(
	'send_headers',
	static function (): void {
		if ( is_admin() || headers_sent() ) {
			return;
		}

		/**
		 * @param array<string,string> $headers
		 */
		$headers = apply_filters( 'contorno_security_headers', contorno_security_headers() );

		if ( ! is_array( $headers ) ) {
			return;
		}

		foreach ( $headers as $name => $value ) {
			if ( is_string( $name ) && is_string( $value ) && '' !== $value ) {
				header( $name . ': ' . $value );
			}
		}

		// HSTS so faz sentido — e so e seguro — servido por HTTPS.
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=15552000' );
		}
	},
	20
);

/* ============================================================
 * 2. XML-RPC
 *
 * Nenhuma integracao do site usa /xmlrpc.php: os plugins ativos sao
 * contorno-core, contorno-evo-sync, WPBakery e o backup do host — nenhum
 * deles fala XML-RPC. Deixa-lo aberto entrega de graca dois vetores:
 *
 *   - system.multicall: centenas de tentativas de senha em UMA requisicao,
 *     o que anula qualquer contagem de tentativas por requisicao;
 *   - pingback.ping: transforma o servidor em proxy de requisicoes (SSRF)
 *     e em amplificador de DDoS contra terceiros.
 *
 * Se algum dia o aplicativo do WordPress ou o Jetpack forem usados, basta
 * add_filter( 'contorno_disable_xmlrpc', '__return_false' ).
 * ========================================================== */

// Fecha /xmlrpc.php antes de o servidor XML-RPC montar a resposta.
//
// Os filtros abaixo tiram todos os metodos do WordPress, mas nao os tres
// system.* — IXR_Server::setCallbacks() os registra DEPOIS do filtro
// xmlrpc_methods, e nao ha gancho para remove-los. Eles ficam inofensivos
// (system.multicall so despacha para o que esta registrado, e nao sobra
// nada), mas o endpoint continuaria de pe. Recusar a requisicao inteira e
// mais simples de verificar.
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return;
		}

		if ( ! (bool) apply_filters( 'contorno_disable_xmlrpc', true ) ) {
			return;
		}

		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'XML-RPC desativado.';
		exit;
	},
	0
);

add_filter(
	'xmlrpc_enabled',
	static function ( bool $enabled ): bool {
		return (bool) apply_filters( 'contorno_disable_xmlrpc', true ) ? false : $enabled;
	},
	20
);

// xmlrpc_enabled sozinho nao desliga os metodos que nao exigem autenticacao
// (pingback e companhia). Esvaziar a lista tira todos.
add_filter(
	'xmlrpc_methods',
	static function ( array $methods ): array {
		return (bool) apply_filters( 'contorno_disable_xmlrpc', true ) ? array() : $methods;
	},
	20
);

// Nao anuncia o endpoint nem aceita pingback de entrada.
add_filter( 'pings_open', '__return_false', 20 );
remove_action( 'wp_head', 'rsd_link' );

add_filter(
	'wp_headers',
	static function ( array $headers ): array {
		if ( (bool) apply_filters( 'contorno_disable_xmlrpc', true ) ) {
			unset( $headers['X-Pingback'] );
		}

		return $headers;
	},
	20
);

/* ============================================================
 * 3. Enumeracao de usuarios
 *
 * /wp-json/wp/v2/users e /?author=1 devolvem, sem login, o nome e o slug de
 * cada autor. O slug e, na pratica, o nome de usuario do painel — e meio
 * caminho andado para um ataque de senha. O site nao tem area de autor nem
 * assinatura de post visivel, entao nada se perde ao fechar.
 *
 * Fecha so para quem NAO esta logado: o painel e o editor de blocos, que
 * usam a mesma rota, continuam funcionando.
 * ========================================================== */

function contorno_should_block_user_enum(): bool {
	return (bool) apply_filters( 'contorno_block_user_enum', true ) && ! is_user_logged_in();
}

add_filter(
	'rest_endpoints',
	static function ( array $endpoints ): array {
		if ( ! contorno_should_block_user_enum() ) {
			return $endpoints;
		}

		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

		return $endpoints;
	},
	20
);

// /?author=1 -> 404 em vez de redirecionar para /author/<login>/.
add_action(
	'template_redirect',
	static function (): void {
		if ( ! contorno_should_block_user_enum() ) {
			return;
		}

		$is_author_probe = isset( $_GET['author'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| is_author();

		if ( ! $is_author_probe ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	},
	1
);

// Tira o mapa de autores do sitemap (mesma informacao, outra porta).
add_filter(
	'wp_sitemaps_add_provider',
	static function ( $provider, string $name ) {
		return 'users' === $name && (bool) apply_filters( 'contorno_block_user_enum', true ) ? false : $provider;
	},
	20,
	2
);

/* ============================================================
 * 4. Identificacao de versao
 *
 * A versao exata do WordPress no HTML e no feed poupa o trabalho de
 * descobrir contra qual falha conhecida atacar. Nao e protecao — e nao
 * atrasar o atacante de graca. A atualizacao do core continua sendo o que
 * realmente resolve.
 * ========================================================== */

remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string', 20 );
