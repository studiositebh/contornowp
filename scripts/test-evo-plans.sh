#!/usr/bin/env bash
# Planos e agenda acompanhando a EVO: preco, substituto de plano inativado,
# contratos internos fora do site, lotes do cron e agenda automatica.
#
# Roda o codigo REAL com stubs do WordPress (test-evo-plans.php). Nao toca
# rede, banco nem staging.
#
#   scripts/test-evo-plans.sh
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR/.."
EVO="$ROOT/wp-content/plugins/contorno-evo-sync"
PHP_BIN="${CONTORNO_PHP_BIN:-php}"

fail=0
for f in "$EVO"/includes/class-{sync,frontend,cron,settings,admin,mapping}.php "$ROOT/wp-content/plugins/contorno-core/includes/data/evo.php"; do
	"$PHP_BIN" -l "$f" >/dev/null || { echo "  [FALHOU]   sintaxe: $f" >&2; fail=1; }
done

if grep -rq "'add_new_plans'" "$EVO/includes"; then
	echo "  [FALHOU]   add_new_plans ainda e lido (true no banco reativaria contratos internos)" >&2
	fail=1
fi

"$PHP_BIN" "$SCRIPT_DIR/test-evo-plans.php" "$EVO" || fail=1

exit $fail
