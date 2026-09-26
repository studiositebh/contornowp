<?php
/**
 * Rede de seguranca da Home: se a PRIMEIRA linha do WPBakery estiver vazia
 * (o Hero antigo foi removido sem o novo bloco ser inserido no lugar — foi
 * exatamente o que aconteceu em producao), restaura SO essa linha a partir
 * do dataset, no proprio carregamento da pagina.
 *
 * Por que nao so o auto-migrate (WP-Cron/deploy): o `wp contorno migrate`
 * do deploy ja tentou isso (merge_empty_rows() em importer.php) mas exige
 * que a pagina INTEIRA tenha o MESMO NUMERO de linhas que o dataset — a
 * Home real tem secoes promocionais (CTN Prime, App) que o dataset nao
 * conhece, entao a contagem nunca bate e o merge amplo desiste. Aqui o
 * alvo e so a PRIMEIRA linha: encontra o primeiro bloco [vc_row]...
 * [/vc_row] da pagina atual, e SO SE ele estiver vazio (nenhum elemento
 * de verdade dentro), troca pelo primeiro bloco do dataset — nao importa
 * quantas linhas existem depois, elas nunca sao tocadas.
 *
 * Roda a cada carregamento da Home (template_redirect); uma vez corrigida,
 * a proxima checagem encontra a linha ja preenchida e sai no primeiro if
 * — custo desprezivel.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Primeiro bloco [vc_row]...[/vc_row] de nivel raiz (nunca vc_row_inner) do
 * conteudo, com o offset onde ele comeca — ou null se nao achar nenhum.
 *
 * @return array{0:string,1:int}|null
 */
function contorno_first_top_level_row( string $content ): ?array {
	if ( ! preg_match( '/\[vc_row(?=[\s\]])[^\]]*\][\s\S]*?\[\/vc_row\]/', $content, $match, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	return array( $match[0][0], $match[0][1] );
}

/**
 * Se a primeira linha de $current estiver vazia e a primeira linha de
 * $dataset_content tiver conteudo de verdade, devolve $current com so essa
 * linha trocada. Caso contrario devolve null (nada a fazer — inclusive
 * quando a linha atual ja tem conteudo real, o caso normal).
 */
function contorno_heal_first_row( string $current, string $dataset_content ): ?string {
	$current_row = contorno_first_top_level_row( $current );

	if ( null === $current_row || ! Contorno_Migration::is_page_builder_shell_only( $current_row[0] ) ) {
		return null;
	}

	$dataset_row = contorno_first_top_level_row( $dataset_content );

	if ( null === $dataset_row || Contorno_Migration::is_page_builder_shell_only( $dataset_row[0] ) ) {
		return null;
	}

	return substr_replace( $current, $dataset_row[0], $current_row[1], strlen( $current_row[0] ) );
}

add_action(
	'template_redirect',
	static function (): void {
		if ( wp_doing_ajax() || wp_doing_cron() || ! is_front_page() ) {
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

		$healed = contorno_heal_first_row( $current, (string) $home['content'] );

		if ( null === $healed || $healed === $current ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $front_id,
				'post_content' => $healed,
			)
		);

		// Reflete a correcao NESTA MESMA requisicao, sem precisar recarregar.
		global $post;
		if ( $post instanceof WP_Post && $post->ID === $front_id ) {
			$post->post_content = $healed;
		}
	},
	5
);
