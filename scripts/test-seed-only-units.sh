#!/usr/bin/env bash
# Dataset so semeia unidades novas; edicoes do painel e vinculos EVO sobrevivem.
# Sem banco, sem rede, sem tocar em producao.   scripts/test-seed-only-units.sh
set -uo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"${CONTORNO_PHP_BIN:-php}" "$SCRIPT_DIR/test-seed-only-units.php"
