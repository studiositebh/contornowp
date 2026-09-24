#!/usr/bin/env bash
# Catalogo central de atributos das unidades: catalogo, migracao e render.
#
# Prova, sem banco e sem rede, que a troca de "textarea com um item por linha"
# por "checkboxes ligados a um catalogo" nao perde nem inventa informacao:
# todos os valores das 70 unidades do dataset tem destino, a chave sobrevive a
# renomear, a ordem de cada unidade e preservada e a migracao e idempotente.
#
#   scripts/test-attributes.sh
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$SCRIPT_DIR/../wp-content/plugins/contorno-core"
PHP_BIN="${CONTORNO_PHP_BIN:-php}"

[[ -f "$PLUGIN/data/dataset.json" ]] || { echo "[FALHOU] dataset nao encontrado" >&2; exit 1; }
[[ -f "$PLUGIN/data/attributes.json" ]] || { echo "[FALHOU] catalogo nao encontrado" >&2; exit 1; }

"$PHP_BIN" "$SCRIPT_DIR/test-attributes.php" "$PLUGIN"
