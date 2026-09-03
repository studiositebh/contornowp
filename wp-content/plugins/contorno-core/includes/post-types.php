<?php
/**
 * Custom post types e taxonomias.
 *
 * Arquitetura escalavel: UMA entrada por unidade / por CTN, renderizada por UM
 * template. Nunca criar pagina duplicada com conteudo hardcoded por academia.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_CPT_UNIT = 'unidade';
const CONTORNO_CPT_CTN  = 'ctn';

const CONTORNO_TAX_CITY      = 'unidade_cidade';
const CONTORNO_TAX_UNIT_KIND = 'unidade_tipo';

/**
 * Registro dos CPTs e taxonomias.
 *
 * Funcao nomeada para tambem ser chamada no hook de ativacao do plugin,
 * garantindo que os rewrites de /unidades e /ctn existam desde o inicio.
 */
function contorno_register_post_types(): void {
		register_post_type(
			CONTORNO_CPT_UNIT,
			array(
				'labels'              => array(
					'name'               => __( 'Unidades', 'contorno' ),
					'singular_name'      => __( 'Unidade', 'contorno' ),
					'add_new_item'       => __( 'Adicionar unidade', 'contorno' ),
					'edit_item'          => __( 'Editar unidade', 'contorno' ),
					'search_items'       => __( 'Buscar unidades', 'contorno' ),
					'not_found'          => __( 'Nenhuma unidade encontrada', 'contorno' ),
					'menu_name'          => __( 'Unidades', 'contorno' ),
				),
				'public'              => true,
				/*
				 * A listagem /unidades e uma PAGINA editavel no WPBakery, nao um
				 * arquivo de CPT — foi decisao de editabilidade. Por isso
				 * has_archive fica desligado: a pagina com slug "unidades"
				 * responde por /unidades, e o CPT responde por /unidades/{slug}.
				 */
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => 'unidades',
					'with_front' => false,
				),
				/*
				 * query_var DESLIGADO de proposito.
				 *
				 * Ligado, o WordPress aceita ?unidade={slug} como consulta do
				 * CPT — e o fluxo de matricula usa exatamente esse parametro
				 * (/matricula/?unidade=alfenas&plano=top, herdado do React).
				 * Com o query_var ativo, a URL era resolvida como o post da
				 * unidade e a pagina de matricula nunca aparecia.
				 *
				 * As URLs bonitas /unidades/{slug} continuam funcionando: elas
				 * vem da regra de rewrite, nao do query_var.
				 */
				'query_var'           => false,
				'publicly_queryable'  => true,
				'menu_icon'           => 'dashicons-location-alt',
				'menu_position'       => 21,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes' ),
				'show_in_rest'        => true,
				'rest_base'           => 'unidades',
				'hierarchical'        => false,
				'exclude_from_search' => false,
			)
		);

		register_post_type(
			CONTORNO_CPT_CTN,
			array(
				'labels'        => array(
					'name'          => __( 'CTNs', 'contorno' ),
					'singular_name' => __( 'CTN', 'contorno' ),
					'add_new_item'  => __( 'Adicionar CTN', 'contorno' ),
					'edit_item'     => __( 'Editar CTN', 'contorno' ),
					'menu_name'     => __( 'CTNs', 'contorno' ),
				),
				'public'        => true,
				// Idem: /ctn e uma pagina editavel; /ctn/{slug} e o CPT.
				'has_archive'   => false,
				'rewrite'       => array(
					'slug'       => 'ctn',
					'with_front' => false,
				),
				// Mesma razão da unidade: livra ?ctn= para uso próprio.
				'query_var'          => false,
				'publicly_queryable' => true,
				'menu_icon'     => 'dashicons-superhero',
				'menu_position' => 22,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'show_in_rest'  => true,
				'rest_base'     => 'ctn',
				'hierarchical'  => false,
			)
		);

		register_taxonomy(
			CONTORNO_TAX_CITY,
			array( CONTORNO_CPT_UNIT, CONTORNO_CPT_CTN ),
			array(
				'labels'            => array(
					'name'          => __( 'Cidades', 'contorno' ),
					'singular_name' => __( 'Cidade', 'contorno' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => 'unidades/cidade',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			CONTORNO_TAX_UNIT_KIND,
			array( CONTORNO_CPT_UNIT ),
			array(
				'labels'            => array(
					'name'          => __( 'Tipos de unidade', 'contorno' ),
					'singular_name' => __( 'Tipo de unidade', 'contorno' ),
				),
				'public'            => false,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
			)
		);
}

add_action( 'init', 'contorno_register_post_types' );

/**
 * Termos fixos de "tipo de unidade" — espelham UnitKind do React
 * (standard | prime | ctn-prime).
 */
add_action(
	'init',
	static function (): void {
		if ( ! taxonomy_exists( CONTORNO_TAX_UNIT_KIND ) ) {
			return;
		}

		$kinds = array(
			'standard'  => __( 'Padrão', 'contorno' ),
			'prime'     => __( 'Prime', 'contorno' ),
			'ctn-prime' => __( 'CTN / Centro de Treinamento', 'contorno' ),
		);

		foreach ( $kinds as $slug => $label ) {
			if ( ! term_exists( $slug, CONTORNO_TAX_UNIT_KIND ) ) {
				wp_insert_term( $label, CONTORNO_TAX_UNIT_KIND, array( 'slug' => $slug ) );
			}
		}
	},
	20
);

/**
 * Base das categorias do blog: /categoria/{slug}.
 *
 * E a URL do React (src/routes/categoria/$slug.tsx). Trocar a base nativa do
 * WordPress ("category") preserva a URL sem precisar de redirect.
 *
 * Os posts continuam em /{slug}, como no React — a estrutura de permalink
 * /%postname%/ ja entrega isso, e paginas tem precedencia em caso de colisao.
 */
add_action(
	'init',
	static function (): void {
		if ( 'categoria' === get_option( 'category_base' ) ) {
			return;
		}

		global $wp_rewrite;

		if ( ! $wp_rewrite instanceof WP_Rewrite ) {
			return;
		}

		/*
		 * update_option() sozinho nao basta: o WP_Rewrite ja leu a base neste
		 * request, e as regras gravadas continuariam com "category".
		 * set_category_base() + flush_rules(true) e o caminho correto.
		 */
		$wp_rewrite->set_category_base( 'categoria' );
		$wp_rewrite->flush_rules( true );
	},
	15
);

/**
 * Flush de rewrite quando a estrutura muda (troca de tema).
 */
add_action(
	'after_switch_theme',
	static function (): void {
		flush_rewrite_rules();
	}
);

/**
 * As paginas /unidades e /ctn precisam existir para as URLs funcionarem.
 * Avisa no painel enquanto nao existirem, com link para a tela de migracao.
 */
add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$missing = array();

		foreach ( array( 'unidades' => __( 'Unidades', 'contorno' ), 'ctn' => __( 'CTN', 'contorno' ) ) as $slug => $label ) {
			if ( ! get_page_by_path( $slug ) instanceof WP_Post ) {
				$missing[] = $label . ' (/' . $slug . ')';
			}
		}

		if ( array() === $missing ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Contorno:', 'contorno' ),
			esc_html(
				sprintf(
					/* translators: %s: list of missing pages */
					__( 'as páginas de listagem ainda não existem: %s.', 'contorno' ),
					implode( ', ', $missing )
				)
			),
			esc_url( admin_url( 'admin.php?page=contorno-migracao' ) ),
			esc_html__( 'Criar agora', 'contorno' )
		);
	}
);
