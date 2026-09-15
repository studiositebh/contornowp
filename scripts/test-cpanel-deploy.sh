#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_SCRIPT="$SCRIPT_DIR/cpanel-deploy.sh"
TMP_ROOT="$(mktemp -d)"
ORIGINAL_PATH="$PATH"

cleanup() {
	rm -rf "$TMP_ROOT"
}
trap cleanup EXIT

fail() {
	echo "[FAIL] $*" >&2
	exit 1
}

assert_contains() {
	local file="$1"
	local expected="$2"

	grep -Fq "$expected" "$file" || fail "Expected '$expected' in $file"
}

make_repo() {
	local repo="$1"

	mkdir -p "$repo/scripts" "$repo/wp-content/plugins/contorno-core" "$repo/wp-content/themes/contorno"
	cp "$SOURCE_SCRIPT" "$repo/scripts/cpanel-deploy.sh"
	chmod +x "$repo/scripts/cpanel-deploy.sh"
	echo "<?php // config" > "$repo/wp-config.php"
	echo "<?php // load" > "$repo/wp-load.php"
	echo "plugin" > "$repo/wp-content/plugins/contorno-core/marker.txt"
	echo "theme" > "$repo/wp-content/themes/contorno/marker.txt"
}

make_target() {
	local target="$1"

	mkdir -p "$target/wp-content/plugins" "$target/wp-content/themes"
	echo "<?php // config" > "$target/wp-config.php"
	echo "<?php // load" > "$target/wp-load.php"
}

make_fake_bin() {
	local bin="$1"
	local mode="${2:-ok}"

	mkdir -p "$bin"
	cat > "$bin/wp" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

args=()
for arg in "$@"; do
	case "$arg" in
		--path=*) ;;
		*) args+=("$arg") ;;
	esac
done

cmd="${args[*]}"
echo "$cmd" >> "${FAKE_WP_LOG:?}"

case "$cmd" in
	"core is-installed") exit 0 ;;
	"plugin is-installed contorno-core") exit 0 ;;
	"plugin is-active contorno-core") exit 0 ;;
	"plugin activate contorno-core") exit 0 ;;
	"theme is-installed contorno") exit 0 ;;
	"theme activate contorno") exit 0 ;;
	"option get stylesheet") echo "contorno"; exit 0 ;;
	"option get home") echo "https://contorno.test"; exit 0 ;;
	"contorno migrate")
		if [[ "${FAKE_WP_FAIL_MIGRATE:-0}" == "1" ]]; then
			exit 23
		fi
		exit 0
		;;
	"contorno status") exit 0 ;;
	"rewrite flush") exit 0 ;;
	"cache flush")
		if [[ "${FAKE_WP_CACHE_FAIL:-0}" == "1" ]]; then
			exit 42
		fi
		exit 0
		;;
esac

echo "Unexpected wp command: $cmd" >&2
exit 99
EOF
	chmod +x "$bin/wp"

	cat > "$bin/curl" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "${FAKE_CURL_LOG:?}"
exit 0
EOF
	chmod +x "$bin/curl"

	if [[ "$mode" == "no-wp" ]]; then
		rm -f "$bin/wp"
	fi
}

run_deploy() {
	local repo="$1"
	local target="$2"
	local out="$3"
	local bin="$4"

	(
		cd "$repo"
		PATH="$bin:$ORIGINAL_PATH" \
		CONTORNO_DEPLOY_TARGET="$target" \
		CONTORNO_DEPLOY_SMOKE_BASE_URL="https://contorno.test" \
		"$repo/scripts/cpanel-deploy.sh"
	) > "$out" 2>&1
}

test_root_equals_target() {
	local repo="$TMP_ROOT/root-equals"
	local bin="$TMP_ROOT/bin-root-equals"
	local out="$TMP_ROOT/root-equals.out"

	make_repo "$repo"
	make_fake_bin "$bin"
	FAKE_WP_LOG="$TMP_ROOT/root-equals.wp.log" FAKE_CURL_LOG="$TMP_ROOT/root-equals.curl.log" run_deploy "$repo" "$repo" "$out" "$bin"

	assert_contains "$out" "Repository root and WordPress target are the same; file sync skipped."
	assert_contains "$out" "[Contorno Deploy] COMPLETE"
}

test_root_diff_target() {
	local repo="$TMP_ROOT/root-diff/repo"
	local target="$TMP_ROOT/root-diff/target"
	local bin="$TMP_ROOT/bin-root-diff"
	local out="$TMP_ROOT/root-diff.out"

	make_repo "$repo"
	make_target "$target"
	make_fake_bin "$bin"
	FAKE_WP_LOG="$TMP_ROOT/root-diff.wp.log" FAKE_CURL_LOG="$TMP_ROOT/root-diff.curl.log" run_deploy "$repo" "$target" "$out" "$bin"

	[[ -f "$target/wp-content/plugins/contorno-core/marker.txt" ]] || fail "Plugin was not synced"
	[[ -f "$target/wp-content/themes/contorno/marker.txt" ]] || fail "Theme was not synced"
	assert_contains "$out" "File sync completed"
}

test_missing_wp_config() {
	local repo="$TMP_ROOT/no-config/repo"
	local target="$TMP_ROOT/no-config/target"
	local bin="$TMP_ROOT/bin-no-config"
	local out="$TMP_ROOT/no-config.out"

	make_repo "$repo"
	make_target "$target"
	rm -f "$target/wp-config.php"
	make_fake_bin "$bin"

	if FAKE_WP_LOG="$TMP_ROOT/no-config.wp.log" FAKE_CURL_LOG="$TMP_ROOT/no-config.curl.log" run_deploy "$repo" "$target" "$out" "$bin"; then
		fail "Deploy should fail without wp-config.php"
	fi
	assert_contains "$out" "wp-config.php not found"
}

test_missing_wp_cli() {
	local repo="$TMP_ROOT/no-wp/repo"
	local target="$TMP_ROOT/no-wp/target"
	local bin="$TMP_ROOT/bin-no-wp"
	local out="$TMP_ROOT/no-wp.out"

	make_repo "$repo"
	make_target "$target"
	make_fake_bin "$bin" "no-wp"

	if FAKE_WP_LOG="$TMP_ROOT/no-wp.wp.log" FAKE_CURL_LOG="$TMP_ROOT/no-wp.curl.log" PATH="$bin:/usr/bin:/bin" CONTORNO_DEPLOY_TARGET="$target" "$repo/scripts/cpanel-deploy.sh" > "$out" 2>&1; then
		fail "Deploy should fail without WP-CLI"
	fi
	assert_contains "$out" "WP-CLI not found"
}

test_migration_failure() {
	local repo="$TMP_ROOT/migration-fail/repo"
	local target="$TMP_ROOT/migration-fail/target"
	local bin="$TMP_ROOT/bin-migration-fail"
	local out="$TMP_ROOT/migration-fail.out"

	make_repo "$repo"
	make_target "$target"
	make_fake_bin "$bin"

	if FAKE_WP_FAIL_MIGRATE=1 FAKE_WP_LOG="$TMP_ROOT/migration-fail.wp.log" FAKE_CURL_LOG="$TMP_ROOT/migration-fail.curl.log" run_deploy "$repo" "$target" "$out" "$bin"; then
		fail "Deploy should fail when migration fails"
	fi
	if grep -Fq "contorno status" "$TMP_ROOT/migration-fail.wp.log"; then
		fail "Status should not run after migration failure"
	fi
}

test_cache_failure_non_critical() {
	local repo="$TMP_ROOT/cache-fail/repo"
	local target="$TMP_ROOT/cache-fail/target"
	local bin="$TMP_ROOT/bin-cache-fail"
	local out="$TMP_ROOT/cache-fail.out"

	make_repo "$repo"
	make_target "$target"
	make_fake_bin "$bin"
	FAKE_WP_CACHE_FAIL=1 FAKE_WP_LOG="$TMP_ROOT/cache-fail.wp.log" FAKE_CURL_LOG="$TMP_ROOT/cache-fail.curl.log" run_deploy "$repo" "$target" "$out" "$bin"

	assert_contains "$out" "Cache flush skipped/unsupported"
	assert_contains "$out" "[Contorno Deploy] COMPLETE"
}

test_root_equals_target
test_root_diff_target
test_missing_wp_config
test_missing_wp_cli
test_migration_failure
test_cache_failure_non_critical

echo "[OK] cpanel deploy tests passed"
