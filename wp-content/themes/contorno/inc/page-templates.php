<?php
/**
 * Templates de pagina disponiveis ao editor.
 *
 * Permitem montar paginas inteiras no WPBakery com largura total, e marcar
 * uma pagina como CTN (sem header institucional, skin dark).
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marca a pagina como contexto CTN quando o template CTN esta selecionado.
 */
add_filter(
	'contorno_is_ctn_context',
	static function ( bool $is_ctn ): bool {
		if ( $is_ctn || ! is_page() ) {
			return $is_ctn;
		}

		return 'templates/page-ctn.php' === get_page_template_slug( get_queried_object_id() );
	}
);

/**
 * Aviso no editor: paginas montadas no builder devem usar "Pagina em branco"
 * para o componente controlar toda a largura.
 */
add_action(
	'edit_form_after_title',
	static function ( WP_Post $post ): void {
		if ( 'page' !== $post->post_type ) {
			return;
		}

		if ( '' !== (string) get_page_template_slug( $post->ID ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info inline"><p>%s</p></div>',
			esc_html__( 'Dica: para montar esta página com os elementos CONTORNO no WPBakery, selecione o template "Página em branco (WPBakery)" em Atributos da página.', 'contorno' )
		);
	}
);
