#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/staging/lib.sh
source "$SCRIPT_DIR/lib.sh"

require_command docker
require_command curl
require_command gzip
require_command sha256sum
require_command flock

load_staging_environment
assert_staging_contract

exec 9>"${ROSTA_DEPLOY_LOCK_FILE:-/tmp/rosta-staging-deploy.lock}"
flock -n 9 || fail "Another staging deployment is already running"

release_tag="${ROSTA_RELEASE_TAG:-}"
if [[ -z "$release_tag" ]]; then
  release_tag="$(git -C "$ROSTA_ROOT_DIR" rev-parse HEAD 2>/dev/null || true)"
fi
[[ "$release_tag" =~ ^[0-9a-f]{40}$ ]] || fail "ROSTA_RELEASE_TAG must be an immutable 40-character commit SHA"
export ROSTA_IMAGE_TAG="$release_tag"

failure_report() {
  local exit_code=$?
  log "Deployment failed for release $ROSTA_IMAGE_TAG"
  rosta_compose ps || true
  rosta_compose logs --tail=160 api api-web frontend worker scheduler edge || true
  exit "$exit_code"
}
trap failure_report ERR

build_quality_image() {
  log "Building PHP 8.3 quality image with MySQL, SQLite and Redis extensions"
  docker build \
    --pull \
    --tag rosta-backend-quality:php83 \
    --file "$ROSTA_ROOT_DIR/backend/Dockerfile" \
    "$ROSTA_ROOT_DIR/backend"
}

quality_container() {
  docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$ROSTA_ROOT_DIR:/repo" \
    --workdir /repo/backend \
    rosta-backend-quality:php83 \
    "$@"
}

require_committed_composer_lock() {
  test -s "$ROSTA_ROOT_DIR/backend/composer.lock" \
    || fail "backend/composer.lock is required; deployment never generates dependencies"

  log "Validating and auditing the committed Composer lock"
  quality_container composer validate --strict --no-interaction
  quality_container composer audit --locked --no-interaction
}

run_backend_quality() {
  log "Installing locked backend development dependencies"
  quality_container composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress

  log "Running all backend audits, PHPUnit, Larastan and Pint on PHP 8.3"
  quality_container composer check
}

log "Validating deployment contract"
build_quality_image
require_committed_composer_lock
run_backend_quality
rosta_compose config --quiet

backup_database "pre-${ROSTA_IMAGE_TAG}"

log "Building immutable release images: $ROSTA_IMAGE_TAG"
# The frontend Docker build runs the complete Bun frozen-lock quality chain.
rosta_compose build --pull api api-web frontend

log "Starting MySQL and Redis"
rosta_compose up -d --wait mysql redis

log "Applying forward-only database migrations"
rosta_compose run --rm api php artisan migrate --force --no-interaction

log "Starting API, workers, SSR frontend and TLS edge"
rosta_compose up -d --wait api api-web worker scheduler frontend edge

log "Running staging acceptance"
ROSTA_RELEASE_TAG="$ROSTA_IMAGE_TAG" "$SCRIPT_DIR/acceptance.sh"

record_release_tag "$ROSTA_IMAGE_TAG"
cat > "$ROSTA_STATE_DIR/release-${ROSTA_IMAGE_TAG}.json" <<JSON
{
  "release": "$ROSTA_IMAGE_TAG",
  "deployed_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "site_url": "$VITE_SITE_URL",
  "api_url": "$VITE_API_URL",
  "media_url": "$S3_PUBLIC_URL",
  "composer_lock_sha256": "$(sha256sum "$ROSTA_ROOT_DIR/backend/composer.lock" | awk '{print $1}')"
}
JSON
chmod 600 "$ROSTA_STATE_DIR/release-${ROSTA_IMAGE_TAG}.json"

# Keep tagged rollback images; only remove old dangling build layers.
docker image prune --force --filter "until=168h" >/dev/null || true
trap - ERR
log "Staging deployment accepted: $ROSTA_IMAGE_TAG"
