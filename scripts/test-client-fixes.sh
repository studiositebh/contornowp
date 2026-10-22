#!/usr/bin/env bash
# Planos (Ver mais/centralizacao), blog sem cards ficticios e link direto do mapa.
# Sem banco, sem rede.   scripts/test-client-fixes.sh
set -uo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"${CONTORNO_PHP_BIN:-php}" "$SCRIPT_DIR/test-client-fixes.php"
