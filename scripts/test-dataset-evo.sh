#!/usr/bin/env bash
# Integridade do vinculo EVO no dataset.
#
# Nasceu de um defeito real: na migracao, o plano "easy" de 5 unidades ficou
# com a URL de checkout do "black". Os dois cards apontavam para o mesmo
# idMembership, entao quem escolhia "Easy" era levado ao checkout do "Black" —
# e, no sync, o card "Easy" receberia o preco do "Black".
#
# Somente leitura: le data/dataset.json e nao fala com a rede nem com o banco.
#
#   scripts/test-dataset-evo.sh
#   CONTORNO_DATASET=/outro/caminho/dataset.json scripts/test-dataset-evo.sh
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATASET="${CONTORNO_DATASET:-$SCRIPT_DIR/../wp-content/plugins/contorno-core/data/dataset.json}"
PHP_BIN="${CONTORNO_PHP_BIN:-php}"

[[ -f "$DATASET" ]] || { echo "[FALHOU] dataset nao encontrado: $DATASET" >&2; exit 1; }

"$PHP_BIN" -r '
$path = $argv[1];
$d = json_decode( file_get_contents( $path ), true );
if ( JSON_ERROR_NONE !== json_last_error() ) { echo "  [FALHOU]   dataset nao e JSON valido\n"; exit( 1 ); }

$ok = 0; $fail = 0;
$t = function ( $nome, $cond, $extra = "" ) use ( &$ok, &$fail ) {
	echo $cond ? "  [OK]       " : "  [FALHOU]   ", $nome, ( "" !== $extra ? "  -> $extra" : "" ), "\n";
	$cond ? $ok++ : $fail++;
};

$PAT = "#/contornodocorpo/(\d+)/site/landing-page/checkout/(\d+)/#i";

echo "\n== Estrutura\n";
$t( "70 unidades", 70 === count( $d["units"] ), count( $d["units"] ) . " encontradas" );
$t( "2 CTNs", 2 === count( $d["ctns"] ), count( $d["ctns"] ) . " encontradas" );

$registros = [];
foreach ( $d["units"] as $u ) { $registros[] = [ "unidade", $u ]; }
foreach ( $d["ctns"] as $c ) { $registros[] = [ "ctn", $c ]; }

echo "\n== idMembership repetido dentro do mesmo registro\n";
$repetidos = [];
foreach ( $registros as [ $tipo, $e ] ) {
	$vistos = [];
	foreach ( (array) ( $e["fields"]["plans"] ?? [] ) as $p ) {
		if ( ! is_array( $p ) ) { continue; }
		$mid = 0;
		if ( "" !== (string) ( $p["evo_membership_id"] ?? "" ) ) { $mid = (int) $p["evo_membership_id"]; }
		elseif ( preg_match( $PAT, (string) ( $p["checkout_url"] ?? "" ), $m ) ) { $mid = (int) $m[2]; }
		if ( ! $mid ) { continue; }
		if ( isset( $vistos[ $mid ] ) ) { $repetidos[] = $e["slug"] . ": \"" . $vistos[ $mid ] . "\" e \"" . ( $p["id"] ?? "?" ) . "\" => $mid"; }
		$vistos[ $mid ] = (string) ( $p["id"] ?? "?" );
	}
}
$t( "nenhum registro com dois planos no mesmo idMembership", [] === $repetidos );
foreach ( $repetidos as $x ) { echo "             ! $x\n"; }

echo "\n== Todo plano tem idMembership\n";
$sem = [];
foreach ( $registros as [ $tipo, $e ] ) {
	foreach ( (array) ( $e["fields"]["plans"] ?? [] ) as $p ) {
		if ( ! is_array( $p ) ) { continue; }
		$tem = "" !== (string) ( $p["evo_membership_id"] ?? "" ) || preg_match( $PAT, (string) ( $p["checkout_url"] ?? "" ) );
		if ( ! $tem ) { $sem[] = $e["slug"] . "/" . ( $p["id"] ?? "?" ); }
	}
}
$t( "nenhum plano sem idMembership", [] === $sem, implode( ", ", array_slice( $sem, 0, 6 ) ) );

echo "\n== Checkout coerente com a filial do registro\n";
$incoerentes = [];
foreach ( $registros as [ $tipo, $e ] ) {
	$branch = (int) ( $e["fields"]["evo_branch_id"] ?? 0 );
	if ( ! $branch ) { continue; }
	foreach ( (array) ( $e["fields"]["plans"] ?? [] ) as $p ) {
		if ( ! is_array( $p ) ) { continue; }
		if ( preg_match( $PAT, (string) ( $p["checkout_url"] ?? "" ), $m ) && (int) $m[1] !== $branch ) {
			$incoerentes[] = $e["slug"] . "/" . ( $p["id"] ?? "?" ) . ": checkout na filial {$m[1]}, registro na $branch";
		}
	}
}
$t( "nenhum checkout apontando para outra filial", [] === $incoerentes );
foreach ( $incoerentes as $x ) { echo "             ! $x\n"; }

echo "\n== Os 5 casos corrigidos (regressao)\n";
$esperado = [
	"para-de-minas" => [ "black" => 5810, "easy" => 4043 ],
	"nova-serrana"  => [ "black" => 5802, "easy" => 583  ],
	"congonhas"     => [ "black" => 5804, "easy" => 584  ],
	"centro"        => [ "black" => 5808, "easy" => 4547 ],
	"veneza"        => [ "black" => 5822, "easy" => 4046 ],
];
foreach ( $esperado as $slug => $planos ) {
	$achado = [];
	foreach ( $d["units"] as $u ) {
		if ( $u["slug"] !== $slug ) { continue; }
		foreach ( (array) ( $u["fields"]["plans"] ?? [] ) as $p ) {
			$id = (string) ( $p["id"] ?? "" );
			if ( ! isset( $planos[ $id ] ) ) { continue; }
			if ( preg_match( $PAT, (string) ( $p["checkout_url"] ?? "" ), $m ) ) { $achado[ $id ] = (int) $m[2]; }
		}
	}
	$bate = $achado === $planos;
	$t( sprintf( "%-16s black=%s easy=%s", $slug, $planos["black"], $planos["easy"] ), $bate,
		$bate ? "" : "achou black=" . ( $achado["black"] ?? "-" ) . " easy=" . ( $achado["easy"] ?? "-" ) );
}

echo "\n== Coordenadas das unidades\n";
$sem_coord = 0; $coords = [];
foreach ( $d["units"] as $u ) {
	$la = $u["fields"]["latitude"] ?? ""; $ln = $u["fields"]["longitude"] ?? "";
	if ( ! is_numeric( $la ) || ! is_numeric( $ln ) || ( 0.0 === (float) $la && 0.0 === (float) $ln ) ) { $sem_coord++; continue; }
	$coords[ sprintf( "%.6f,%.6f", (float) $la, (float) $ln ) ] = true;
}
$t( "70/70 unidades com lat/lng valida", 0 === $sem_coord, "$sem_coord sem" );
$t( "nenhuma coordenada duplicada", 70 === count( $coords ), count( $coords ) . " distintas" );

printf( "\n== Resultado: %d OK, %d falhas\n", $ok, $fail );
exit( $fail > 0 ? 1 : 0 );
' "$DATASET"
