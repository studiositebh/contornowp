#!/usr/bin/env bash
set -euo pipefail

ROOT="${CONTORNO_AUTO_UPDATE_ROOT:-/home/voceconecta/contornowp.voceconecta.com.br}"
BRANCH="${CONTORNO_AUTO_UPDATE_BRANCH:-main}"
LOG_FILE="${CONTORNO_AUTO_UPDATE_LOG:-/home/voceconecta/logs/contorno-auto-update.log}"
LOCK_FILE="${CONTORNO_AUTO_UPDATE_LOCK:-$HOME/tmp/contorno-deploy.lock}"
DEPLOY_SCRIPT="${CONTORNO_AUTO_UPDATE_DEPLOY_SCRIPT:-scripts/cpanel-deploy.sh}"
TEMP_FILES=()

cleanup_temp_files() {
	local file

	for file in "${TEMP_FILES[@]}"; do
		rm -f "$file"
	done
}

trap cleanup_temp_files EXIT

timestamp() {
	date '+%Y-%m-%d %H:%M:%S'
}

log() {
	printf '[%s] [Contorno Auto Update] %s\n' "$(timestamp)" "$*" | tee -a "$LOG_FILE"
}

die() {
	log "ERROR: $*"
	exit 1
}

prepare_paths() {
	mkdir -p "$(dirname "$LOG_FILE")" "$(dirname "$LOCK_FILE")"
}

acquire_lock() {
	exec 9>"$LOCK_FILE"

	if command -v flock >/dev/null 2>&1; then
		if ! flock -n 9; then
			log "Another update is already running. Exiting."
			exit 0
		fi
	else
		die "flock not found; refusing to run without lock support."
	fi
}

current_branch() {
	git rev-parse --abbrev-ref HEAD
}

has_path_intersection() {
	local left_file="$1"
	local right_file="$2"

	[[ -s "$left_file" && -s "$right_file" ]] || return 1

	comm -12 <(sort -u "$left_file") <(sort -u "$right_file") | grep -q .
}

main() {
	prepare_paths
	acquire_lock

	log "Starting auto update"
	cd "$ROOT" || die "Repository root not found: $ROOT"

	[[ -f "$ROOT/wp-config.php" ]] || die "wp-config.php not found in $ROOT. Aborting."
	[[ -d "$ROOT/.git" ]] || die "Git repository not found in $ROOT"

	local branch
	branch="$(current_branch)"
	[[ "$branch" == "$BRANCH" ]] || die "Invalid branch: $branch. Expected: $BRANCH"

	local old_head remote_head new_head
	old_head="$(git rev-parse HEAD)"
	log "Current HEAD: $old_head"

	log "Fetching origin/$BRANCH"
	git fetch origin "$BRANCH"

	remote_head="$(git rev-parse "origin/$BRANCH")"
	log "origin/$BRANCH: $remote_head"

	if [[ "$old_head" == "$remote_head" ]]; then
		log "Already up to date"
		exit 0
	fi

	if ! git merge-base --is-ancestor "$old_head" "origin/$BRANCH"; then
		die "origin/$BRANCH cannot be applied as fast-forward from $old_head"
	fi

	local incoming_paths local_paths
	incoming_paths="$(mktemp)"
	local_paths="$(mktemp)"
	TEMP_FILES+=("$incoming_paths" "$local_paths")

	git diff --name-only "$old_head" "origin/$BRANCH" > "$incoming_paths"
	{
		git diff --name-only
		git diff --name-only --cached
	} > "$local_paths"

	if has_path_intersection "$local_paths" "$incoming_paths"; then
		log "Local changes conflict with incoming paths:"
		comm -12 <(sort -u "$local_paths") <(sort -u "$incoming_paths") | tee -a "$LOG_FILE"
		die "Aborting to preserve local changes."
	fi

	log "Fast-forwarding to origin/$BRANCH"
	git merge --ff-only "origin/$BRANCH"

	new_head="$(git rev-parse HEAD)"
	log "New HEAD: $new_head"
	[[ "$new_head" == "$remote_head" ]] || die "HEAD mismatch after fast-forward: $new_head != $remote_head"

	log "Running deploy: $DEPLOY_SCRIPT"
	bash "$DEPLOY_SCRIPT"
	log "Deploy completed successfully"
	log "COMPLETE"
}

main "$@"
