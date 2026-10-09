<?php
/**
 * Diagnostico do HTTP 500 da EVO — SOMENTE GET, sem PII, sem token em claro.
 *
 *   EVO_DNS=... EVO_TOKEN=... php scripts/diagnose-evo-auth.php
 *
 * 1) Inspeciona o formato de EVO_DNS / EVO_TOKEN (tamanho, espacos, quebras
 *    de linha, aspas, ':' embutido, JSON, nao-ASCII) sem imprimir o valor.
 * 2) Chama GET /api/v1/configuration DIRETO na EVO (sem WordPress, sem
 *    proxy) em variantes controladas e grava status, headers (Authorization
 *    mascarado) e corpo de erro. ~8 chamadas, 1,6 s entre elas.
 */

declare( strict_types = 1 );

$dns   = (string) getenv( 'EVO_DNS' );
$token = (string) getenv( 'EVO_TOKEN' );

/*
 * Sem variaveis de ambiente: pergunta no proprio terminal. No Windows o
 * token e lido por Read-Host -AsSecureString (nao ecoa na tela nem vai para
 * o historico do PowerShell). O valor fica so na memoria deste processo.
 */
if ( ( '' === $dns || '' === $token ) && 'Windows' === PHP_OS_FAMILY ) {
	$ask = static function ( string $label, bool $secret ): string {
		echo $label . ': ';
		$ps = $secret
			? '$s = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))'
			: '[Console]::ReadLine()';
		$v = (string) shell_exec( 'powershell -NoProfile -Command "' . $ps . '"' );
		if ( $secret ) {
			echo "\n";
		}
		return trim( (string) preg_replace( '/\R/', '', $v ) );
	};
	if ( '' === $dns ) {
		$dns = $ask( 'DNS EVO', false );
	}
	if ( '' === $token ) {
		$token = $ask( 'Token EVO (oculto)', true );
	}
}
$base  = rtrim( (string) ( getenv( 'EVO_BASE' ) ?: 'https://evo-integracao-api.w12app.com.br' ), '/' );
$out   = (string) ( getenv( 'EVO_DIAG_OUT' ) ?: sys_get_temp_dir() . '/evo-diag.json' );
$ca    = dirname( __DIR__ ) . '/wp-includes/certificates/ca-bundle.crt';

function mask( string $s ): string {
	$l = strlen( $s );
	return $l <= 8 ? str_repeat( '*', $l ) : substr( $s, 0, 4 ) . '...' . substr( $s, -4 );
}

/** Formato do valor, sem revelar o valor. */
function shape( string $s ): array {
	$t = trim( $s );
	return array(
		'set'                => '' !== $s,
		'length'             => strlen( $s ),
		'length_trimmed'     => strlen( $t ),
		'mask'               => mask( $s ),
		'leading_ws'         => (bool) preg_match( '/^\s/', $s ),
		'trailing_ws'        => (bool) preg_match( '/\s$/', $s ),
		'has_cr_lf'          => (bool) preg_match( '/[\r\n]/', $s ),
		'has_inner_space'    => (bool) preg_match( '/\S\s+\S/', $t ),
		'quoted'             => (bool) preg_match( '/^["\'].*["\']$/s', $t ),
		'has_colon'          => str_contains( $s, ':' ),
		'looks_json'         => in_array( substr( $t, 0, 1 ), array( '{', '[' ), true ),
		'json_valid'         => in_array( substr( $t, 0, 1 ), array( '{', '[' ), true ) ? null !== json_decode( $t ) : null,
		'non_ascii'          => (bool) preg_match( '/[^\x20-\x7E]/', $s ),
		'starts_basic'       => (bool) preg_match( '/^(basic|bearer)\s/i', $t ),
		'dot_segments'       => substr_count( $t, '.' ) + 1,
		'charset'            => implode( '', array_filter( array(
			preg_match( '/[a-z]/', $s ) ? 'a-z ' : '',
			preg_match( '/[A-Z]/', $s ) ? 'A-Z ' : '',
			preg_match( '/[0-9]/', $s ) ? '0-9 ' : '',
			preg_match( '/-/', $s ) ? '- ' : '',
			preg_match( '/_/', $s ) ? '_ ' : '',
			preg_match( '/[^A-Za-z0-9_\-]/', $s ) ? 'OUTROS(' . implode( '', array_unique( str_split( (string) preg_replace( '/[A-Za-z0-9_\-]/', '', $s ) ) ) ) . ') ' : '',
		) ) ),
		'guid'               => (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $t ),
		'prefix'             => preg_match( '/^([a-z]{2,6}[-_])/i', $t, $m ) ? $m[1] : '',
	);
}

$report = array(
	'generated_at' => gmdate( 'c' ),
	'base'         => $base,
	'php'          => PHP_VERSION,
	'curl'         => curl_version()['version'] ?? '',
	'env'          => array( 'EVO_DNS' => shape( $dns ), 'EVO_TOKEN' => shape( $token ) ),
	'calls'        => array(),
);

echo "== Fase 4: formato das variaveis ==\n";
foreach ( $report['env'] as $k => $v ) {
	echo "$k: " . json_encode( $v, JSON_UNESCAPED_SLASHES ) . "\n";
}

if ( '' === $dns || '' === $token ) {
	echo "\nEVO_DNS/EVO_TOKEN ausentes — abortando antes de chamar a EVO.\n";
	file_put_contents( $out, json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	exit( 1 );
}

$last = 0.0;

/**
 * @return array{http:int,data:mixed}
 */
function call( string $label, string $path, array $headers, string $auth_desc, ?array $safe = null, string $branch = '' ): array {
	global $base, $ca, $report, $last;

	$wait = 1.6 - ( microtime( true ) - $last );
	if ( $last > 0 && $wait > 0 ) {
		usleep( (int) ( $wait * 1e6 ) );
	}
	$last = microtime( true );

	$resp_headers = array();
	$ch           = curl_init( $base . $path );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_USERAGENT      => 'ContornoEvoDiag/1.0 (read-only)',
			CURLOPT_CAINFO         => $ca,
			CURLOPT_HEADERFUNCTION => static function ( $c, string $line ) use ( &$resp_headers ): int {
				if ( str_contains( $line, ':' ) ) {
					[ $k, $v ] = explode( ':', $line, 2 );
					$k         = strtolower( trim( $k ) );
					// Nada de cookie/token de volta no relatorio.
					$resp_headers[ $k ] = in_array( $k, array( 'set-cookie', 'authorization' ), true ) ? '[omitido]' : trim( $v );
				}
				return strlen( $line );
			},
		)
	);

	$t0   = microtime( true );
	$body = (string) curl_exec( $ch );
	$info = curl_getinfo( $ch );
	$err  = curl_error( $ch );
	curl_close( $ch );

	$sent = array_map(
		static fn ( string $h ): string => preg_match( '/^authorization:/i', $h ) ? 'Authorization: ' . $auth_desc : $h,
		$headers
	);

	$http = (int) $info['http_code'];
	$ok   = $http >= 200 && $http < 300;
	$data = $ok ? json_decode( $body, true ) : null;

	$rec = array(
		'label'            => $label,
		'branch'           => $branch,
		'request'          => array( 'method' => 'GET', 'url' => $base . $path, 'headers' => $sent ),
		'http'             => $http,
		'ms'               => (int) ( ( microtime( true ) - $t0 ) * 1000 ),
		'remote_ip'        => $info['primary_ip'] ?? '',
		'curl_error'       => $err,
		'content_type'     => $info['content_type'] ?? '',
		'response_headers' => $resp_headers,
		'body_length'      => strlen( $body ),
		// 2xx: so resumo (quantidade, campos, valores de campos da lista
		// $safe). Erro: trecho do corpo, com digitos longos mascarados.
		'body_excerpt'     => $ok ? null : preg_replace( '/\d{6,}/', '[num]', mb_substr( $body, 0, 1500 ) ),
		'summary'          => $ok ? summarize( $data, $safe ?? array() ) : null,
	);

	$report['calls'][] = $rec;
	printf(
		"%-44s HTTP %3d  %4d ms  len=%-6d %s\n",
		$label,
		$http,
		$rec['ms'],
		$rec['body_length'],
		$ok ? 'itens=' . ( $rec['summary']['count'] ?? '?' ) : mb_substr( str_replace( "\n", ' ', (string) $rec['body_excerpt'] ), 0, 140 ) . ( isset( $resp_headers['cf-ray'] ) ? '  cf-ray=' . $resp_headers['cf-ray'] : '' )
	);

	return array( 'http' => $http, 'data' => $data );
}

/**
 * Resumo sem PII: quantidade, nomes de campos e so os campos de $safe.
 *
 * @return array<string,mixed>
 */
function summarize( mixed $data, array $safe, int $sample = 50 ): array {
	if ( ! is_array( $data ) ) {
		return array( 'type' => gettype( $data ), 'count' => null === $data ? 0 : 1, 'scalar' => is_string( $data ) ? '(string ' . strlen( $data ) . ' chars)' : $data );
	}

	// Envelope {qtde, lista|list, ...} (visto em /api/v3/membership em
	// 09/10/2026, embora o swagger declare array): abre e registra o envelope.
	$wrapper = null;
	if ( ! array_is_list( $data ) ) {
		foreach ( array( 'lista', 'list', 'items', 'data' ) as $k ) {
			if ( isset( $data[ $k ] ) && is_array( $data[ $k ] ) && array_is_list( $data[ $k ] ) && array() !== $data[ $k ] ) {
				$wrapper = array(
					'unwrapped_from' => $k,
					'keys'           => array_keys( $data ),
					'scalars'        => array_filter( $data, 'is_scalar' ),
					'list_sizes'     => array_map( static fn ( $v ) => is_array( $v ) ? count( $v ) : null, array_filter( $data, 'is_array' ) ),
				);
				$data = $data[ $k ];
				break;
			}
		}
		if ( null === $wrapper && ( isset( $data['lista'] ) || isset( $data['qtde'] ) ) ) {
			$wrapper = array( 'unwrapped_from' => null, 'keys' => array_keys( $data ), 'scalars' => array_filter( $data, 'is_scalar' ), 'list_sizes' => array_map( static fn ( $v ) => is_array( $v ) ? count( $v ) : null, array_filter( $data, 'is_array' ) ) );
		}
	}

	$rows = array_is_list( $data ) ? $data : array( $data );
	$out  = array(
		'count'  => count( $rows ),
		'fields' => is_array( $rows[0] ?? null ) ? array_keys( $rows[0] ) : array(),
	);
	if ( null !== $wrapper ) {
		$out['wrapper'] = $wrapper;
	}

	if ( array() !== $safe ) {
		foreach ( array_slice( $rows, 0, $sample ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$pick = array();
			foreach ( $safe as $k ) {
				if ( ! array_key_exists( $k, $row ) ) {
					continue;
				}
				$v          = $row[ $k ];
				$pick[ $k ] = is_array( $v )
					// Sub-objeto (ex.: gatewayData): so os NOMES das chaves.
					? ( array_is_list( $v ) ? '(' . count( $v ) . ' itens)' : array( 'keys' => array_keys( $v ) ) )
					: ( is_string( $v ) ? mb_substr( $v, 0, 80 ) : $v );
			}
			$out['rows'][] = $pick;
		}
	}

	return $out;
}

$std = array( 'Accept: application/json', 'culture: pt-BR' );
$b64 = static fn ( string $u, string $p ): string => 'Basic ' . base64_encode( $u . ':' . $p );

$dns_c   = trim( trim( $dns ), "\"'" );
$token_c = trim( trim( $token ), "\"'" );
$mode    = (string) ( getenv( 'EVO_DIAG_MODE' ) ?: 'h6' );
$report['mode'] = $mode;

if ( 'baseline' === $mode ) :

echo "\n== Fases 2/3/5: chamada DIRETA a EVO (sem WordPress/proxy) ==\n";

// Controles.
call( 'C1 sem Authorization', '/api/v1/configuration', $std, '(ausente)' );
call( 'C2 Basic dns:token_errado', '/api/v1/configuration', array_merge( $std, array( 'Authorization: ' . $b64( $dns, 'x' . bin2hex( random_bytes( 16 ) ) ) ) ), 'Basic base64(DNS:<token aleatorio>)' );
call( 'C3 Basic dns_errado:token', '/api/v1/configuration', array_merge( $std, array( 'Authorization: ' . $b64( 'dns-inexistente-' . bin2hex( random_bytes( 3 ) ), $token ) ) ), 'Basic base64(<dns falso>:TOKEN)' );

// Credencial real, exatamente como o plugin/auditoria enviam.
call( 'R1 Basic dns:token (igual ao plugin)', '/api/v1/configuration', array_merge( $std, array( 'Authorization: ' . $b64( $dns, $token ) ) ), 'Basic base64(DNS:TOKEN)' );

// Mesma credencial sem os headers extras (isola "culture").
call( 'R2 Basic dns:token, so Accept', '/api/v1/configuration', array( 'Accept: application/json', 'Authorization: ' . $b64( $dns, $token ) ), 'Basic base64(DNS:TOKEN)' );

// Se houver espaco/aspas/quebra, testa a versao limpa.
if ( $dns_c !== $dns || $token_c !== $token ) {
	call( 'R3 Basic dns:token LIMPOS (trim/aspas)', '/api/v1/configuration', array_merge( $std, array( 'Authorization: ' . $b64( $dns_c, $token_c ) ) ), 'Basic base64(trim(DNS):trim(TOKEN))' );
}

// DNS em minusculas (DNS de academia e case-insensitive na pratica?).
if ( strtolower( $dns_c ) !== $dns_c ) {
	call( 'R4 Basic dns_lower:token', '/api/v1/configuration', array_merge( $std, array( 'Authorization: ' . $b64( strtolower( $dns_c ), $token_c ) ) ), 'Basic base64(lower(DNS):TOKEN)' );
}

// Segundo endpoint seguro.
call( 'R5 group-branches (igual ao plugin)', '/api/v1/configuration/group-branches', array_merge( $std, array( 'Authorization: ' . $b64( $dns_c, $token_c ) ) ), 'Basic base64(DNS:TOKEN)' );

endif; // baseline

if ( 'h6' === $mode ) :

/*
 * H6 — chave ADM Geral so atende chamadas COM idBranch (api.abcevo.com:
 * "chave criada em uma instancia de ADM Geral com acesso somente a chamadas
 * que possuem o parametro idbranch").
 *
 * IDs: campo evo_branch_id do dataset do site (1 = Sao Lucas/BH,
 * 8 = Betim), conferidos contra o ID embutido nas URLs de checkout da
 * propria EVO (contornodocorpo/<idBranch>/site/...). EVO_DIAG_BRANCHES=1,8.
 *
 * Regra de parada (fase 6): se /configuration?idBranch der 500 nas DUAS
 * filiais, para ai — nada de martelar os outros endpoints.
 */
$diag_branches = array_values( array_filter( array_map( 'intval', explode( ',', (string) ( getenv( 'EVO_DIAG_BRANCHES' ) ?: '1,8' ) ) ) ) );
$auth          = array_merge( $std, array( 'Authorization: ' . $b64( $dns_c, $token_c ) ) );
$A             = 'Basic base64(DNS:TOKEN)';
$b1            = $diag_branches[0] ?? 1;

$cfg_safe    = array( 'idBranch', 'internalName', 'city', 'stateShort', 'siteSlug' );
$gw_safe     = array( 'gatewayType', 'showCardType', 'tokenizeBackend', 'validationEnabled', 'gatewayData' );
$plan_safe   = array( 'idMembership', 'idBranch', 'nameMembership', 'displayName', 'membershipType', 'durationType', 'duration', 'value', 'maxAmountInstallments', 'inactive', 'externalSaleAvailable', 'acceptEnrollment', 'enrollmentRequired', 'typePromotionalPeriod', 'valuePromotionalPeriod', 'monthsPromotionalPeriod', 'additionalService', 'urlSale' );
$act_safe    = array( 'idActivity', 'idBranch', 'name', 'inactive', 'showOnWebsite', 'activityGroup', 'audience' );
$sched_safe  = array( 'idActivity', 'idAtividadeSessao', 'idConfiguration', 'name', 'activityDate', 'startTime', 'endTime', 'capacity', 'ocupation', 'area', 'audience', 'status', 'statusName' );
$group_safe  = array( 'groupId', 'groupName', 'branches' );

echo "\n== H6.0 contraprova no mesmo minuto ==\n";
call( 'H6.0a SEM idBranch /configuration', '/api/v1/configuration', $auth, $A );
call( "H6.0b token aleatorio COM idBranch=$b1", "/api/v1/configuration?idBranch=$b1", array_merge( $std, array( 'Authorization: ' . $b64( $dns_c, 'x' . bin2hex( random_bytes( 16 ) ) ) ) ), 'Basic base64(DNS:<token aleatorio>)', null, (string) $b1 );

echo "\n== H6.1 portao: /configuration COM idBranch ==\n";
$gate = array();
foreach ( $diag_branches as $b ) {
	$gate[ $b ] = call( "H6.1 configuration?idBranch=$b", "/api/v1/configuration?idBranch=$b", $auth, $A, $cfg_safe, (string) $b )['http'];
}

$all_500 = array() !== $gate && count( array_filter( $gate, static fn ( int $h ): bool => 500 === $h ) ) === count( $gate );

if ( $all_500 ) {
	$report['h6'] = 'NAO SUFICIENTE: /configuration?idBranch deu 500 em todas as filiais testadas — parada conforme fase 6';
	echo "\nH6: 500 tambem com idBranch em " . implode( ',', array_keys( $gate ) ) . ". Parando (fase 6). Dados do chamado no JSON.\n";
} else {
	echo "\n== H6.2 permissoes (somente GET) ==\n";
	$fake_mail = 'auditoria-' . bin2hex( random_bytes( 4 ) ) . '@example.invalid';
	$plans     = array();

	foreach ( $diag_branches as $b ) {
		$s = (string) $b;
		echo "-- filial $b --\n";
		// Configuracao - Consulta
		call( "CFG group-branches?idBranch=$b", "/api/v1/configuration/group-branches?idBranch=$b", $auth, $A, $group_safe, $s );
		call( "CFG gateway?idBranch=$b", "/api/v2/configuration/gateway?idBranch=$b", $auth, $A, $gw_safe, $s );
		// Contratos - Consulta
		$p           = call( "CTR membership?idBranch=$b", "/api/v3/membership?idBranch=$b&take=50", $auth, $A, $plan_safe, $s );
		$pd = is_array( $p['data'] ) ? $p['data'] : array();
		if ( ! array_is_list( $pd ) ) {
			$pd = is_array( $pd['lista'] ?? null ) && array() !== $pd['lista'] ? $pd['lista'] : ( is_array( $pd['list'] ?? null ) ? $pd['list'] : array() );
		}
		$plans[ $b ] = array_values( array_filter( $pd, 'is_array' ) );
		$first       = (int) ( $plans[ $b ][0]['idMembership'] ?? 0 );
		if ( $first > 0 ) {
			call( "CTR membership item $first", "/api/v3/membership?idBranch=$b&idMembership=$first&take=5", $auth, $A, $plan_safe, $s );
		}
		// Atividade - Consulta
		call( "ATV activities?idBranch=$b", "/api/v1/activities?idBranch=$b&take=50", $auth, $A, $act_safe, $s );
		call( "ATV schedule semana idBranch=$b", '/api/v1/activities/schedule?' . http_build_query( array( 'idBranch' => $b, 'date' => gmdate( 'Y-m-d' ), 'showFullWeek' => 'true', 'take' => 500 ) ), $auth, $A, $sched_safe, $s );
		// Cliente - Consulta sem dados sensiveis: e-mail inexistente (prova
		// autorizacao sem tocar em ninguem) + 1 registro so para ver os CAMPOS.
		call( "CLI members/basic email-inexistente $b", '/api/v1/members/basic?' . http_build_query( array( 'email' => $fake_mail, 'idBranch' => $b, 'take' => 1 ) ), $auth, $A, null, $s );
		call( "CLI members/basic campos $b", "/api/v1/members/basic?idBranch=$b&take=1", $auth, $A, null, $s );
		// Prospects - Consulta
		call( "PRO prospects email-inexistente $b", '/api/v1/prospects?' . http_build_query( array( 'email' => $fake_mail, 'idBranch' => $b, 'take' => 1 ) ), $auth, $A, null, $s );
		call( "PRO prospects campos $b", "/api/v1/prospects?idBranch=$b&take=1", $auth, $A, null, $s );
	}

	echo "-- sem parametro idBranch na especificacao --\n";
	// Vendas: by-session-id nao tem idBranch no swagger; sessao inexistente.
	call( 'VND sales/by-session-id inexistente', '/api/v1/sales/by-session-id?sessionId=audit-' . bin2hex( random_bytes( 8 ) ), $auth, $A );
	call( 'CFG states', '/api/v2/states', $auth, $A );

	// Comparacao entre filiais: os planos sao de fato diferentes?
	$cmp = array();
	foreach ( $plans as $b => $rows ) {
		$cmp[ $b ] = array(
			'count'            => count( $rows ),
			'idBranch_values'  => array_values( array_unique( array_map( static fn ( $r ) => (int) ( $r['idBranch'] ?? 0 ), $rows ) ) ),
			'idMembership_ids' => array_map( static fn ( $r ) => (int) ( $r['idMembership'] ?? 0 ), $rows ),
		);
	}
	$ids = array_column( $cmp, 'idMembership_ids' );
	$report['plans_comparison'] = array(
		'per_branch'          => $cmp,
		'shared_idMembership' => count( $ids ) >= 2 ? array_values( array_intersect( ...$ids ) ) : array(),
	);
	echo "\nComparacao de planos: " . json_encode( $report['plans_comparison'] ) . "\n";
}

endif; // h6

file_put_contents( $out, json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
echo "\nEvidencias: $out\n";
