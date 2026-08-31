#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/production/lib.sh
source "$SCRIPT_DIR/lib.sh"

[[ "${ROSTA_CONFIRM_PRODUCTION_ROLLBACK:-}" == "yes" ]] \
  || fail "Set ROSTA_CONFIRM_PRODUCTION_ROLLBACK=yes for an intentional image rollback"

for command in docker curl python3; do
  require_command "$command"
done

load_production_environment
assert_production_environment_contract

current="$(current_release_tag)"
previous="$(previous_release_tag)"
assert_release_tag "$current"
assert_release_tag "$previous"
[[ "$current" != "$previous" ]] || fail "Current and previous release SHAs must differ"

for image in \
  "rosta-api:$previous" \
  "rosta-api-web:$previous" \
  "rosta-frontend:$previous"; do
  docker image inspect "$image" >/dev/null 2>&1 || fail "Rollback image is unavailable: $image"
done

log "Rolling application images back to $previous; database migrations are never rolled back automatically"
export ROSTA_IMAGE_TAG="$previous"
rosta_compose up -d --no-build --wait api api-web worker scheduler frontend edge

ROSTA_ACCEPTANCE_RELEASE_TAG="$previous" "$SCRIPT_DIR/acceptance.sh"

printf '%s\n' "$current" > "$ROSTA_STATE_DIR/previous"
printf '%s\n' "$previous" > "$ROSTA_STATE_DIR/current"
chmod 600 "$ROSTA_STATE_DIR/current" "$ROSTA_STATE_DIR/previous"
log "Image rollback accepted: $previous"
