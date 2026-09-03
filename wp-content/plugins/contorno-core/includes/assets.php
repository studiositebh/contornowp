<?php
/**
 * Assets dos componentes funcionais.
 *
 * O plugin carrega o CSS/JS ESTRUTURAL dos componentes (layout, carrossel,
 * lightbox, iframe da agenda). A pele visual — cores, tipografia, sombras —
 * vem dos tokens do tema. Assim os componentes continuam funcionando se o
 * tema mudar, sem duplicar design system.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fallback de tokens: se o tema ativo nao for o Contorno, o plugin injeta um
 * conjunto minimo de variaveis para os componentes nao ficarem sem cor.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_register_style(
			'contorno-components',
			contorno_core_url( 'assets/css/components.css' ),
			array(),
			contorno_core_asset_version( 'assets/css/components.css' )
		);
	},
	5
);

/**
 * Fallback de tokens — decidido no FIM da fila.
 *
 * O tema Contorno registra 'contorno-tokens' na prioridade padrao (10); checar
 * antes disso acusaria ausencia e carregaria o fallback a toa.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( wp_style_is( 'contorno-tokens', 'registered' ) || wp_style_is( 'contorno-tokens', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style(
			'contorno-tokens-fallback',
			contorno_core_url( 'assets/css/tokens-fallback.css' ),
			array(),
			contorno_core_asset_version( 'assets/css/tokens-fallback.css' )
		);
	},
	99
);

/**
 * Assets sob demanda dos componentes dinamicos.
 *
 * Chamado pelos shortcodes: o cliente insere o componente em qualquer pagina
 * do WPBakery e o script acompanha, sem carregar tudo em toda pagina.
 */
function contorno_enqueue_component( string $component ): void {
	wp_enqueue_style( 'contorno-components' );

	$map = array(
		'units-carousel' => 'assets/js/units-carousel.js',
		'unit-search'    => 'assets/js/unit-search.js',
		'lightbox'       => 'assets/js/lightbox.js',
		'lazy-video'     => 'assets/js/lazy-video.js',
		'enrollment'     => 'assets/js/enrollment.js',
		'contact-form'   => 'assets/js/contact-form.js',
		'reveal'         => 'assets/js/reveal.js',
	);

	if ( ! isset( $map[ $component ] ) ) {
		return;
	}

	$handle = 'contorno-' . $component;

	if ( wp_script_is( $handle, 'enqueued' ) ) {
		return;
	}

	wp_enqueue_script(
		$handle,
		contorno_core_url( $map[ $component ] ),
		array(),
		contorno_core_asset_version( $map[ $component ] ),
		true
	);
}

/**
 * A animacao de entrada e usada por praticamente todo componente.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		contorno_enqueue_component( 'reveal' );
	},
	20
);

/**
 * Marca o documento como "tem JavaScript".
 *
 * As animacoes de entrada partem de opacity:0. Sem esta marca, um erro de JS
 * ou um bloqueio de script deixaria o conteudo invisivel — o CSS so esconde
 * o que sera revelado quando o JS estiver de fato rodando.
 */
add_action(
	'wp_head',
	static function (): void {
		echo '<script>document.documentElement.classList.add("contorno-js");</script>' . "\n";
	},
	1
);
