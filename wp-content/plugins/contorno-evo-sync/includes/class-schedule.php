<?php
/**
 * Grade semanal nativa de aulas coletivas (substitui o iframe do EVO Totem).
 *
 * Campos de GET /api/v1/activities/schedule confirmados em 16/10/2026 contra
 * as 66 filiais: name, area, capacity, ocupation, startTime, endTime,
 * activityDate, instructor, status/statusName, allowExperimentalClass,
 * experimentalClassSlots, idAtividadeSessao.
 *
 * Regras:
 *
 *  - A API NUNCA e consultada durante a renderizacao da pagina (a EVO limita
 *    40 req/min por IP e o cliente dorme/re-tenta em 429). A pagina le so o
 *    cache; se ele faltar ou estiver velho, a filial entra numa fila e um
 *    evento de cron busca a grade. Enquanto isso a pagina mostra o iframe.
 *  - Orcamento proprio de chamadas por minuto, abaixo do limite da EVO, para
 *    sobrar espaco para o sync de planos e o checkout.
 *  - Fail-closed: aula sem nome, horario ou data valida e descartada; grade
 *    vazia, truncada ou com erro nao vira grade — vira iframe. Nada e
 *    inventado, e instructorPhoto (URL assinada que expira) nunca e guardada.
 *  - CTNs (filiais 50 e 57) passam de 500 aulas na semana; quando a consulta
 *    da semana volta cheia (= truncada), a filial passa a ser consultada dia
 *    a dia (7 chamadas) e isso fica lembrado.
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Schedule {

	public const HOOK = 'contorno_evo_schedule_refresh';

	/** Grade com ate 15 min e servida sem pedir atualizacao. */
	public const FRESH = 900;

	/** Acima disto a grade nao e mais exibida (vagas podem ter mudado). */
	public const MAX_AGE = 3600;

	/** Depois de uma falha, a filial so e tentada de novo apos este tempo. */
	public const RETRY_AFTER = 300;

	/** Chamadas de grade por minuto, somando todos os processos (EVO: 40). */
	public const BUDGET_PER_MIN = 20;

	/** take enviado a EVO. Resposta com este tamanho e tratada como truncada. */
	public const TAKE = 500;

	private const OPT_QUEUE = 'contorno_evo_schedule_queue';
	private const OPT_DAILY = 'contorno_evo_schedule_daily';
	private const KEY_WEEK  = 'contorno_evo_week_';
	private const KEY_FAIL  = 'contorno_evo_week_fail_';
	private const KEY_BUDG  = 'contorno_evo_sch_budget';

	public static function boot(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/* ---------------------------------------------------------------
	 * Leitura (pagina)
	 * ------------------------------------------------------------- */

	/**
	 * Grade da semana corrente para exibir, ou null (= mostrar o iframe).
	 *
	 * Nunca chama a API. Se a grade faltar ou passar de FRESH, a filial entra
	 * na fila do cron.
	 *
	 * @return array<string,mixed>|null  formato de parse() + fetched_at
	 */
	public static function week( int $id_branch, ?int $now = null ): ?array {
		if ( $id_branch <= 0 ) {
			return null;
		}

		$now    = $now ?? time();
		$monday = self::monday( $now );
		$stored = get_transient( self::KEY_WEEK . $id_branch );

		$valid = is_array( $stored )
			&& ( $stored['monday'] ?? '' ) === $monday
			&& isset( $stored['fetched_at'], $stored['days'] )
			&& $now - (int) $stored['fetched_at'] <= self::MAX_AGE;

		if ( ! $valid || $now - (int) $stored['fetched_at'] > self::FRESH ) {
			self::enqueue( $id_branch, $now );
		}

		return $valid ? $stored : null;
	}

	/**
	 * Segunda-feira (Y-m-d, fuso do site) da semana de $now.
	 */
	public static function monday( int $now ): string {
		$day = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( wp_timezone() );

		return $day->modify( '-' . ( (int) $day->format( 'N' ) - 1 ) . ' days' )->format( 'Y-m-d' );
	}

	/* ---------------------------------------------------------------
	 * Fila + cron
	 * ------------------------------------------------------------- */

	public static function enqueue( int $id_branch, ?int $now = null ): void {
		$now = $now ?? time();

		// Falhou ha pouco: nao martelar a EVO a cada visita.
		if ( false !== get_transient( self::KEY_FAIL . $id_branch ) ) {
			return;
		}

		$queue = get_option( self::OPT_QUEUE, array() );
		$queue = is_array( $queue ) ? $queue : array();

		if ( ! isset( $queue[ $id_branch ] ) ) {
			$queue[ $id_branch ] = $now;
			update_option( self::OPT_QUEUE, $queue, false );
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( $now, self::HOOK );
		}
	}

	/**
	 * Evento de cron: atualiza as filiais da fila dentro do orcamento.
	 */
	public static function run(): void {
		$queue = get_option( self::OPT_QUEUE, array() );
		$queue = is_array( $queue ) ? $queue : array();
		$daily = self::daily_branches();

		asort( $queue );

		foreach ( array_keys( $queue ) as $id_branch ) {
			$id_branch = (int) $id_branch;
			$needed    = isset( $daily[ $id_branch ] ) ? 7 : 1;

			if ( ! self::take_budget( $needed ) ) {
				break;
			}

			$result = self::refresh( $id_branch );

			// Truncada na semana cheia: passou para dia a dia, que pede
			// outro orcamento. Fica na fila para a proxima rodada.
			if ( 'retry_daily' === $result ) {
				$daily = self::daily_branches();
				continue;
			}

			unset( $queue[ $id_branch ] );
		}

		$current = get_option( self::OPT_QUEUE, array() );
		$current = is_array( $current ) ? $current : array();

		// Mantem o que entrou na fila enquanto o cron rodava.
		foreach ( $current as $id => $ts ) {
			if ( ! isset( $queue[ $id ] ) && ! self::processed_recently( (int) $id ) ) {
				$queue[ $id ] = $ts;
			}
		}

		update_option( self::OPT_QUEUE, $queue, false );

		if ( array() !== $queue && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::HOOK );
		}
	}

	/**
	 * Busca e grava a grade de uma filial.
	 *
	 * @return string ok | empty | error | truncated | retry_daily
	 */
	public static function refresh( int $id_branch, ?int $now = null ): string {
		$now    = $now ?? time();
		$monday = self::monday( $now );
		$daily  = self::daily_branches();
		$items  = array();

		if ( isset( $daily[ $id_branch ] ) ) {
			for ( $i = 0; $i < 7; $i++ ) {
				$date = gmdate( 'Y-m-d', strtotime( $monday . ' +' . $i . ' days UTC' ) );
				$res  = Contorno_Evo_Activities::schedule( $id_branch, array( 'date' => $date, 'take' => self::TAKE ), true );

				if ( ! $res['ok'] ) {
					return self::fail( $id_branch, 'error', $res['message'] );
				}

				if ( count( $res['items'] ) >= self::TAKE ) {
					return self::fail( $id_branch, 'truncated', 'dia ' . $date . ' veio com ' . self::TAKE . ' aulas' );
				}

				$items = array_merge( $items, $res['items'] );
			}
		} else {
			$res = Contorno_Evo_Activities::schedule( $id_branch, array( 'date' => $monday, 'showFullWeek' => 'true', 'take' => self::TAKE ), true );

			if ( ! $res['ok'] ) {
				return self::fail( $id_branch, 'error', $res['message'] );
			}

			if ( count( $res['items'] ) >= self::TAKE ) {
				$daily[ $id_branch ] = true;
				update_option( self::OPT_DAILY, $daily, false );

				return 'retry_daily';
			}

			$items = $res['items'];
		}

		$week = self::parse( $items, $monday );

		if ( 0 === $week['total'] ) {
			return self::fail( $id_branch, 'empty', count( $items ) . ' itens, nenhum valido' );
		}

		$week['fetched_at'] = $now;
		set_transient( self::KEY_WEEK . $id_branch, $week, self::MAX_AGE );
		delete_transient( self::KEY_FAIL . $id_branch );

		return 'ok';
	}

	private static function fail( int $id_branch, string $code, string $detail ): string {
		set_transient( self::KEY_FAIL . $id_branch, $code, self::RETRY_AFTER );

		if ( class_exists( 'Contorno_Evo_Log' ) ) {
			Contorno_Evo_Log::add( 'warning', sprintf( 'Grade nativa filial %d: %s (%s). Pagina usa o iframe.', $id_branch, $code, $detail ) );
		}

		return $code;
	}

	private static function processed_recently( int $id_branch ): bool {
		$stored = get_transient( self::KEY_WEEK . $id_branch );

		return ( is_array( $stored ) && time() - (int) ( $stored['fetched_at'] ?? 0 ) < self::FRESH )
			|| false !== get_transient( self::KEY_FAIL . $id_branch );
	}

	/** @return array<int,bool> */
	private static function daily_branches(): array {
		$daily = get_option( self::OPT_DAILY, array() );

		return is_array( $daily ) ? $daily : array();
	}

	/**
	 * Reserva $n chamadas no orcamento do minuto corrente.
	 *
	 * Contador em transient por minuto: nao e atomico, mas o cron roda um
	 * evento por vez e o orcamento (20) fica com folga ate o limite (40).
	 */
	public static function take_budget( int $n, ?int $now = null ): bool {
		$minute = (int) floor( ( $now ?? time() ) / 60 );
		$state  = get_transient( self::KEY_BUDG );
		$used   = is_array( $state ) && (int) ( $state['m'] ?? 0 ) === $minute ? (int) ( $state['n'] ?? 0 ) : 0;

		if ( $used + $n > self::BUDGET_PER_MIN ) {
			return false;
		}

		set_transient( self::KEY_BUDG, array( 'm' => $minute, 'n' => $used + $n ), 120 );

		return true;
	}

	/* ---------------------------------------------------------------
	 * Parser (puro — coberto por scripts/test-evo-schedule.php)
	 * ------------------------------------------------------------- */

	/**
	 * Normaliza a resposta crua da EVO numa semana seg..dom.
	 *
	 * @param array<int,mixed> $items   itens crus de /activities/schedule
	 * @param string           $monday  Y-m-d da segunda-feira da semana
	 * @return array{monday:string,days:array<int,array<int,array<string,mixed>>>,total:int,skipped:int}
	 */
	public static function parse( array $items, string $monday ): array {
		$days = array_fill( 1, 7, array() );
		$seen = array();
		$skip = 0;
		$base = DateTimeImmutable::createFromFormat( '!Y-m-d', $monday, new DateTimeZone( 'UTC' ) );

		if ( false === $base ) {
			return array( 'monday' => $monday, 'days' => $days, 'total' => 0, 'skipped' => count( $items ) );
		}

		foreach ( $items as $raw ) {
			$class = is_array( $raw ) ? self::parse_item( $raw, $base ) : null;

			if ( null === $class ) {
				++$skip;
				continue;
			}

			$key = '' !== $class['id'] ? $class['id'] : $class['date'] . '|' . $class['start'] . '|' . $class['name'] . '|' . $class['area'];

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ]            = true;
			$days[ $class['day'] ][] = $class;
		}

		foreach ( $days as &$list ) {
			usort(
				$list,
				static fn ( array $a, array $b ): int => array( $a['start'], $a['name'] ) <=> array( $b['start'], $b['name'] )
			);
		}
		unset( $list );

		return array(
			'monday'  => $monday,
			'days'    => $days,
			'total'   => count( $seen ),
			'skipped' => $skip,
		);
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>|null
	 */
	private static function parse_item( array $raw, DateTimeImmutable $monday ): ?array {
		$name  = self::text( $raw['name'] ?? '' );
		$start = self::hhmm( $raw['startTime'] ?? '' );

		if ( '' === $name || '' === $start || ! is_string( $raw['activityDate'] ?? null ) ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', substr( $raw['activityDate'], 0, 10 ), new DateTimeZone( 'UTC' ) );

		if ( false === $date ) {
			return null;
		}

		$offset = (int) $monday->diff( $date )->format( '%r%a' );

		if ( $offset < 0 || $offset > 6 ) {
			return null;
		}

		$end = self::hhmm( $raw['endTime'] ?? '' );
		if ( '' !== $end && $end <= $start ) {
			$end = '';
		}

		$capacity = self::int_or_null( $raw['capacity'] ?? null );
		$occupied = self::int_or_null( $raw['ocupation'] ?? null );
		$left     = null !== $capacity && $capacity > 0 && null !== $occupied ? max( 0, $capacity - $occupied ) : null;

		$status = strtolower( self::text( $raw['statusName'] ?? '' ) );
		if ( str_starts_with( $status, 'cancel' ) ) {
			$state = 'canceled';
		} elseif ( 'finalized' === $status ) {
			$state = 'finished';
		} elseif ( 0 === $left ) {
			$state = 'full';
		} else {
			$state = 'open';
		}

		$hour       = (int) substr( $start, 0, 2 );
		$exp_slots  = self::int_or_null( $raw['experimentalClassSlots'] ?? null );
		$experiment = true === ( $raw['allowExperimentalClass'] ?? false );

		// instructorPhoto e description ficam de fora de proposito: a foto e
		// URL assinada que expira, e a descricao e texto longo de catalogo.
		return array(
			'id'           => isset( $raw['idAtividadeSessao'] ) && is_scalar( $raw['idAtividadeSessao'] ) ? 's' . $raw['idAtividadeSessao'] : '',
			'name'         => $name,
			'area'         => self::text( $raw['area'] ?? '' ),
			'instructor'   => self::person( self::text( $raw['instructor'] ?? '' ) ),
			'date'         => $date->format( 'Y-m-d' ),
			'day'          => $offset + 1,
			'start'        => $start,
			'end'          => $end,
			'period'       => $hour < 12 ? 'morning' : ( $hour < 18 ? 'afternoon' : 'night' ),
			'capacity'     => $capacity,
			'spots_left'   => $left,
			'state'        => $state,
			'experimental' => $experiment && ( null === $exp_slots || $exp_slots > 0 ),
		);
	}

	private static function text( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		return trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $value ) ) );
	}

	/** "07:00", "7:00", "07:00:00" -> "07:00"; qualquer outra coisa -> "". */
	private static function hhmm( mixed $value ): string {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim( $value ), $m ) ) {
			return '';
		}

		$h = (int) $m[1];
		$i = (int) $m[2];

		return $h <= 23 && $i <= 59 ? sprintf( '%02d:%02d', $h, $i ) : '';
	}

	private static function int_or_null( mixed $value ): ?int {
		if ( is_int( $value ) ) {
			return $value;
		}

		if ( is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
			return (int) $value;
		}

		return null;
	}

	/** "FELIPE BRAGA DANTAS" -> "Felipe Braga Dantas" (de/da/dos minusculos). */
	private static function person( string $name ): string {
		if ( '' === $name ) {
			return '';
		}

		$words = explode( ' ', mb_convert_case( mb_strtolower( $name, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' ) );

		foreach ( $words as $i => $word ) {
			if ( $i > 0 && in_array( mb_strtolower( $word, 'UTF-8' ), array( 'de', 'da', 'do', 'das', 'dos', 'e' ), true ) ) {
				$words[ $i ] = mb_strtolower( $word, 'UTF-8' );
			}
		}

		return implode( ' ', $words );
	}
}
