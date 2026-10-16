#!/usr/bin/env bash
# Grade nativa de aulas coletivas: parser, fila do cron e orcamento de chamadas.
#
# Roda o codigo REAL de class-schedule.php com stubs do WordPress e um cliente
# EVO falso (test-evo-schedule.php). Nao toca rede, banco nem staging.
# Alem disso, verificacoes ESTATICAS que so fazem sentido no repositorio.
#
#   scripts/test-evo-schedule.sh
#   CONTORNO_SCHEDULE_SAMPLE=/tmp/evo-schedule-sample.json scripts/test-evo-schedule.sh
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR/.."
EVO="$ROOT/wp-content/plugins/contorno-evo-sync"
CORE="$ROOT/wp-content/plugins/contorno-core"
PHP_BIN="${CONTORNO_PHP_BIN:-php}"

fail=0
ok()  { printf '  [OK]       %s\n' "$*"; }
bad() { printf '  [FALHOU]   %s\n' "$*" >&2; fail=1; }

echo "Estatico"

if grep -rqi "instructorPhoto" "$CORE"; then
	bad "contorno-core nao pode usar instructorPhoto (URL assinada que expira)"
else
	ok "contorno-core nao usa instructorPhoto"
fi

if grep -rq "Contorno_Evo_Activities\|Contorno_Evo_Client\|Contorno_Evo_Schedule::refresh" "$CORE"; then
	bad "a renderizacao (contorno-core) nao pode chamar a API da EVO"
else
	ok "renderizacao so le cache (Contorno_Evo_Schedule::week)"
fi

if grep -q "<iframe" "$CORE/includes/shortcodes/units.php" && grep -q "contorno_schedule_url" "$CORE/includes/shortcodes/units.php"; then
	ok "iframe do EVO Totem continua como fallback"
else
	bad "iframe do EVO Totem sumiu do contorno_classes"
fi

if grep -q "'classes_mode'" "$CORE/includes/meta/registry.php"; then
	ok "opcao por unidade para forcar o iframe"
else
	bad "campo classes_mode ausente"
fi

for f in "$EVO/includes/class-schedule.php" "$CORE/includes/shortcodes/units.php" "$CORE/includes/data/evo.php"; do
	"$PHP_BIN" -l "$f" >/dev/null || bad "sintaxe: $f"
done

echo
"$PHP_BIN" "$SCRIPT_DIR/test-evo-schedule.php" "$EVO" || fail=1

exit $fail
