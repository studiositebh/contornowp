#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

candidates=()
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
		target="$candidate"
		break
	fi
done

if [[ -z "$target" ]]; then
	echo "WordPress target directory not found." >&2
	exit 1
fi

sync_dir() {
	local source_dir="$1"
	local target_dir="$2"

	mkdir -p "$target_dir"
	if command -v rsync >/dev/null 2>&1; then
		rsync -a --delete "$source_dir/" "$target_dir/"
		return
	fi

	find "$target_dir" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
	cp -a "$source_dir/." "$target_dir/"
}

sync_dir "$ROOT/wp-content/plugins/contorno-core" "$target/wp-content/plugins/contorno-core"
sync_dir "$ROOT/wp-content/themes/contorno" "$target/wp-content/themes/contorno"

if command -v wp >/dev/null 2>&1 && [[ -f "$target/wp-load.php" ]]; then
	wp --path="$target" cache flush || true
	wp --path="$target" transient delete --all || true
fi

echo "Deployed Contorno theme and core plugin to $target"
