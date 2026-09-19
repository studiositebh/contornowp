<?php
/**
 * Reconciliacao periodica via WP-Cron.
 *
 * A EVO nao tem webhook para alteracao de plano (eventos oficiais: NewSale,
 * CreateMember, AlterMember, NewInvoice), entao a reconciliacao periodica e o
 * mecanismo principal. Intervalo configuravel (30 min a 24 h).
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

	public static function boot(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( CONTORNO_EVO_CRON_HOOK, array( __CLASS__, 'run' ) );
		add_action( 'update_option_' . Contorno_Evo_Settings::OPTION, array( __CLASS__, 'reschedule' ) );
	}

	/**
	 * @param array<string,array{interval:int,display:string}> $schedules
	 * @return array<string,array{interval:int,display:string}>
	 */
	public static function schedules( array $schedules ): array {
		$minutes = (int) Contorno_Evo_Settings::get( 'interval_minutes', 360 );

		$schedules[ self::SCHEDULE ] = array(
			'interval' => max( Contorno_Evo_Settings::MIN_INTERVAL, $minutes ) * MINUTE_IN_SECONDS,
			'display'  => sprintf( /* translators: %d: minutes */ __( 'EVO: a cada %d min', 'contorno-evo' ), $minutes ),
		);

		return $schedules;
	}

	public static function schedule(): void {
		self::unschedule();

		if ( ! Contorno_Evo_Settings::get( 'auto_sync', false ) ) {
			return;
		}

		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, self::SCHEDULE, CONTORNO_EVO_CRON_HOOK );
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

		Contorno_Evo_Sync::run( array( 'trigger' => 'cron' ) );
	}

	public static function next_run(): int {
		return (int) wp_next_scheduled( CONTORNO_EVO_CRON_HOOK );
	}
}
