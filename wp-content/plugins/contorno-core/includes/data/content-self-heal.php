<?php
/**
 * Rede de seguranca da Home: se a primeira linha do WPBakery estiver vazia
 * (o Hero antigo foi removido sem o novo bloco ser inserido no lugar — foi
 * exatamente o que aconteceu em producao), restaura SO essa linha a partir
 * do dataset, no proprio carregamento da pagina — nao depende do WP-Cron
 * (que em alguns hosts so roda via crontab real, com atraso imprevisivel
 * daqui de fora) nem de acesso ao wp-admin pra corrigir manualmente.
 *
 * Reaproveita Contorno_Migration::merge_empty_rows() (mesma logica do
 * importador, ja usada no auto-migrate): qualquer linha com conteudo real
 * (edicao do cliente) nunca e tocada, e o merge so acontece quando a
 * pagina atual tem o MESMO NUMERO de linhas que o dataset — sem essas
 * condicoes, nao faz nada. Uma vez corrigida, a checagem seguinte encontra
 * a linha ja preenchida e sai no primeiro `if`, custo desprezivel.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'template_redirect',
	static function (): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ! is_front_page() ) {
			return;
		}

		$front_id = (int) get_option( 'page_on_front' );

		if ( 0 === $front_id ) {
			return;
		}

		$current = (string) get_post_field( 'post_content', $front_id );

		if ( '' === $current ) {
			return;
		}

		require_once CONTORNO_CORE_DIR . 'includes/migration/importer.php';

		$dataset = Contorno_Migration::read_dataset();
		$home    = null;

		foreach ( (array) ( $dataset['pages'] ?? array() ) as $page ) {
			if ( ! empty( $page['isFront'] ) ) {
				$home = $page;
				break;
			}
		}

		if ( null === $home || empty( $home['content'] ) ) {
			return;
		}

		$merged = Contorno_Migration::merge_empty_rows( $current, (string) $home['content'] );

		if ( null === $merged || $merged === $current ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $front_id,
				'post_content' => $merged,
			)
		);

		// Reflete a correcao NESTA MESMA requisicao, sem precisar recarregar.
		global $post;
		if ( $post instanceof WP_Post && $post->ID === $front_id ) {
			$post->post_content = $merged;
		}
	},
	5
);
