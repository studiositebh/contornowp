<?php
/**
 * Reconciliacao periodica via WP-Cron.
 *
 * A EVO nao tem webhook para alteracao de plano (eventos oficiais: NewSale,
 * CreateMember, AlterMember, NewInvoice), entao a reconciliacao periodica e o
 * mecanismo principal. Roda em lotes a cada 5 min; o ciclo completo (todas
 * as unidades) cabe no intervalo configuravel (30 min a 24 h).
 *
 * Recomendacao de servidor: DISABLE_WP_CRON + cron do sistema chamando
 * wp-cron.php a cada 5 min, para nao depender de visita.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contorno_Evo_Cron {

	public const SCHEDULE = 'contorno_evo_interval';

	/** Um lote a cada BATCH_MINUTES; o ciclo inteiro cabe em interval_minutes. */
	public const BATCH_MINUTES = 5;

	private const OPT_CURSOR = 'contorno_evo_sync_cursor';

	public static function boot(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( CONTORNO_EVO_CRON_HOOK, array( __CLASS__, 'run' ) );
		add_action( 'update_option_' . Contorno_Evo_Settings::OPTION, array( __CLASS__, 'reschedule' ) );
		add_action( 'init', array( __CLASS__, 'ensure' ) );
	}

	/**
	 * O evento so era criado na ativacao ou ao salvar as configuracoes: um
	 * plugin ja ativo (deploy por git) ficava sem reconciliacao. Recria se
	 * faltar; wp_next_scheduled le o option de cron ja carregado.
	 */
	public static function ensure(): void {
		if ( Contorno_Evo_Settings::get( 'auto_sync', false ) && Contorno_Evo_Settings::has_credentials() && false === wp_next_scheduled( CONTORNO_EVO_CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, CONTORNO_EVO_CRON_HOOK );
		}
	}

	/**
	 * @param array<string,array{interval:int,display:string}> $schedules
	 * @return array<string,array{interval:int,display:string}>
	 */
	public static function schedules( array $schedules ): array {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => self::BATCH_MINUTES * MINUTE_IN_SECONDS,
			'display'  => sprintf( /* translators: %d: minutes */ __( 'EVO: lote a cada %d min', 'contorno-evo' ), self::BATCH_MINUTES ),
		);

		return $schedules;
	}

	/**
	 * Unidades deste lote, em rodizio.
	 *
	 * Uma rodada inteira sao ~330 GETs (66 filiais, ate 9 paginas de planos):
	 * ~9 min no limite da EVO, mais do que uma requisicao de WP-Cron aguenta.
	 * Em lotes de ceil(unidades / (intervalo / 5 min)), cada unidade e
	 * reconciliada uma vez por intervalo (60 min -> 12 lotes de ~6 unidades,
	 * ~30 chamadas, menos de 1 min).
	 *
	 * @param int[] $post_ids  todas as unidades vinculadas, em ordem estavel
	 * @return array{batch:int[],cursor:int}
	 */
	public static function batch( array $post_ids, int $cursor, int $interval_minutes ): array {
		$total = count( $post_ids );

		if ( 0 === $total ) {
			return array( 'batch' => array(), 'cursor' => 0 );
		}

		$runs   = max( 1, intdiv( max( Contorno_Evo_Settings::MIN_INTERVAL, $interval_minutes ), self::BATCH_MINUTES ) );
		$size   = (int) ceil( $total / $runs );
		$cursor = $cursor >= $total || $cursor < 0 ? 0 : $cursor;
		$next   = $cursor + $size;

		return array( 'batch' => array_slice( $post_ids, $cursor, $size ), 'cursor' => $next >= $total ? 0 : $next );
	}

	public static function schedule(): void {
		self::unschedule();

		if ( ! Contorno_Evo_Settings::get( 'auto_sync', false ) ) {
			return;
		}

		wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, CONTORNO_EVO_CRON_HOOK );
	}

	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( CONTORNO_EVO_CRON_HOOK );

		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, CONTORNO_EVO_CRON_HOOK );
			$timestamp = wp_next_scheduled( CONTORNO_EVO_CRON_HOOK );
		}
	}

	public static function reschedule(): void {
		self::schedule();
	}

	public static function run(): void {
		if ( ! Contorno_Evo_Settings::get( 'auto_sync', false ) || ! Contorno_Evo_Settings::has_credentials() ) {
			return;
		}

		$ids = array();
		foreach ( Contorno_Evo_Mapping::posts() as $post ) {
			if ( Contorno_Evo_Mapping::branch( $post->ID ) > 0 ) {
				$ids[] = (int) $post->ID;
			}
		}

		$plan = self::batch( $ids, (int) get_option( self::OPT_CURSOR, 0 ), (int) Contorno_Evo_Settings::get( 'interval_minutes', 60 ) );
		update_option( self::OPT_CURSOR, $plan['cursor'], false );

		if ( array() === $plan['batch'] ) {
			return;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- hospedagem pode bloquear.
		}

		// Por filial: o modo global paginaria o catalogo da rede inteira a cada lote.
		Contorno_Evo_Sync::run( array( 'trigger' => 'cron', 'post_ids' => $plan['batch'], 'fetch_mode' => 'per-branch' ) );
	}

	public static function next_run(): int {
		return (int) wp_next_scheduled( CONTORNO_EVO_CRON_HOOK );
	}
}
