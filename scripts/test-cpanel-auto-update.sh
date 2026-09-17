#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_SCRIPT="$SCRIPT_DIR/cpanel-auto-update.sh"
TMP_ROOT="$(mktemp -d)"
ORIGINAL_PATH="$PATH"
FAKE_BIN="$TMP_ROOT/bin"

cleanup() {
	rm -rf "$TMP_ROOT"
}
trap cleanup EXIT

mkdir -p "$FAKE_BIN"
cat > "$FAKE_BIN/flock" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

if [[ "${FAKE_FLOCK_BUSY:-0}" == "1" ]]; then
	exit 1
fi

exit 0
EOF
chmod +x "$FAKE_BIN/flock"

fail() {
	echo "[FAIL] $*" >&2
	exit 1
}

assert_contains() {
	local file="$1"
	local expected="$2"

	grep -Fq "$expected" "$file" || fail "Expected '$expected' in $file"
}

git_config() {
	git config user.email "contorno-tests@example.com"
	git config user.name "Contorno Tests"
}

commit_file() {
	local file="$1"
	local content="$2"
	local message="$3"

	mkdir -p "$(dirname "$file")"
	printf '%s\n' "$content" > "$file"
	git add "$file"
	git commit -m "$message" >/dev/null
}

make_deploy() {
	local repo="$1"

	mkdir -p "$repo/scripts"
	cp "$SOURCE_SCRIPT" "$repo/scripts/cpanel-auto-update.sh"
	chmod +x "$repo/scripts/cpanel-auto-update.sh"
	cat > "$repo/scripts/cpanel-deploy.sh" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
echo "deploy" >> "${FAKE_DEPLOY_LOG:?}"
if [[ "${FAKE_DEPLOY_FAIL:-0}" == "1" ]]; then
	exit 27
fi
exit 0
EOF
	chmod +x "$repo/scripts/cpanel-deploy.sh"
}

make_fixture() {
	local name="$1"
	local remote="$TMP_ROOT/$name/origin.git"
	local seed="$TMP_ROOT/$name/seed"
	local repo="$TMP_ROOT/$name/repo"

	mkdir -p "$TMP_ROOT/$name"
	git init --bare "$remote" >/dev/null
	git clone "$remote" "$seed" >/dev/null 2>&1
	(
		cd "$seed"
		git_config
		git checkout -b main >/dev/null
		commit_file "tracked.txt" "v1" "initial"
		git push -u origin main >/dev/null 2>&1
	)
	git clone "$remote" "$repo" >/dev/null 2>&1
	(
		cd "$repo"
		git checkout main >/dev/null
		git_config
		make_deploy "$repo"
		echo "<?php // config" > wp-config.php
	)

	printf '%s\n' "$remote|$seed|$repo"
}

run_auto_update() {
	local repo="$1"
	local out="$2"
	local lock="${3:-$TMP_ROOT/locks/contorno-deploy.lock}"
	local log="${4:-$TMP_ROOT/logs/contorno-auto-update.log}"

	(
		cd "$repo"
		PATH="$FAKE_BIN:$ORIGINAL_PATH" \
		CONTORNO_AUTO_UPDATE_ROOT="$repo" \
		CONTORNO_AUTO_UPDATE_LOG="$log" \
		CONTORNO_AUTO_UPDATE_LOCK="$lock" \
		FAKE_DEPLOY_LOG="$TMP_ROOT/deploy.log" \
		"$repo/scripts/cpanel-auto-update.sh"
	) > "$out" 2>&1
}

remote_commit() {
	local seed="$1"
	local file="$2"
	local content="$3"
	local message="$4"

	(
		cd "$seed"
		commit_file "$file" "$content" "$message"
		git push origin main >/dev/null 2>&1
	)
}

test_already_up_to_date() {
	local fixture remote seed repo out
	fixture="$(make_fixture already-up-to-date)"
	IFS='|' read -r remote seed repo <<< "$fixture"
	out="$TMP_ROOT/already-up-to-date.out"

	run_auto_update "$repo" "$out"
	assert_contains "$out" "Already up to date"

	if [[ -f "$TMP_ROOT/deploy.log" ]]; then
		fail "Deploy should not run when already up to date"
	fi
}

test_fast_forward() {
	local fixture remote seed repo out old_head new_head
	fixture="$(make_fixture fast-forward)"
	IFS='|' read -r remote seed repo <<< "$fixture"
	out="$TMP_ROOT/fast-forward.out"

	remote_commit "$seed" "tracked.txt" "v2" "remote update"
	old_head="$(git -C "$repo" rev-parse HEAD)"
	run_auto_update "$repo" "$out"
	new_head="$(git -C "$repo" rev-parse HEAD)"

	[[ "$old_head" != "$new_head" ]] || fail "HEAD did not advance"
	[[ "$(git -C "$repo" rev-parse HEAD)" == "$(git -C "$repo" rev-parse origin/main)" ]] || fail "HEAD did not match origin/main"
	assert_contains "$out" "Deploy completed successfully"
	assert_contains "$TMP_ROOT/deploy.log" "deploy"
}

test_divergent_branch() {
	local fixture remote seed repo out
	fixture="$(make_fixture divergent)"
	IFS='|' read -r remote seed repo <<< "$fixture"
	out="$TMP_ROOT/divergent.out"

	remote_commit "$seed" "remote.txt" "remote" "remote update"
	(
		cd "$repo"
		commit_file "local.txt" "local" "local update"
	)

	if run_auto_update "$repo" "$out"; then
		fail "Divergent branch should fail"
	fi
	assert_contains "$out" "cannot be applied as fast-forward"
}

test_local_change_without_conflict() {
	local fixture remote seed repo out
	fixture="$(make_fixture local-clean)"
	IFS='|' read -r remote seed repo <<< "$fixture"
	out="$TMP_ROOT/local-clean.out"

	remote_commit "$seed" "tracked.txt" "v2" "remote update"
	printf '%s\n' "runtime" > "$repo/runtime-local.txt"

	run_auto_update "$repo" "$out"
	assert_contains "$out" "Deploy completed successfully"
	[[ "$(cat "$repo/runtime-local.txt")" == "runtime" ]] || fail "Unrelated local file changed"
}

test_local_change_with_conflict() {
	local fixture remote seed repo out
	fixture="$(make_fixture local-conflict)"
	IFS='|' read -r remote seed repo <<< "$fixture"
	out="$TMP_ROOT/local-conflict.out"

	remote_commit "$seed" "tracked.txt" "v2" "remote update"
	printf '%s\n' "local edit" > "$repo/tracked.txt"

	if run_auto_update "$repo" "$out"; then
		fail "Conflicting local change should fail"
	fi
	assert_contains "$out" "Local changes conflict with incoming paths"
}

test_deploy_failure() {
	local fixture remote seed repo out
	fixture="$(make_fixture deploy-failure)"
	IFS='|' read -r remote seed repo <<< "$fixture"
	out="$TMP_ROOT/deploy-failure.out"

	remote_commit "$seed" "tracked.txt" "v2" "remote update"
	if (
		cd "$repo"
		PATH="$FAKE_BIN:$ORIGINAL_PATH" \
		CONTORNO_AUTO_UPDATE_ROOT="$repo" \
		CONTORNO_AUTO_UPDATE_LOG="$TMP_ROOT/logs/deploy-failure.log" \
		CONTORNO_AUTO_UPDATE_LOCK="$TMP_ROOT/locks/deploy-failure.lock" \
		FAKE_DEPLOY_LOG="$TMP_ROOT/deploy-failure.deploy.log" \
		FAKE_DEPLOY_FAIL=1 \
		"$repo/scripts/cpanel-auto-update.sh"
	) > "$out" 2>&1; then
		fail "Deploy failure should return non-zero"
	fi
	assert_contains "$TMP_ROOT/deploy-failure.deploy.log" "deploy"
}

test_lock_already_occupied() {
	local fixture remote seed repo out lock
	fixture="$(make_fixture locked)"
	IFS='|' read -r remote seed repo <<< "$fixture"
	out="$TMP_ROOT/locked.out"
	lock="$TMP_ROOT/locks/locked.lock"

	FAKE_FLOCK_BUSY=1 run_auto_update "$repo" "$out" "$lock"

	assert_contains "$out" "Another update is already running"
}

test_already_up_to_date
test_fast_forward
test_divergent_branch
test_local_change_without_conflict
test_local_change_with_conflict
test_deploy_failure
test_lock_already_occupied

echo "[OK] cpanel auto update tests passed"
