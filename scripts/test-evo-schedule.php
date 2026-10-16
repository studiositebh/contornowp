<?php
/**
 * Testes da grade nativa de aulas — rodados por scripts/test-evo-schedule.sh.
 *
 * Carrega o codigo REAL de class-schedule.php e class-activities.php com
 * stubs do WordPress e um Contorno_Evo_Client falso. Nao toca rede, banco
 * nem staging.
 *
 * O que estes testes impedem:
 *   - horario inventado ou aula sem nome/hora/data entrar na grade;
 *   - aula de outra semana aparecer como desta;
 *   - foto do professor (URL assinada que expira) ser guardada;
 *   - vagas erradas (capacity - ocupation, nunca negativo);
 *   - a pagina chamar a API (so o cron chama);
 *   - passar do orcamento de chamadas por minuto (EVO: 40/min por IP);
 *   - grade truncada das CTNs (>500 aulas) ser exibida como completa;
 *   - erro da EVO apagar a ultima grade boa ou virar grade vazia.
 *
 * Opcional: CONTORNO_SCHEDULE_SAMPLE=<arquivo> roda o parser contra a amostra
 * crua gerada por scripts/validate-evo-units.php.
 *
 * @package ContornoEvoSync
 */

declare( strict_types = 1 );

$plugin = rtrim( (string) ( $argv[1] ?? '' ), '/\\' );

if ( '' === $plugin || ! is_file( $plugin . '/includes/class-schedule.php' ) ) {
	fwrite( STDERR, "Uso: php test-evo-schedule.php <contorno-evo-sync>\n" );
	exit( 1 );
}

/* =============================================================
 * Stubs do WordPress
 * =========================================================== */

define( 'ABSPATH', true );

$GLOBALS['now']    = 0;
$GLOBALS['tr']     = array();
$GLOBALS['opt']    = array();
$GLOBALS['events'] = array();
$GLOBALS['calls']  = array();
$GLOBALS['log']    = array();

function __( string $t, string $d = '' ): string { return $t; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_strip_all_tags( string $s ): string { return strip_tags( $s ); }
function wp_timezone(): DateTimeZone { return new DateTimeZone( 'America/Sao_Paulo' ); }
function add_action( ...$a ): void {}
function get_transient( string $k ) {
	$t = $GLOBALS['tr'][ $k ] ?? null;
	if ( null === $t || ( $t[1] > 0 && $t[1] <= time() ) ) {
		return false;
	}
	return $t[0];
}
function set_transient( string $k, $v, int $ttl = 0 ): bool { $GLOBALS['tr'][ $k ] = array( $v, $ttl > 0 ? time() + $ttl : 0 ); return true; }
function delete_transient( string $k ): bool { unset( $GLOBALS['tr'][ $k ] ); return true; }
function get_option( string $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( string $k, $v, $autoload = null ): bool { $GLOBALS['opt'][ $k ] = $v; return true; }
function wp_next_scheduled( string $hook ) { return $GLOBALS['events'][ $hook ] ?? false; }
function wp_schedule_single_event( int $ts, string $hook ): bool { $GLOBALS['events'][ $hook ] = $ts; return true; }

final class Contorno_Evo_Log {
	public static function add( string $level, string $message, array $context = array() ): void { $GLOBALS['log'][] = $message; }
}

/**
 * Cliente falso. $GLOBALS['fixture'] = callable( int $branch, array $query ): array{ok,message,items}.
 */
final class Contorno_Evo_Client {
	public function activities_schedule( int $id_branch, array $extra = array() ): array {
		$GLOBALS['calls'][] = array( 'branch' => $id_branch ) + $extra;
		$res = ( $GLOBALS['fixture'] )( $id_branch, $extra );
		return $res + array( 'ok' => true, 'message' => '', 'http' => 200, 'items' => array() );
	}
}

require $plugin . '/includes/class-activities.php';
require $plugin . '/includes/class-schedule.php';

/* =============================================================
 * Mini-runner
 * =========================================================== */

$pass = 0;
$fail = 0;

function check( bool $cond, string $label ): void {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		printf( "  [OK]       %s\n", $label );
	} else {
		++$fail;
		fprintf( STDERR, "  [FALHOU]   %s\n", $label );
	}
}

function reset_state(): void {
	$GLOBALS['tr']     = array();
	$GLOBALS['opt']    = array();
	$GLOBALS['events'] = array();
	$GLOBALS['calls']  = array();
	$GLOBALS['log']    = array();
}

/** Aula crua no formato confirmado da EVO (16/10/2026). */
function raw( array $over = array() ): array {
	static $seq = 1000;
	return array_merge(
		array(
			'idConfiguration'        => 1,
			'idActivity'             => 221,
			'idAtividadeSessao'      => ++$seq,
			'name'                   => 'Contorno Cycling',
			'description'            => 'Texto longo de catalogo',
			'area'                   => 'SALA DE SPINNING',
			'capacity'               => 21,
			'ocupation'              => 5,
			'startTime'              => '07:00',
			'endTime'                => '07:45',
			'instructor'             => 'MARIA DA SILVA SOUZA',
			'instructorPhoto'        => 'https://w12evostorage.w12app.com.br/fotos/x.jpeg?exp=1&sig=abc',
			'activityDate'           => '2026-10-12T00:00:00',
			'status'                 => 0,
			'statusName'             => 'Free',
			'allowExperimentalClass' => false,
			'experimentalClassSlots' => null,
		),
		$over
	);
}

$monday = '2026-10-12';

/* =============================================================
 * Parser
 * =========================================================== */

echo "Parser\n";

$week = Contorno_Evo_Schedule::parse(
	array(
		raw( array( 'startTime' => '19:30', 'endTime' => '20:15', 'name' => 'Treinamento Funcional' ) ),
		raw(),
		raw( array( 'startTime' => '14:30', 'endTime' => '15:15', 'activityDate' => '2026-10-14T00:00:00' ) ),
		raw( array( 'activityDate' => '2026-10-18T00:00:00', 'startTime' => '9:05' ) ),
	),
	$monday
);

check( 4 === $week['total'] && 0 === $week['skipped'], 'quatro aulas validas, nenhuma descartada' );
check( array( '07:00', '19:30' ) === array_column( $week['days'][1], 'start' ), 'segunda ordenada por horario' );
check( 1 === count( $week['days'][3] ) && 1 === count( $week['days'][7] ), 'quarta e domingo no dia certo (activityDate)' );
check( '09:05' === $week['days'][7][0]['start'], 'hora sem zero a esquerda normalizada (9:05 -> 09:05)' );
check( 'morning' === $week['days'][1][0]['period'] && 'afternoon' === $week['days'][3][0]['period'] && 'night' === $week['days'][1][1]['period'], 'periodo Manha/Tarde/Noite pela hora de inicio' );
check( 16 === $week['days'][1][0]['spots_left'], 'vagas = capacity - ocupation' );
check( 'Maria da Silva Souza' === $week['days'][1][0]['instructor'], 'nome do professor em caixa normal' );
check( false === strpos( (string) json_encode( $week ), 'w12evostorage' ) && ! isset( $week['days'][1][0]['instructorPhoto'] ), 'foto do professor (URL assinada) nunca e guardada' );
check( ! isset( $week['days'][1][0]['description'] ), 'descricao de catalogo fica de fora' );

$bad = Contorno_Evo_Schedule::parse(
	array(
		raw( array( 'name' => '' ) ),
		raw( array( 'name' => null ) ),
		raw( array( 'startTime' => '25:00' ) ),
		raw( array( 'startTime' => '' ) ),
		raw( array( 'startTime' => 'manha' ) ),
		raw( array( 'activityDate' => null ) ),
		raw( array( 'activityDate' => 'ontem' ) ),
		raw( array( 'activityDate' => '2026-10-11T00:00:00' ) ), // domingo anterior
		raw( array( 'activityDate' => '2026-10-19T00:00:00' ) ), // segunda seguinte
		'texto solto',
		null,
		array( 'list' => array( raw() ) ),
	),
	$monday
);
check( 0 === $bad['total'] && 12 === $bad['skipped'], 'sem nome, hora invalida, data fora da semana ou lixo: tudo descartado' );

$dup = Contorno_Evo_Schedule::parse( array( raw( array( 'idAtividadeSessao' => 7 ) ), raw( array( 'idAtividadeSessao' => 7 ) ) ), $monday );
check( 1 === $dup['total'], 'mesma sessao (idAtividadeSessao) repetida conta uma vez' );

$dup2 = Contorno_Evo_Schedule::parse( array( raw( array( 'idAtividadeSessao' => null ) ), raw( array( 'idAtividadeSessao' => null ) ) ), $monday );
check( 1 === $dup2['total'], 'sem id: data + hora + nome + sala identificam a aula' );

$st = Contorno_Evo_Schedule::parse(
	array(
		raw( array( 'statusName' => 'Finalized', 'startTime' => '06:00' ) ),
		raw( array( 'statusName' => 'Canceled', 'startTime' => '07:00' ) ),
		raw( array( 'capacity' => 20, 'ocupation' => 23, 'startTime' => '08:00' ) ),
		raw( array( 'capacity' => 0, 'ocupation' => 0, 'startTime' => '09:00' ) ),
		raw( array( 'capacity' => null, 'startTime' => '10:00' ) ),
	),
	$monday
)['days'][1];
check( 'finished' === $st[0]['state'], 'Finalized -> encerrada' );
check( 'canceled' === $st[1]['state'], 'Canceled -> cancelada' );
check( 'full' === $st[2]['state'] && 0 === $st[2]['spots_left'], 'ocupacao acima da capacidade: lotada, vagas 0 (nunca negativo)' );
check( null === $st[3]['spots_left'] && 'open' === $st[3]['state'], 'capacidade 0: nao inventa numero de vagas' );
check( null === $st[4]['spots_left'], 'capacidade ausente: nao inventa numero de vagas' );

$ex = Contorno_Evo_Schedule::parse(
	array(
		raw( array( 'allowExperimentalClass' => true, 'experimentalClassSlots' => null, 'startTime' => '06:00' ) ),
		raw( array( 'allowExperimentalClass' => true, 'experimentalClassSlots' => 0, 'startTime' => '07:00' ) ),
		raw( array( 'allowExperimentalClass' => 'true', 'startTime' => '08:00' ) ),
		raw( array( 'endTime' => '06:00', 'startTime' => '09:00' ) ),
	),
	$monday
)['days'][1];
check( true === $ex[0]['experimental'], 'aceita experimental sem limite de vagas' );
check( false === $ex[1]['experimental'], 'experimental com 0 vagas experimentais: nao anuncia' );
check( false === $ex[2]['experimental'], 'allowExperimentalClass so vale como booleano true' );
check( '' === $ex[3]['end'], 'fim antes do inicio: fim omitido, nao inventado' );

$sample_file = (string) getenv( 'CONTORNO_SCHEDULE_SAMPLE' );
if ( '' !== $sample_file && is_file( $sample_file ) ) {
	$sample = json_decode( (string) file_get_contents( $sample_file ), true );
	foreach ( (array) $sample as $branch => $items ) {
		$first = (string) ( $items[0]['activityDate'] ?? '' );
		$mon   = Contorno_Evo_Schedule::monday( (int) strtotime( substr( $first, 0, 10 ) . ' 12:00 America/Sao_Paulo' ) );
		$w     = Contorno_Evo_Schedule::parse( (array) $items, $mon );
		check( $w['total'] === count( (array) $items ) && 0 === $w['skipped'], "amostra real filial $branch: " . count( (array) $items ) . ' aulas lidas sem descarte' );
	}
}

/* =============================================================
 * Leitura da pagina: nunca chama a API
 * =========================================================== */

echo "Pagina (week)\n";

reset_state();
$GLOBALS['fixture'] = static fn (): array => array( 'items' => array( raw() ) );
$now = (int) strtotime( '2026-10-14 10:00 America/Sao_Paulo' );

check( '2026-10-12' === Contorno_Evo_Schedule::monday( $now ), 'segunda-feira da semana no fuso do site' );
check( '2026-10-12' === Contorno_Evo_Schedule::monday( (int) strtotime( '2026-10-18 23:30 America/Sao_Paulo' ) ), 'domingo 23h30 (02h30 UTC de segunda) ainda e a mesma semana' );

check( null === Contorno_Evo_Schedule::week( 8, $now ), 'sem cache: devolve null (pagina mostra o iframe)' );
check( array() === $GLOBALS['calls'], 'sem cache: a pagina NAO chama a EVO' );
check( isset( $GLOBALS['opt']['contorno_evo_schedule_queue'][8] ) && false !== wp_next_scheduled( Contorno_Evo_Schedule::HOOK ), 'sem cache: filial vai para a fila do cron' );

reset_state();
set_transient( 'contorno_evo_week_8', array( 'monday' => '2026-10-12', 'fetched_at' => $now - 60, 'days' => array(), 'total' => 1 ), 3600 );
check( is_array( Contorno_Evo_Schedule::week( 8, $now ) ) && array() === $GLOBALS['opt'], 'cache de 1 min: exibe e nao enfileira' );

reset_state();
set_transient( 'contorno_evo_week_8', array( 'monday' => '2026-10-12', 'fetched_at' => $now - 1200, 'days' => array(), 'total' => 1 ), 3600 );
check( is_array( Contorno_Evo_Schedule::week( 8, $now ) ) && isset( $GLOBALS['opt']['contorno_evo_schedule_queue'][8] ), 'cache de 20 min: exibe e pede atualizacao' );

reset_state();
set_transient( 'contorno_evo_week_8', array( 'monday' => '2026-10-12', 'fetched_at' => $now - 4000, 'days' => array(), 'total' => 1 ), 0 );
check( null === Contorno_Evo_Schedule::week( 8, $now ), 'cache de mais de 1 h: nao exibe (vagas velhas)' );

reset_state();
set_transient( 'contorno_evo_week_8', array( 'monday' => '2026-10-05', 'fetched_at' => $now - 60, 'days' => array(), 'total' => 1 ), 3600 );
check( null === Contorno_Evo_Schedule::week( 8, $now ), 'grade da semana passada nao aparece nesta semana' );

reset_state();
set_transient( 'contorno_evo_week_fail_8', 'error', 300 );
Contorno_Evo_Schedule::week( 8, $now );
check( ! isset( $GLOBALS['opt']['contorno_evo_schedule_queue'][8] ), 'falha recente: visita nao re-enfileira (sem martelar a EVO)' );

/* =============================================================
 * Refresh (cron)
 * =========================================================== */

echo "Atualizacao (cron)\n";

reset_state();
$GLOBALS['fixture'] = static fn (): array => array( 'items' => array( raw(), raw( array( 'startTime' => '18:00' ) ) ) );
$now                = time();
$mon                = Contorno_Evo_Schedule::monday( $now );
$GLOBALS['fixture'] = static fn (): array => array( 'items' => array( raw( array( 'activityDate' => $mon . 'T00:00:00' ) ) ) );

check( 'ok' === Contorno_Evo_Schedule::refresh( 8 ), 'filial comum: grade gravada' );
check( 1 === count( $GLOBALS['calls'] ) && 'true' === $GLOBALS['calls'][0]['showFullWeek'] && $mon === $GLOBALS['calls'][0]['date'], 'filial comum: 1 chamada com a semana cheia a partir da segunda' );
check( null !== Contorno_Evo_Schedule::week( 8 ), 'apos o cron, a pagina exibe a grade nativa' );

// Erro nao apaga a ultima grade boa.
$GLOBALS['fixture'] = static fn (): array => array( 'ok' => false, 'message' => 'HTTP 429' );
check( 'error' === Contorno_Evo_Schedule::refresh( 8 ), 'erro da EVO: refresh falha' );
check( null !== Contorno_Evo_Schedule::week( 8 ), 'erro da EVO nao apaga a ultima grade boa' );
check( false !== get_transient( 'contorno_evo_week_fail_8' ) && array() !== $GLOBALS['log'], 'erro da EVO: registra falha e loga' );

reset_state();
$GLOBALS['fixture'] = static fn (): array => array( 'items' => array() );
check( 'empty' === Contorno_Evo_Schedule::refresh( 9 ) && null === Contorno_Evo_Schedule::week( 9 ), 'semana vazia: nao vira grade vazia, fica o iframe' );

reset_state();
$GLOBALS['fixture'] = static fn (): array => array( 'items' => array( raw( array( 'name' => '' ) ) ) );
check( 'empty' === Contorno_Evo_Schedule::refresh( 9 ), 'so itens invalidos: nao vira grade' );

// CTN: semana cheia volta truncada em 500 -> passa a consultar dia a dia.
reset_state();
$GLOBALS['fixture'] = static function ( int $b, array $q ) use ( $mon ): array {
	if ( isset( $q['showFullWeek'] ) ) {
		return array( 'items' => array_fill( 0, 500, raw( array( 'activityDate' => $mon . 'T00:00:00' ) ) ) );
	}
	$items = array();
	for ( $i = 0; $i < 80; $i++ ) {
		$items[] = raw( array( 'activityDate' => $q['date'] . 'T00:00:00', 'startTime' => sprintf( '%02d:%02d', 6 + intdiv( $i, 6 ), ( $i % 6 ) * 10 ) ) );
	}
	return array( 'items' => $items );
};
check( 'retry_daily' === Contorno_Evo_Schedule::refresh( 50 ), 'CTN: 500 aulas na semana = truncada, passa para dia a dia' );
check( null === Contorno_Evo_Schedule::week( 50 ), 'CTN: grade truncada nao e exibida' );
$GLOBALS['calls'] = array();
check( 'ok' === Contorno_Evo_Schedule::refresh( 50 ), 'CTN: dia a dia grava a grade' );
check( 7 === count( $GLOBALS['calls'] ) && ! isset( $GLOBALS['calls'][0]['showFullWeek'] ), 'CTN: 7 chamadas, uma por dia' );
check( 560 === get_transient( 'contorno_evo_week_50' )['total'], 'CTN: as 560 aulas da semana (7 x 80) entram' );

$GLOBALS['fixture'] = static fn ( int $b, array $q ): array => array( 'items' => array_fill( 0, 500, raw( array( 'activityDate' => $q['date'] . 'T00:00:00' ) ) ) );
$GLOBALS['opt']['contorno_evo_schedule_daily'][57] = true;
check( 'truncated' === Contorno_Evo_Schedule::refresh( 57 ), 'dia com 500 aulas tambem truncado: falha fechada' );

/* =============================================================
 * Orcamento de chamadas (EVO: 40/min por IP)
 * =========================================================== */

echo "Orcamento\n";

reset_state();
$t = 1_800_000_000 - ( 1_800_000_000 % 60 );
$n = 0;
while ( Contorno_Evo_Schedule::take_budget( 1, $t ) ) {
	++$n;
}
check( Contorno_Evo_Schedule::BUDGET_PER_MIN === $n && $n < 40, "orcamento de $n chamadas/min, abaixo do limite de 40 da EVO" );
check( false === Contorno_Evo_Schedule::take_budget( 7, $t + 59 ), 'mesmo minuto: nao reserva mais' );
check( true === Contorno_Evo_Schedule::take_budget( 7, $t + 60 ), 'minuto seguinte: orcamento renovado' );

// Fila com 66 filiais (todas as unidades com cache frio) + 2 CTNs dia a dia.
reset_state();
$GLOBALS['fixture'] = static fn ( int $b, array $q ): array => array( 'items' => array( raw( array( 'activityDate' => ( $q['date'] ?? $mon ) . 'T00:00:00' ) ) ) );
$GLOBALS['opt']['contorno_evo_schedule_daily'] = array( 50 => true, 57 => true );
for ( $b = 1; $b <= 66; $b++ ) {
	Contorno_Evo_Schedule::enqueue( $b );
}
Contorno_Evo_Schedule::run();
$first = count( $GLOBALS['calls'] );
check( $first <= Contorno_Evo_Schedule::BUDGET_PER_MIN, "uma rodada do cron: $first chamadas (<= " . Contorno_Evo_Schedule::BUDGET_PER_MIN . ')' );
check( count( $GLOBALS['opt']['contorno_evo_schedule_queue'] ) > 0 && false !== wp_next_scheduled( Contorno_Evo_Schedule::HOOK ), 'sobra da fila continua no proximo minuto' );

// Rodadas seguintes, simulando o minuto virando (zera o contador).
$rounds = 1;
while ( array() !== $GLOBALS['opt']['contorno_evo_schedule_queue'] && $rounds < 20 ) {
	unset( $GLOBALS['tr']['contorno_evo_sch_budget'] );
	$before = count( $GLOBALS['calls'] );
	Contorno_Evo_Schedule::run();
	check( count( $GLOBALS['calls'] ) - $before <= Contorno_Evo_Schedule::BUDGET_PER_MIN, 'rodada ' . ( $rounds + 1 ) . ': ' . ( count( $GLOBALS['calls'] ) - $before ) . ' chamadas' );
	++$rounds;
}
check( array() === $GLOBALS['opt']['contorno_evo_schedule_queue'], "fila de 66 filiais esvaziada em $rounds minutos" );
check( 64 + 14 === count( $GLOBALS['calls'] ), '64 filiais x 1 + 2 CTNs x 7 = 78 chamadas no total, nenhuma repetida' );

printf( "\n%d ok, %d falhas\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
