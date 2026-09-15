#!/usr/bin/env bash
set -euo pipefail

log() {
	echo "[Contorno Deploy] $*"
}

die() {
	echo "[Contorno Deploy] ERROR: $*" >&2
	exit 1
}

real_path() {
	local path="$1"

	if command -v realpath >/dev/null 2>&1; then
		realpath "$path"
		return
	fi

	(cd "$path" && pwd -P)
}

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ROOT="$(real_path "$ROOT")"

candidates=()
if [[ -n "${CONTORNO_DEPLOY_TARGET:-}" ]]; then
	candidates+=("${CONTORNO_DEPLOY_TARGET%/}")
fi
if [[ -n "${DEPLOYPATH:-}" ]]; then
	candidates+=("${DEPLOYPATH%/}")
fi
candidates+=(
	"/home/voceconecta/contornowp.voceconecta.com.br"
	"/home/voceconecta/domains/contornowp.voceconecta.com.br/public_html"
	"/home/voceconecta/public_html/contornowp.voceconecta.com.br"
	"/home/voceconecta/public_html"
)

target=""
for candidate in "${candidates[@]}"; do
	if [[ -d "$candidate/wp-content" ]]; then
		target="$(real_path "$candidate")"
		break
	fi
done

if [[ -z "$target" ]]; then
	die "WordPress target directory not found."
fi

log "Root: $ROOT"
log "Target: $target"

sync_dir() {
	local source_dir="$1"
	local target_dir="$2"
	local source_real
	local target_real

	[[ -d "$source_dir" ]] || die "Source directory not found: $source_dir"

	mkdir -p "$target_dir"
	source_real="$(real_path "$source_dir")"
	target_real="$(real_path "$target_dir")"

	if [[ "$source_real" == "$target_real" ]]; then
		log "File sync skipped for $source_real; source and target are the same."
		return
	fi

	if command -v rsync >/dev/null 2>&1; then
		rsync -a --delete "$source_real/" "$target_real/"
		return
	fi

	find "$target_real" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
	cp -a "$source_real/." "$target_real/"
}

if [[ "$ROOT" == "$target" ]]; then
	log "Repository root and WordPress target are the same; file sync skipped."
else
	sync_dir "$ROOT/wp-content/plugins/contorno-core" "$target/wp-content/plugins/contorno-core"
	sync_dir "$ROOT/wp-content/themes/contorno" "$target/wp-content/themes/contorno"
	log "File sync completed"
fi

[[ -f "$target/wp-config.php" ]] || die "wp-config.php not found in target. Aborting without creating WordPress."
[[ -f "$target/wp-load.php" ]] || die "wp-load.php not found in target."
command -v wp >/dev/null 2>&1 || die "WP-CLI not found in PATH."

wp --path="$target" core is-installed
log "WordPress OK"

wp --path="$target" plugin is-installed contorno-core
log "Contorno Core OK"

if ! wp --path="$target" plugin is-active contorno-core >/dev/null 2>&1; then
	wp --path="$target" plugin activate contorno-core
fi

wp --path="$target" theme is-installed contorno
if [[ "$(wp --path="$target" option get stylesheet)" != "contorno" ]]; then
	wp --path="$target" theme activate contorno
fi

wp --path="$target" contorno migrate
wp --path="$target" contorno status
log "Migration OK"

wp --path="$target" rewrite flush
log "Rewrite OK"

if wp --path="$target" cache flush; then
	log "Cache flushed"
else
	log "Cache flush skipped/unsupported"
fi

command -v curl >/dev/null 2>&1 || die "curl not found in PATH."

base_url="${CONTORNO_DEPLOY_SMOKE_BASE_URL:-$(wp --path="$target" option get home)}"
base_url="${base_url%/}"
smoke_paths=(
	"/"
	"/unidades/"
	"/unidades/alfenas/"
	"/ctn/"
	"/ctn/castelo/"
	"/planos/"
	"/matricula/"
)

for smoke_path in "${smoke_paths[@]}"; do
	curl --fail --silent --show-error --location --max-time 20 --output /dev/null "${base_url}${smoke_path}"
done
log "Smoke OK"

log "COMPLETE"
