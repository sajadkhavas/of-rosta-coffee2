#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/staging/lib.sh
source "$SCRIPT_DIR/lib.sh"

require_command docker
require_command curl
require_command flock

load_staging_environment
assert_staging_contract

exec 9>"${ROSTA_DEPLOY_LOCK_FILE:-/tmp/rosta-staging-deploy.lock}"
flock -n 9 || fail "Another staging deployment or rollback is running"

current="$(current_release_tag)"
target="${1:-$(previous_release_tag)}"
[[ -n "$current" ]] || fail "No current release is recorded"
[[ -n "$target" ]] || fail "No previous release is recorded; pass an explicit release tag"
[[ "$target" =~ ^[A-Za-z0-9._-]{7,64}$ ]] || fail "Invalid rollback release tag"
[[ "$target" != "$current" ]] || fail "Target release is already current"

for image in rosta-api rosta-api-web rosta-frontend; do
  docker image inspect "$image:$target" >/dev/null 2>&1 \
    || fail "Rollback image is missing: $image:$target"
done

backup_database "pre-rollback-${current}-to-${target}"

log "Switching staging images from $current to $target"
export ROSTA_IMAGE_TAG="$target"
rosta_compose up -d --no-build --wait api api-web worker scheduler frontend edge

if ! ROSTA_RELEASE_TAG="$target" "$SCRIPT_DIR/acceptance.sh"; then
  log "Rollback candidate failed acceptance; restoring current release $current"
  export ROSTA_IMAGE_TAG="$current"
  rosta_compose up -d --no-build --wait api api-web worker scheduler frontend edge
  ROSTA_RELEASE_TAG="$current" "$SCRIPT_DIR/acceptance.sh" || true
  fail "Rollback candidate $target was rejected"
fi

printf '%s\n' "$target" > "$ROSTA_STATE_DIR/current"
printf '%s\n' "$current" > "$ROSTA_STATE_DIR/previous"
log "Rollback accepted. Current release: $target; previous release: $current"
