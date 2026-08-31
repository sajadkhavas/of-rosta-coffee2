#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/production/lib.sh
source "$SCRIPT_DIR/lib.sh"

[[ "${ROSTA_CONFIRM_PRODUCTION_DEPLOY:-}" == "yes" ]] \
  || fail "Set ROSTA_CONFIRM_PRODUCTION_DEPLOY=yes for an intentional production cutover"

for command in git docker gzip sha256sum curl python3; do
  require_command "$command"
done

docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required"

load_production_environment
assert_production_contract
"$SCRIPT_DIR/preflight.sh"

current="$(current_release_tag)"
if [[ "$current" == "$ROSTA_IMAGE_TAG" ]]; then
  fail "Release $ROSTA_IMAGE_TAG is already recorded as current"
fi

if rosta_compose ps --status running mysql 2>/dev/null | grep -q mysql; then
  backup_database pre-deploy >/dev/null
fi

log "Building immutable release images for $ROSTA_IMAGE_TAG"
rosta_compose build --pull api api-web frontend

for image in \
  "rosta-api:$ROSTA_IMAGE_TAG" \
  "rosta-api-web:$ROSTA_IMAGE_TAG" \
  "rosta-frontend:$ROSTA_IMAGE_TAG"; do
  docker image inspect "$image" >/dev/null 2>&1 || fail "Missing built image $image"
done

log "Starting stateful dependencies"
rosta_compose up -d --wait mysql redis

log "Applying forward-only database migrations"
rosta_compose run --rm api php artisan migrate --force --no-interaction

log "Activating application services"
rosta_compose up -d --wait api api-web worker scheduler frontend edge

log "Running fail-closed production acceptance for candidate $ROSTA_IMAGE_TAG"
ROSTA_ACCEPTANCE_RELEASE_TAG="$ROSTA_IMAGE_TAG" "$SCRIPT_DIR/acceptance.sh"

record_release_tag "$ROSTA_IMAGE_TAG"
log "Production release accepted and recorded: $ROSTA_IMAGE_TAG"
