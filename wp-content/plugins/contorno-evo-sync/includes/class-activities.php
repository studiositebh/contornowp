<?php
/**
 * Atividades e grade horaria — infraestrutura, sem frontend ainda.
 *
 * A grade de aulas do site continua sendo o iframe do EVO Totem
 * (contorno_schedule_url, no contorno-core). Esta classe existe para que a
 * troca do iframe por grade nativa seja possivel DEPOIS, com resposta real da
 * API na mao — e nao para adivinhar agora como a EVO devolve horario,
 * professor, vaga e aula experimental.
 *
 * O que ja esta pronto e testavel sem credencial: cache, paginacao, erro e
 * a forma dos metodos. O que falta e o unico pedaco que nao se pode inventar:
 * o significado dos campos de /api/v1/activities/schedule. Nenhum template
 * consome isto ainda.
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Activities {

	/** Catalogo de atividades muda pouco. */
	public const TTL_ACTIVITIES = 21600; // 6 h

	/** Grade muda todo dia e tem vaga disponivel no meio. */
	public const TTL_SCHEDULE = 900; // 15 min

	/**
	 * Atividades de uma filial, paginando ate o fim.
	 *
	 * @return array{ok:bool,message:string,items:array<int,array<string,mixed>>,cached:bool}
	 */
	public static function activities( int $id_branch, bool $force = false ): array {
		if ( $id_branch <= 0 ) {
			return array( 'ok' => false, 'message' => __( 'Filial não informada.', 'contorno-evo' ), 'items' => array(), 'cached' => false );
		}

		$key = 'contorno_evo_act_' . $id_branch;

		if ( ! $force ) {
			$cached = get_transient( $key );

			if ( is_array( $cached ) ) {
				return array( 'ok' => true, 'message' => '', 'items' => $cached, 'cached' => true );
			}
		}

		$client = new Contorno_Evo_Client();
		$items  = array();
		$skip   = 0;
		$pages  = 0;

		do {
			$page = $client->activities( $id_branch, 50, $skip );
			++$pages;

			if ( ! $page['ok'] ) {
				// Erro NAO sobrescreve o cache: um 429 no meio da paginacao
				// nao pode apagar a ultima lista boa.
				return array( 'ok' => false, 'message' => $page['message'], 'items' => array(), 'cached' => false );
			}

			$items = array_merge( $items, $page['items'] );
			$skip += 50;
		} while ( count( $page['items'] ) >= 50 && $pages < 20 );

		set_transient( $key, $items, self::TTL_ACTIVITIES );

		return array( 'ok' => true, 'message' => '', 'items' => $items, 'cached' => false );
	}

	/**
	 * Grade horaria de uma filial.
	 *
	 * @param array<string,mixed> $extra  date (yyyy-mm-dd), showFullWeek, onlyAvailables
	 * @return array{ok:bool,message:string,items:array<int,array<string,mixed>>,cached:bool}
	 */
	public static function schedule( int $id_branch, array $extra = array(), bool $force = false ): array {
		if ( $id_branch <= 0 ) {
			return array( 'ok' => false, 'message' => __( 'Filial não informada.', 'contorno-evo' ), 'items' => array(), 'cached' => false );
		}

		// A chave inclui os filtros: grade de semana cheia e grade de um dia
		// sao respostas diferentes e nao podem compartilhar cache.
		$key = 'contorno_evo_sch_' . $id_branch . '_' . substr( md5( (string) wp_json_encode( $extra ) ), 0, 12 );

		if ( ! $force ) {
			$cached = get_transient( $key );

			if ( is_array( $cached ) ) {
				return array( 'ok' => true, 'message' => '', 'items' => $cached, 'cached' => true );
			}
		}

		$result = ( new Contorno_Evo_Client() )->activities_schedule( $id_branch, $extra );

		if ( ! $result['ok'] ) {
			return array( 'ok' => false, 'message' => $result['message'], 'items' => array(), 'cached' => false );
		}

		set_transient( $key, $result['items'], self::TTL_SCHEDULE );

		return array( 'ok' => true, 'message' => '', 'items' => $result['items'], 'cached' => false );
	}

	/**
	 * Limpa o cache de uma filial (ou de todas, se $id_branch for 0).
	 *
	 * Sem tabela propria de chaves: o cache de grade e por combinacao de
	 * filtros, e varrer o wp_options por LIKE em cada deploy custa mais do
	 * que deixar o TTL de 15 min expirar. Aqui limpamos o que e enderecavel.
	 */
	public static function flush( int $id_branch ): void {
		if ( $id_branch > 0 ) {
			delete_transient( 'contorno_evo_act_' . $id_branch );
		}
	}
}
