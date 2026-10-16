<?php
/**
 * Validacao unidades x planos x agenda contra a EVO (somente GET, nada e criado/alterado).
 *
 *   php scripts/validate-evo-units.php
 *
 * Pergunta DNS e token (oculto). Para cada unidade/CTN publicada no site:
 *   1. le a pagina publica: planos exibidos (nome, preco, "de") e o iframe da
 *      agenda (idBranch do EVO Totem);
 *   2. le o dataset (evo_branch_id, classes_enabled, idMembership pela URL de
 *      checkout ou evo_membership_id) e o mapa por slug do contorno-core;
 *   3. na EVO: lista de filiais, planos da filial (/api/v3/membership) e grade
 *      da semana (/api/v1/activities/schedule).
 * e aponta divergencias. Grava relatorio JSON + amostra da grade (para a grade
 * nativa) em %TEMP%/evo-validate-units.json e evo-schedule-sample.json.
 *
 * Variaveis opcionais: EVO_DNS, EVO_TOKEN, EVO_BASE, SITE_URL (padrao: staging),
 * ONLY=slug1,slug2 (testar poucas unidades).
 */

declare( strict_types = 1 );

$root = dirname( __DIR__ );
$base = rtrim( (string) ( getenv( 'EVO_BASE' ) ?: 'https://evo-integracao-api.w12app.com.br' ), '/' );
$site = rtrim( (string) ( getenv( 'SITE_URL' ) ?: 'https://contornowp.voceconecta.com.br' ), '/' );
$ca   = $root . '/wp-includes/certificates/ca-bundle.crt';
$only = array_filter( array_map( 'trim', explode( ',', (string) getenv( 'ONLY' ) ) ) );
$tmp  = sys_get_temp_dir();

function ask( string $label, bool $secret = false ): string {
	echo $label . ': ';
	if ( 'Windows' === PHP_OS_FAMILY ) {
		$ps = $secret
			? '$s = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))'
			: '[Console]::ReadLine()';
		$v = (string) shell_exec( 'powershell -NoProfile -Command "' . $ps . '"' );
		if ( $secret ) {
			echo "\n";
		}
		return trim( (string) preg_replace( '/\R/', '', $v ) );
	}
	return trim( (string) fgets( STDIN ) );
}

$dns   = getenv( 'SITE_ONLY' ) ? '' : (string) ( getenv( 'EVO_DNS' ) ?: ask( 'DNS EVO' ) );
$token = getenv( 'SITE_ONLY' ) ? '' : (string) ( getenv( 'EVO_TOKEN' ) ?: ask( 'Token EVO (oculto)', true ) );

function http( string $url, array $headers = array() ): array {
	global $ca;
	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => 40,
			CURLOPT_CAINFO         => $ca,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_USERAGENT      => 'contorno-validate/1.0',
		)
	);
	$body = (string) curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );
	return array( $code, $body );
}

/** GET na EVO com intervalo de 1,6 s e 1 nova tentativa em 429. */
function evo( string $path, array $query ): array {
	global $base, $dns, $token;
	for ( $try = 0; $try < 2; $try++ ) {
		usleep( 1600000 );
		[ $code, $body ] = http(
			$base . $path . ( $query ? '?' . http_build_query( $query ) : '' ),
			array( 'Accept: application/json', 'culture: pt-BR', 'Authorization: Basic ' . base64_encode( $dns . ':' . $token ) )
		);
		if ( 429 !== $code ) {
			break;
		}
		echo "  429, aguardando 20 s...\n";
		sleep( 20 );
	}
	return array( $code, json_decode( $body, true ) );
}

function rows( mixed $data ): array {
	if ( ! is_array( $data ) ) {
		return array( array(), null );
	}
	if ( array_is_list( $data ) ) {
		return array( array_values( array_filter( $data, 'is_array' ) ), null );
	}
	$total = isset( $data['qtde'] ) && is_numeric( $data['qtde'] ) ? (int) $data['qtde'] : null;
	foreach ( array( 'list', 'lista' ) as $k ) {
		if ( ! empty( $data[ $k ] ) && is_array( $data[ $k ] ) ) {
			return array( array_values( array_filter( $data[ $k ], 'is_array' ) ), $total );
		}
	}
	return array( array(), $total );
}

function price( string $s ): ?float {
	if ( ! preg_match( '/R\$\s*([\d.]+,\d{2})/u', $s, $m ) ) {
		return null;
	}
	return (float) str_replace( array( '.', ',' ), array( '', '.' ), $m[1] );
}

function norm( string $s ): string {
	$s = mb_strtolower( trim( $s ) );
	$s = strtr( $s, array( 'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c' ) );
	return (string) preg_replace( '/[^a-z0-9]+/', ' ', $s );
}

function text( string $html ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

/* ------------------------------------------------------------------ */
/* 1. Dataset e mapa por slug                                          */
/* ------------------------------------------------------------------ */

$dataset = json_decode( (string) file_get_contents( $root . '/wp-content/plugins/contorno-core/data/dataset.json' ), true );
$ds      = array();
foreach ( array( 'units' => 'unidades', 'ctns' => 'ctn' ) as $key => $type ) {
	foreach ( (array) ( $dataset[ $key ] ?? array() ) as $row ) {
		$ds[ $type . '/' . $row['slug'] ] = $row['fields'] ?? array();
	}
}

$slug_map = array();
$evo_php  = (string) file_get_contents( $root . '/wp-content/plugins/contorno-core/includes/data/evo.php' );
if ( preg_match_all( "/'([a-z0-9-]+)'\s*=>\s*'(\d+)'/", $evo_php, $mm, PREG_SET_ORDER ) ) {
	foreach ( $mm as $m ) {
		$slug_map[ $m[1] ] = (int) $m[2];
	}
}

/* ------------------------------------------------------------------ */
/* 2. Site publico                                                     */
/* ------------------------------------------------------------------ */

echo "\nSite: $site\n";
$units = array();
foreach ( array( 'unidades', 'ctn' ) as $type ) {
	[ $code, $body ] = http( $site . '/wp-json/wp/v2/' . $type . '?per_page=100&_fields=slug,link,title' );
	foreach ( (array) json_decode( $body, true ) as $p ) {
		if ( ! is_array( $p ) || ( $only && ! in_array( $p['slug'], $only, true ) ) ) {
			continue;
		}
		$units[] = array( 'type' => $type, 'slug' => $p['slug'], 'title' => text( (string) ( $p['title']['rendered'] ?? '' ) ), 'link' => $p['link'] );
	}
}
echo count( $units ) . " paginas publicadas. Lendo HTML...\n";

foreach ( $units as &$u ) {
	[ $code, $html ] = http( $u['link'] );
	$u['http']       = $code;
	$u['iframe']     = preg_match( '#evo-totem\.w12app\.com\.br/contornodocorpo/(\d+)/#', $html, $m ) ? (int) $m[1] : 0;
	$u['site_plans'] = array();
	if ( preg_match_all( '#<article class="contorno-plan[ "].*?</article>#s', $html, $arts ) ) {
		foreach ( $arts[0] as $a ) {
			preg_match( '#contorno-plan__name">(.*?)</#s', $a, $n );
			preg_match( '#contorno-plan__price">(.*?)</#s', $a, $p );
			preg_match( '#contorno-plan__from">(.*?)</#s', $a, $f );
			preg_match( '#contorno-plan__note">(.*?)</#s', $a, $no );
			preg_match( '#[?&](?:amp;|\#038;)?plano=([a-z0-9_-]+)#i', $a, $id );
			$u['site_plans'][] = array(
				'id'    => $id[1] ?? '',
				'name'  => text( $n[1] ?? '' ),
				'price' => price( text( $p[1] ?? '' ) ),
				'label' => text( $p[1] ?? '' ),
				'from'  => price( text( $f[1] ?? '' ) ),
				'note'  => text( $no[1] ?? '' ),
			);
		}
	}
	$f              = $ds[ $u['type'] . '/' . $u['slug'] ] ?? null;
	$u['in_dataset'] = null !== $f;
	$u['ds_branch']  = (int) ( $f['evo_branch_id'] ?? 0 );
	$u['ds_classes'] = ! empty( $f['classes_enabled'] );
	// O mapa por slug e de unidades; CTN tem outra filial com o mesmo slug.
	$u['map_branch'] = 'unidades' === $u['type'] ? ( $slug_map[ $u['slug'] ] ?? 0 ) : 0;
	$u['url_branches'] = array();
	$u['ds_plans']   = array();
	foreach ( array_merge( array( array( 'checkout_url' => $f['checkout_url'] ?? '' ) ), (array) ( $f['plans'] ?? array() ) ) as $i => $pl ) {
		$mid = (int) ( $pl['evo_membership_id'] ?? 0 );
		if ( preg_match( '#/contornodocorpo/(\d+)/site/landing-page/checkout/(\d+)(?!\d)#', (string) ( $pl['checkout_url'] ?? '' ), $m ) ) {
			$u['url_branches'][ (int) $m[1] ] = true;
			$mid = $mid ?: (int) $m[2];
		}
		if ( $i > 0 ) {
			$u['ds_plans'][ (string) ( $pl['id'] ?? '' ) ] = array( 'name' => $pl['name'] ?? '', 'price' => $pl['price'] ?? null, 'id_membership' => $mid );
		}
	}
	$u['url_branches'] = array_keys( $u['url_branches'] );
	// Filial que o sync usaria (sem acesso ao meta explicito do banco).
	$u['branch'] = $u['ds_branch'] ?: ( 1 === count( $u['url_branches'] ) ? $u['url_branches'][0] : $u['map_branch'] );
}
unset( $u );

if ( getenv( 'SITE_ONLY' ) ) { // so a leitura do site, sem EVO
	echo json_encode( $units, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
	exit( 0 );
}

/* ------------------------------------------------------------------ */
/* 3. EVO                                                              */
/* ------------------------------------------------------------------ */

echo "\nEVO: $base\n";
[ $code, $data ] = evo( '/api/v1/configuration', array() );
[ $cfg ]         = rows( $data );
$evo_branches    = array();
foreach ( $cfg as $row ) {
	if ( ! empty( $row['idBranch'] ) ) {
		$evo_branches[ (int) $row['idBranch'] ] = (string) ( $row['name'] ?? $row['internalName'] ?? '' );
	}
}
echo "configuration: HTTP $code, " . count( $evo_branches ) . " filiais\n";
if ( 200 !== $code ) {
	fwrite( STDERR, "Sem acesso a EVO (HTTP $code). Confira DNS/token.\n" );
	exit( 1 );
}

$branches = array_values( array_unique( array_filter( array_merge( array_column( $units, 'branch' ), array_column( $units, 'iframe' ) ) ) ) );
sort( $branches );
$plans    = array();
$schedule = array();
$sample   = array();
$monday   = date( 'Y-m-d', strtotime( 'monday this week' ) );

foreach ( $branches as $i => $b ) {
	printf( "[%d/%d] filial %d %s\n", $i + 1, count( $branches ), $b, $evo_branches[ $b ] ?? '(nao visivel)' );
	$items = array();
	$skip  = 0;
	$ok    = true;
	do {
		[ $code, $data ]   = evo( '/api/v3/membership', array( 'idBranch' => $b, 'take' => 50, 'skip' => $skip ) );
		[ $batch, $total ] = rows( $data );
		if ( 200 !== $code ) {
			$ok = false;
			break;
		}
		$items = array_merge( $items, $batch );
		$skip += 50;
	} while ( count( $batch ) >= 50 && ( null === $total || $skip < $total ) && $skip < 2000 );
	$plans[ $b ] = array( 'ok' => $ok, 'http' => $code, 'items' => array() );
	foreach ( $items as $it ) {
		$plans[ $b ]['items'][ (int) $it['idMembership'] ] = array(
			'id_branch'   => (int) ( $it['idBranch'] ?? 0 ),
			'name'        => (string) ( $it['displayName'] ?? $it['nameMembership'] ?? '' ),
			'value'       => round( (float) ( $it['value'] ?? 0 ), 2 ),
			'promo_value' => round( (float) ( $it['valuePromotionalPeriod'] ?? 0 ), 2 ),
			'promo_months' => (int) ( $it['monthsPromotionalPeriod'] ?? 0 ),
			'promo_days'  => (int) ( $it['daysPromotionalPeriod'] ?? 0 ),
			'inactive'    => ! empty( $it['inactive'] ),
			'online'      => ! empty( $it['externalSaleAvailable'] ),
		);
	}

	[ $code, $data ] = evo( '/api/v1/activities/schedule', array( 'idBranch' => $b, 'date' => $monday, 'showFullWeek' => 'true', 'take' => 500 ) );
	[ $sch ]         = rows( $data );
	$schedule[ $b ]  = array( 'http' => $code, 'count' => count( $sch ), 'activities' => array() );
	foreach ( $sch as $s ) {
		$name = (string) ( $s['name'] ?? $s['activityDescription'] ?? $s['description'] ?? '?' );
		$schedule[ $b ]['activities'][ $name ] = ( $schedule[ $b ]['activities'][ $name ] ?? 0 ) + 1;
	}
	if ( $sch && count( $sample ) < 3 ) {
		$sample[ $b ] = array_slice( $sch, 0, 5 ); // amostra crua: base para a grade nativa
	}
}

/* ------------------------------------------------------------------ */
/* 4. Cruzamento                                                       */
/* ------------------------------------------------------------------ */

$report = array();
$totals = array( 'units' => 0, 'ok' => 0, 'issues' => 0 );

foreach ( $units as $u ) {
	$issues = array();
	$b      = $u['branch'];
	$label  = $u['type'] . '/' . $u['slug'];

	if ( 200 !== $u['http'] ) {
		$issues[] = "pagina HTTP {$u['http']}";
	}
	if ( ! $b ) {
		$issues[] = 'sem filial EVO (evo_branch_id vazio, sem URL de checkout e fora do mapa por slug)';
	} elseif ( ! isset( $evo_branches[ $b ] ) ) {
		$issues[] = "filial $b nao existe/nao e visivel na EVO";
	}
	if ( $u['ds_branch'] && $u['map_branch'] && $u['ds_branch'] !== $u['map_branch'] ) {
		$issues[] = "evo_branch_id {$u['ds_branch']} != mapa por slug {$u['map_branch']}";
	}
	if ( count( $u['url_branches'] ) > 1 || ( $u['url_branches'] && $b && ! in_array( $b, $u['url_branches'], true ) ) ) {
		$issues[] = 'URLs de checkout apontam para filial(is) ' . implode( ',', $u['url_branches'] ) . " (filial usada: $b)";
	}

	// Agenda
	$sch = $schedule[ $u['iframe'] ?: $b ] ?? null;
	if ( $u['iframe'] && $b && $u['iframe'] !== $b ) {
		$issues[] = "AGENDA: iframe aponta para filial {$u['iframe']}, mas a unidade e filial $b";
	}
	if ( $u['iframe'] && $sch && 200 === $sch['http'] && 0 === $sch['count'] ) {
		$issues[] = "AGENDA: iframe exibido, mas a filial {$u['iframe']} nao tem aulas na EVO nesta semana (grade vazia)";
	}
	if ( ! $u['iframe'] && $b && ( $schedule[ $b ]['count'] ?? 0 ) > 0 ) {
		$issues[] = "AGENDA: filial $b tem {$schedule[$b]['count']} aulas na semana, mas a pagina nao mostra a agenda";
	}
	if ( $sch && 200 !== $sch['http'] ) {
		$issues[] = "AGENDA: /activities/schedule HTTP {$sch['http']}";
	}

	// Planos
	$evo_plans = $plans[ $b ]['items'] ?? array();
	$used      = array();
	if ( $b && isset( $plans[ $b ] ) && ! $plans[ $b ]['ok'] ) {
		$issues[] = "PLANOS: /api/v3/membership HTTP {$plans[$b]['http']}";
	}
	if ( ! $u['site_plans'] ) {
		$issues[] = 'PLANOS: pagina sem planos exibidos';
	}
	$plan_rows = array();
	foreach ( $u['site_plans'] as $sp ) {
		$dsp = $u['ds_plans'][ $sp['id'] ] ?? null;
		$mid = (int) ( $dsp['id_membership'] ?? 0 );
		$row = array( 'site' => $sp, 'id_membership' => $mid, 'evo' => null );
		if ( ! $mid ) {
			// Sem vinculo no dataset: tenta achar por nome entre os ativos (so sugestao).
			foreach ( $evo_plans as $id => $ep ) {
				if ( ! $ep['inactive'] && str_contains( norm( $ep['name'] ), norm( $sp['name'] ) ) ) {
					$row['suggested'][] = $id;
				}
			}
			$issues[] = "PLANOS: '{$sp['name']}' sem idMembership no dataset" . ( ! empty( $row['suggested'] ) ? ' (candidatos por nome: ' . implode( ',', $row['suggested'] ) . ')' : '' );
		} elseif ( ! isset( $evo_plans[ $mid ] ) ) {
			$issues[] = "PLANOS: '{$sp['name']}' -> idMembership $mid NAO existe na filial $b";
		} else {
			$ep         = $evo_plans[ $mid ];
			$row['evo'] = $ep;
			$used[ $mid ] = true;
			if ( $ep['inactive'] ) {
				$issues[] = "PLANOS: '{$sp['name']}' -> $mid esta INATIVO na EVO e continua no site";
			}
			$has_promo = $ep['promo_value'] > 0 && $ep['promo_value'] < $ep['value'] && ( $ep['promo_months'] > 0 || $ep['promo_days'] > 0 );
			$first     = $has_promo ? $ep['promo_value'] : $ep['value'];
			if ( null !== $sp['price'] && abs( $sp['price'] - $first ) > 0.009 ) {
				$issues[] = sprintf( "PLANOS: '%s' preco no site R$ %.2f != EVO R$ %.2f%s (id %d)", $sp['name'], $sp['price'], $first, $has_promo ? sprintf( ' promo, depois R$ %.2f', $ep['value'] ) : '', $mid );
			}
			if ( null === $sp['price'] ) {
				// Preco em texto livre (price_label): confere se o valor da EVO aparece.
				$txt = $sp['label'] . ' ' . $sp['note'];
				if ( '' === trim( $txt ) ) {
					$issues[] = sprintf( "PLANOS: '%s' card SEM PRECO no site (EVO: R$ %.2f%s, id %d)", $sp['name'], $first, $has_promo ? sprintf( ' promo, depois R$ %.2f', $ep['value'] ) : '', $mid );
				} elseif ( null === price( $txt ) || ( ! str_contains( $txt, number_format( $first, 2, ',', '.' ) ) && ! str_contains( $txt, number_format( $ep['value'], 2, ',', '.' ) ) ) ) {
					$issues[] = sprintf( "PLANOS: '%s' preco em texto livre ('%s') nao confere com EVO R$ %.2f / R$ %.2f (id %d)", $sp['name'], mb_substr( $sp['label'], 0, 60 ), $first, $ep['value'], $mid );
				}
			}
		}
		$plan_rows[] = $row;
	}
	$extra = array();
	foreach ( $evo_plans as $id => $ep ) {
		if ( ! $ep['inactive'] && $ep['online'] && ! isset( $used[ $id ] ) ) {
			$extra[ $id ] = sprintf( '%s R$ %.2f', $ep['name'], $ep['value'] );
		}
	}

	++$totals['units'];
	$issues ? ++$totals['issues'] : ++$totals['ok'];
	$report[ $label ] = array(
		'title'        => $u['title'],
		'branch'       => $b,
		'evo_name'     => $evo_branches[ $b ] ?? '',
		'iframe'       => $u['iframe'],
		'week_classes' => $schedule[ $u['iframe'] ?: $b ]['count'] ?? null,
		'plans'        => $plan_rows,
		'evo_online_not_on_site' => $extra,
		'issues'       => $issues,
	);
}

file_put_contents( $tmp . '/evo-validate-units.json', json_encode( array( 'site' => $site, 'when' => date( 'c' ), 'totals' => $totals, 'evo_branches' => $evo_branches, 'evo_branches_without_page' => array_diff_key( $evo_branches, array_flip( array_column( $report, 'branch' ) ) ), 'units' => $report, 'schedule' => $schedule ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
file_put_contents( $tmp . '/evo-schedule-sample.json', json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

echo "\n=== Resultado ===\n";
foreach ( $report as $label => $r ) {
	printf( "%s  %-40s filial %-3s %-28s agenda:%s aulas/semana:%s\n", $r['issues'] ? 'XX' : 'OK', $label, $r['branch'] ?: '-', mb_substr( $r['evo_name'], 0, 28 ), $r['iframe'] ?: '-', $r['week_classes'] ?? '-' );
	foreach ( $r['issues'] as $i ) {
		echo "      - $i\n";
	}
}
printf( "\n%d unidades: %d OK, %d com divergencia.\nRelatorio: %s\nAmostra da grade: %s\n", $totals['units'], $totals['ok'], $totals['issues'], $tmp . DIRECTORY_SEPARATOR . 'evo-validate-units.json', $tmp . DIRECTORY_SEPARATOR . 'evo-schedule-sample.json' );
