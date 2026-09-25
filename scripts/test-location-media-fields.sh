#!/usr/bin/env bash
# Mascaras/normalizacao de CEP, telefone/WhatsApp, coordenadas e galeria
# (rodada de UX de Localizacao e Midia do editor de Unidade).
#
# Prova, sem banco e sem rede, que contorno_sanitize_field() normaliza os
# novos tipos de campo (cep, phone, coordinate, media_list) do jeito que o
# painel e o importador esperam, sem apagar dado malformado silenciosamente
# e sem aceitar referencia arbitraria de attachment na galeria.
#
#   scripts/test-location-media-fields.sh
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$SCRIPT_DIR/../wp-content/plugins/contorno-core"
PHP_BIN="${CONTORNO_PHP_BIN:-php}"

[[ -f "$PLUGIN/includes/meta/fields.php" ]] || { echo "[FALHOU] fields.php nao encontrado" >&2; exit 1; }

"$PHP_BIN" "$SCRIPT_DIR/test-location-media-fields.php" "$PLUGIN"
