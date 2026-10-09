<?php
/**
 * Auditoria do token EVO — SOMENTE LEITURA.
 *
 * Duas formas de rodar (as duas usam as credenciais sem nunca imprimi-las):
 *
 *   1) No servidor, com as credenciais ja gravadas no plugin:
 *        wp eval-file scripts/audit-evo-token.php
 *
 *   2) Local, com variaveis de ambiente:
 *        EVO_DNS=... EVO_TOKEN=... php scripts/audit-evo-token.php
 *
 * Saida: resumo no console + JSON de evidencias (EVO_AUDIT_OUT ou
 * ./evo-audit-<data>.json). Nada no JSON contem token, CPF, e-mail,
 * telefone ou nome de pessoa: registros de cliente/prospect/colaborador
 * viram apenas "quantidade + nomes de campos".
 *
 * O que NAO faz: nao cria prospect, nao cria venda, nao cobra, nao altera
 * configuracao. Os "probes de escrita" (EVO_AUDIT_WRITE_PROBES=1, desligado
 * por padrao) mandam JSON MALFORMADO ("{") — o corpo nao desserializa, entao
 * nenhum registro pode ser criado; servem so para ver se a EVO responde 403
 * (sem permissao) ou 400 (autorizou e depois recusou o corpo).
 *
 * Custo: a EVO API Pro cobra por hit. Esta auditoria faz ~40 chamadas.
 */

declare( strict_types = 1 );

/* -------------------------------------------------------------
 * Credenciais
 * ----------------------------------------------------------- */

$in_wp = defined( 'ABSPATH' ) && class_exists( 'Contorno_Evo_Settings' );

if ( $in_wp ) {
	$dns   = Contorno_Evo_Settings::dns();
	$token = Contorno_Evo_Settings::token();
	$base  = Contorno_Evo_Settings::base_url();
	$src   = Contorno_Evo_Settings::credentials_from_constants() ? 'wp-config.php (constantes)' : 'banco (option cifrada)';
} else {
	$dns   = (string) getenv( 'EVO_DNS' );
	$token = (string) getenv( 'EVO_TOKEN' );
	$base  = (string) ( getenv( 'EVO_BASE' ) ?: 'https://evo-integracao-api.w12app.com.br' );
	$src   = 'variaveis de ambiente';
}

$base = rtrim( $base, '/' );

if ( '' === $dns || '' === $token ) {
	fwrite( STDERR, "Credenciais EVO ausentes ($src).\n" );
	exit( 1 );
}

$write_probes = '1' === (string) getenv( 'EVO_AUDIT_WRITE_PROBES' );
$out_file     = (string) ( getenv( 'EVO_AUDIT_OUT' ) ?: ( getcwd() . '/evo-audit-' . gmdate( 'Ymd-His' ) . '.json' ) );

/* -------------------------------------------------------------
 * Mascaras
 * ----------------------------------------------------------- */

function evo_mask_secret( string $s ): string {
	$len = strlen( $s );
	return $len <= 8 ? str_repeat( '*', $len ) : substr( $s, 0, 4 ) . '...' . substr( $s, -4 ) . " ($len chars)";
}

/** Chaves cujo VALOR nunca sai do script. */
const EVO_PII_KEYS = '/(cpf|document|rg|passport|email|mail|phone|cellphone|telephone|whatsapp|firstname|lastname|registername|registerlastname|birth|address|zipcode|number|complement|neighborhood|photo|token|login|card|contacts|responsib|notes|name$|^name|instructor|cnpj|latitude|longitude)/i';

/**
 * Resume uma resposta para evidencia.
 * $safe = lista de campos cujo valor pode aparecer (nao-PII).
 *
 * @return array<string,mixed>
 */
function evo_summarize( mixed $data, array $safe = array(), int $sample = 3 ): array {
	if ( null === $data ) {
		return array( 'type' => 'null' );
	}
	if ( is_scalar( $data ) ) {
		return array( 'type' => gettype( $data ), 'value' => is_string( $data ) ? '(string ' . strlen( $data ) . ' chars)' : $data );
	}

	$is_list = array_is_list( $data );
	$rows    = $is_list ? $data : array( $data );
	$first   = is_array( $rows[0] ?? null ) ? $rows[0] : array();
	$out     = array(
		'type'   => $is_list ? 'array' : 'object',
		'count'  => $is_list ? count( $data ) : 1,
		'fields' => array_keys( $first ),
	);

	if ( array() !== $safe ) {
		$out['sample'] = array();
		foreach ( array_slice( $rows, 0, $sample ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$pick = array();
			foreach ( $safe as $k ) {
				if ( array_key_exists( $k, $row ) ) {
					$v          = $row[ $k ];
					$pick[ $k ] = is_array( $v ) ? '(' . count( $v ) . ' itens)' : ( is_string( $v ) ? mb_substr( $v, 0, 80 ) : $v );
				}
			}
			$out['sample'][] = $pick;
		}
	}

	return $out;
}

/* -------------------------------------------------------------
 * HTTP (curl puro; 1,6 s entre chamadas = <= 37/min)
 * ----------------------------------------------------------- */

// wp eval-file inclui o arquivo dentro de um metodo: variaveis de topo NAO
// sao globais. Tudo que as funcoes precisam vai explicitamente em $GLOBALS.
$GLOBALS['evo_cfg']      = array( 'dns' => $dns, 'token' => $token, 'base' => $base );
$GLOBALS['evo_evidence'] = array();
$GLOBALS['evo_last']     = 0.0;

/**
 * @return array{http:int,data:mixed,raw_len:int,error:string,ms:int,mensagens:array<int,string>}
 */
function evo_call( string $method, string $path, array $query = array(), ?string $raw_body = null ): array {
	$dns   = $GLOBALS['evo_cfg']['dns'];
	$token = $GLOBALS['evo_cfg']['token'];
	$base  = $GLOBALS['evo_cfg']['base'];

	$wait = 1.6 - ( microtime( true ) - $GLOBALS['evo_last'] );
	if ( $GLOBALS['evo_last'] > 0 && $wait > 0 ) {
		usleep( (int) ( $wait * 1e6 ) );
	}
	$GLOBALS['evo_last'] = microtime( true );

	$url = $base . $path . ( $query ? '?' . http_build_query( $query ) : '' );
	$ch  = curl_init( $url );
	$h   = array( 'Accept: application/json', 'culture: pt-BR', 'Authorization: Basic ' . base64_encode( $dns . ':' . $token ) );

	// PHP local sem CA configurado (comum no Windows): usa o bundle do proprio
	// WordPress. Verificacao TLS continua LIGADA.
	$ca = (string) ( getenv( 'EVO_CA_BUNDLE' ) ?: dirname( __DIR__ ) . '/wp-includes/certificates/ca-bundle.crt' );
	if ( is_file( $ca ) ) {
		curl_setopt( $ch, CURLOPT_CAINFO, $ca );
	}

	if ( null !== $raw_body ) {
		$h[] = 'Content-Type: application/json';
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $raw_body );
	}

	curl_setopt_array(
		$ch,
		array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTPHEADER     => $h,
			CURLOPT_USERAGENT      => 'ContornoEvoAudit/1.0 (read-only)',
		)
	);

	$t0   = microtime( true );
	$body = curl_exec( $ch );
	$ms   = (int) ( ( microtime( true ) - $t0 ) * 1000 );
	$http = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	$err  = false === $body ? curl_error( $ch ) : '';
	curl_close( $ch );

	$body = (string) $body;
	$data = json_decode( $body, true );
	$msgs = array();

	if ( $http >= 400 ) {
		if ( is_array( $data ) && isset( $data['mensagens'] ) && is_array( $data['mensagens'] ) ) {
			$msgs = array_map( 'strval', array_slice( $data['mensagens'], 0, 4 ) );
		} elseif ( is_array( $data ) && isset( $data['errors'] ) ) {
			$msgs = array( mb_substr( json_encode( $data['errors'] ), 0, 300 ) );
		} elseif ( is_array( $data ) && isset( $data['title'] ) ) {
			$msgs = array( (string) $data['title'] );
		} elseif ( '' !== trim( $body ) ) {
			$msgs = array( mb_substr( trim( strip_tags( $body ) ), 0, 200 ) );
		}
		// Mensagem de erro pode ecoar dado enviado; nada nosso aqui e PII, mas
		// por garantia removemos sequencias longas de digitos.
		$msgs = array_map( static fn ( $m ) => preg_replace( '/\d{6,}/', '[num]', $m ), $msgs );
	}

	return array( 'http' => $http, 'data' => $data, 'raw_len' => strlen( $body ), 'error' => $err, 'ms' => $ms, 'mensagens' => $msgs );
}

function evo_probe( string $id, string $perm, string $method, string $path, array $query = array(), array $safe = array(), ?string $raw_body = null, string $unit = '' ): array {
	$r   = evo_call( $method, $path, $query, $raw_body );
	$rec = array(
		'id'         => $id,
		'permission' => $perm,
		'method'     => $method,
		'path'       => $path,
		// Filtros de PII na query aparecem mascarados.
		'query'      => array_map( static fn ( $v ) => is_string( $v ) && str_contains( $v, '@' ) ? 'x***@invalid' : $v, $query ),
		'body'       => null === $raw_body ? null : 'JSON malformado ("' . $raw_body . '") — nao desserializa',
		'unit'       => $unit,
		'http'       => $r['http'],
		'ms'         => $r['ms'],
		'net_error'  => $r['error'],
		'body_len'   => $r['raw_len'],
		'mensagens'  => $r['mensagens'],
		'summary'    => $r['http'] >= 200 && $r['http'] < 300 ? evo_summarize( $r['data'], $safe ) : null,
	);

	$GLOBALS['evo_evidence'][] = $rec;

	$count = $rec['summary']['count'] ?? '-';
	printf( "%-34s %-6s %-48s HTTP %3d  itens=%s %s\n", $id, $method, $path, $r['http'], $count, $r['mensagens'] ? '| ' . implode( '; ', $r['mensagens'] ) : '' );

	return $r + array( 'rec' => $rec );
}

/* -------------------------------------------------------------
 * Execucao
 * ----------------------------------------------------------- */

echo "Base: $base\nDNS: " . evo_mask_secret( $dns ) . "\nToken: " . evo_mask_secret( $token ) . "\nFonte: $src\nProbes de escrita: " . ( $write_probes ? 'SIM (corpo "{" invalido de proposito; nenhuma variavel e JSON)' : 'nao' ) . "\n\n";

// 0. Autenticacao + custo da API.
evo_probe( 'auth.api_usage', 'Configuracao - Consulta', 'GET', '/api/v1/configuration/api-usage' );

// 1. Multifilial.
$gb       = evo_probe( 'branches.group_branches', 'Configuracao - Consulta', 'GET', '/api/v1/configuration/group-branches', array(), array( 'groupId', 'groupName', 'branches' ) );
$branches = array();
if ( is_array( $gb['data'] ) ) {
	foreach ( $gb['data'] as $g ) {
		foreach ( (array) ( $g['branches'] ?? array() ) as $b ) {
			if ( ! empty( $b['branchId'] ) ) {
				$branches[ (int) $b['branchId'] ] = (string) ( $b['branchName'] ?? '' );
			}
		}
	}
}
$cfg = evo_probe( 'config.configuration', 'Configuracao - Consulta', 'GET', '/api/v1/configuration', array(), array( 'idBranch', 'internalName', 'city', 'stateShort', 'siteSlug' ), null );
if ( is_array( $cfg['data'] ) ) {
	foreach ( ( array_is_list( $cfg['data'] ) ? $cfg['data'] : array( $cfg['data'] ) ) as $row ) {
		if ( is_array( $row ) && ! empty( $row['idBranch'] ) && ! isset( $branches[ (int) $row['idBranch'] ] ) ) {
			$branches[ (int) $row['idBranch'] ] = (string) ( $row['internalName'] ?? $row['name'] ?? '' );
		}
	}
}
ksort( $branches );
echo "\nFiliais visiveis: " . count( $branches ) . "\n";
foreach ( $branches as $id => $name ) {
	echo "  - $id  $name\n";
}
echo "\n";

// Duas unidades para comparacao: EVO_AUDIT_BRANCHES=12,34 ou as duas primeiras.
$pick = array_filter( array_map( 'intval', explode( ',', (string) getenv( 'EVO_AUDIT_BRANCHES' ) ) ) );
$test = $pick ? array_values( $pick ) : array_slice( array_keys( $branches ), 0, 2 );

// 2. Configuracao por unidade + gateway.
foreach ( $test as $b ) {
	evo_probe( "config.configuration[$b]", 'Configuracao - Consulta', 'GET', '/api/v1/configuration', array( 'idBranch' => $b ), array( 'idBranch', 'internalName', 'city', 'stateShort', 'siteSlug' ), null, (string) $b );
	evo_probe( "config.gateway[$b]", 'Configuracao - Consulta', 'GET', '/api/v2/configuration/gateway', array( 'idBranch' => $b ), array( 'gatewayType', 'showCardType', 'tokenizeBackend', 'validationEnabled' ), null, (string) $b );
}
evo_probe( 'config.states', 'Configuracao - Consulta', 'GET', '/api/v2/states' );

// 3. Contratos / planos.
$plan_safe = array( 'idMembership', 'idBranch', 'nameMembership', 'displayName', 'membershipType', 'durationType', 'duration', 'value', 'maxAmountInstallments', 'inactive', 'externalSaleAvailable', 'acceptEnrollment', 'urlSale' );
$all_plans = evo_probe( 'plans.global', 'Contratos - Consulta', 'GET', '/api/v3/membership', array( 'take' => 50, 'skip' => 0 ), $plan_safe, null, 'todas' );
$plan_by_branch = array();
foreach ( $test as $b ) {
	$p = evo_probe( "plans.branch[$b]", 'Contratos - Consulta', 'GET', '/api/v3/membership', array( 'take' => 50, 'skip' => 0, 'idBranch' => $b ), $plan_safe, null, (string) $b );
	$plan_by_branch[ $b ] = is_array( $p['data'] ) ? $p['data'] : array();
}
// Distribuicao por filial no retorno global.
$dist = array();
foreach ( (array) $all_plans['data'] as $row ) {
	if ( is_array( $row ) ) {
		$dist[ (int) ( $row['idBranch'] ?? 0 ) ] = ( $dist[ (int) ( $row['idBranch'] ?? 0 ) ] ?? 0 ) + 1;
	}
}
$GLOBALS['evo_evidence'][] = array( 'id' => 'plans.global.distribution', 'idBranch_counts' => $dist );
echo '  distribuicao global por idBranch: ' . json_encode( $dist ) . "\n";

// Consulta de um item.
foreach ( $test as $b ) {
	$first = $plan_by_branch[ $b ][0]['idMembership'] ?? null;
	if ( $first ) {
		evo_probe( "plans.item[$b:$first]", 'Contratos - Consulta', 'GET', '/api/v3/membership', array( 'idMembership' => (int) $first, 'idBranch' => $b, 'take' => 5 ), $plan_safe, null, (string) $b );
	}
}
evo_probe( 'plans.category', 'Contratos - Consulta', 'GET', '/api/v1/membership/category', array(), array( 'idMembershipCategory', 'name' ) );
foreach ( $test as $b ) {
	evo_probe( "sales.sales_items[$b]", 'Vendas / Contratos', 'GET', '/api/v1/sales/sales-items', array( 'idBranch' => $b ), array( 'nameSalePage', 'idBranch', 'idSaleItem', 'notInaugurated', 'itens' ), null, (string) $b );
}

// 4. Atividades e grade.
$sched_safe = array( 'idActivity', 'idAtividadeSessao', 'name', 'activityDate', 'startTime', 'endTime', 'capacity', 'ocupation', 'area', 'audience', 'status', 'statusName' );
foreach ( $test as $b ) {
	evo_probe( "activities.list[$b]", 'Atividade - Consulta', 'GET', '/api/v1/activities', array( 'idBranch' => $b, 'take' => 50 ), array( 'idActivity', 'idBranch', 'name', 'inactive', 'showOnWebsite', 'activityGroup' ), null, (string) $b );
	evo_probe( "activities.schedule_week[$b]", 'Atividade - Consulta', 'GET', '/api/v1/activities/schedule', array( 'idBranch' => $b, 'showFullWeek' => 'true', 'date' => gmdate( 'Y-m-d' ), 'take' => 200 ), $sched_safe, null, (string) $b );
}

// 5. Cliente — sem dados sensiveis. Filtro inexistente => prova autorizacao sem expor ninguem.
$fake_mail = 'auditoria-' . bin2hex( random_bytes( 4 ) ) . '@example.invalid';
evo_probe( 'members.basic.by_email_absent', 'Cliente - Consulta s/ sensiveis', 'GET', '/api/v1/members/basic', array( 'email' => $fake_mail, 'take' => 1 ) );
if ( $test ) {
	// Um registro real so para listar QUAIS CAMPOS a rota devolve (valores descartados).
	evo_probe( 'members.basic.fields', 'Cliente - Consulta s/ sensiveis', 'GET', '/api/v1/members/basic', array( 'idBranch' => $test[0], 'take' => 1 ), array(), null, (string) $test[0] );
}

// 6. Prospects — consulta.
evo_probe( 'prospects.by_email_absent', 'Prospects - Consulta', 'GET', '/api/v1/prospects', array( 'email' => $fake_mail, 'take' => 1 ) );
if ( $test ) {
	evo_probe( 'prospects.fields', 'Prospects - Consulta', 'GET', '/api/v1/prospects', array( 'idBranch' => $test[0], 'take' => 1 ), array(), null, (string) $test[0] );
}

// 7. Vendas — leitura que o fluxo usa (reconciliacao por sessionId).
evo_probe( 'sales.by_session_absent', 'Vendas', 'GET', '/api/v1/sales/by-session-id', array( 'sessionId' => 'audit-' . bin2hex( random_bytes( 8 ) ) ) );

// 8. Permissoes EXCESSIVAS — so leitura, so HTTP + quantidade.
$excess = array(
	array( 'excess.members_full', 'Cliente - dados sensiveis (NAO solicitado)', '/api/v2/members', array( 'take' => 1 ) ),
	array( 'excess.employees', 'Colaboradores (NAO solicitado)', '/api/v2/employees', array( 'take' => 1 ) ),
	array( 'excess.employee_permissions', 'Colaboradores (NAO solicitado)', '/api/v1/employees/permissions', array( 'take' => 50 ) ),
	array( 'excess.receivables', 'Financeiro (NAO solicitado)', '/api/v1/receivables', array( 'take' => 1 ) ),
	array( 'excess.payables', 'Financeiro (NAO solicitado)', '/api/v1/payables', array( 'take' => 1 ) ),
	array( 'excess.bank_accounts', 'Financeiro (NAO solicitado)', '/api/v1/bank-accounts', array() ),
	array( 'excess.debtors', 'Financeiro (NAO solicitado)', '/api/v1/receivables/debtors', array( 'take' => 1 ) ),
	array( 'excess.sales_list', 'Vendas - Consulta (NAO solicitado)', '/api/v2/sales', array( 'take' => 1 ) ),
	array( 'excess.active_clients', 'Gerencial (NAO solicitado)', '/api/v2/management/activeclients', array() ),
	array( 'excess.webhooks', 'Webhook (NAO solicitado)', '/api/v2/webhook', array() ),
	array( 'excess.entries', 'Acessos (NAO solicitado)', '/api/v1/entries', array( 'take' => 1 ) ),
	array( 'excess.member_memberships', 'Contratos de clientes (NAO solicitado)', '/api/v3/membermembership', array( 'take' => 1 ) ),
);
foreach ( $excess as $e ) {
	evo_probe( $e[0], $e[1], 'GET', $e[2], $e[3] );
}

// 9. Probes de escrita com corpo que NAO desserializa (opt-in).
if ( $write_probes ) {
	echo "\n-- probes de escrita (JSON malformado; nada pode ser criado) --\n";
	evo_probe( 'write.prospects_post', 'Prospects - Edicao', 'POST', '/api/v1/prospects', array(), array(), '{' );
	evo_probe( 'write.prospects_patch', 'Prospects - Edicao', 'PATCH', '/api/v1/prospects', array(), array(), '{' );
	evo_probe( 'write.sales_post', 'Vendas - Edicao', 'POST', '/api/v2/sales', array(), array(), '{' );
	// Controles NEGATIVOS: permissoes que NAO pedimos. Se estes derem 403 e os
	// de cima 400, fica provado que a EVO checa permissao ANTES do corpo.
	evo_probe( 'write.ctrl_config_sale_settings', 'Configuracao - Edicao (NAO solicitado)', 'PATCH', '/api/v1/configuration/branch/sale-settings', array(), array(), '{' );
	evo_probe( 'write.ctrl_employees', 'Colaboradores - Edicao (NAO solicitado)', 'POST', '/api/v1/employees', array(), array(), '{' );
	evo_probe( 'write.ctrl_receivables_cancel', 'Financeiro - Estorno/Cancelamento (NAO solicitado)', 'POST', '/api/v1/receivables/cancel', array(), array(), '{' );
}

$report = array(
	'generated_at'   => gmdate( 'c' ),
	'base'           => $base,
	'dns_masked'     => evo_mask_secret( $dns ),
	'token_masked'   => evo_mask_secret( $token ),
	'source'         => $src,
	'branches'       => $branches,
	'tested_units'   => $test,
	'write_probes'   => $write_probes,
	'evidence'       => $GLOBALS['evo_evidence'],
);

file_put_contents( $out_file, json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
echo "\nEvidencias: $out_file\n";
