#!/usr/bin/env bash
set -Eeuo pipefail

HISTORICAL_PS12_SHA="4a54780d504b91527a86777e7f04368022354686"

CONFIG_FILE="${1:-/etc/rosta/staging/server-entry.env}"

fail() {
  printf '[rosta-fastpath] ERROR: %s\n' "$*" >&2
  exit 1
}

log() {
  printf '[rosta-fastpath] %s\n' "$*"
}

for command in docker git curl python3 sha256sum; do
  command -v "$command" >/dev/null 2>&1 || fail "Missing command: $command"
done

[[ -f "$CONFIG_FILE" ]] || fail "Missing server-entry config: $CONFIG_FILE"

# shellcheck disable=SC1090
set -a
source "$CONFIG_FILE"
set +a

: "${ROSTA_RELEASE_SHA:?ROSTA_RELEASE_SHA is required}"
: "${ROSTA_RELEASE_TAG:?ROSTA_RELEASE_TAG is required}"
: "${ROSTA_API_REMOTE_IMAGE:?ROSTA_API_REMOTE_IMAGE is required}"
: "${ROSTA_API_WEB_REMOTE_IMAGE:?ROSTA_API_WEB_REMOTE_IMAGE is required}"
: "${ROSTA_FRONTEND_REMOTE_IMAGE:?ROSTA_FRONTEND_REMOTE_IMAGE is required}"

[[ "$ROSTA_RELEASE_SHA" =~ ^[0-9a-f]{40}$ ]]   || fail "ROSTA_RELEASE_SHA must be an exact 40-character lowercase commit SHA"

[[ "$ROSTA_RELEASE_TAG" =~ ^rosta-server-ready-[0-9]{4}-[0-9]{2}-[0-9]{2}([._-][a-zA-Z0-9._-]+)?$ ]]   || fail "ROSTA_RELEASE_TAG must be an immutable rosta-server-ready-* tag"

ROOT_DIR="${ROSTA_ROOT_DIR:-/srv/rosta}"
[[ -d "$ROOT_DIR/.git" ]] || fail "Expected git repository at $ROOT_DIR"

log "Fetching immutable release identity"
git -C "$ROOT_DIR" fetch --force --tags origin

test "$(git -C "$ROOT_DIR" rev-list -n 1 "$ROSTA_RELEASE_TAG")" = "$ROSTA_RELEASE_SHA"   || fail "Server-ready tag does not resolve to the requested exact SHA"

git -C "$ROOT_DIR" merge-base --is-ancestor "$HISTORICAL_PS12_SHA" "$ROSTA_RELEASE_SHA"   || fail "Server-ready release is not a descendant of the frozen PS12 source"

git -C "$ROOT_DIR" checkout --detach "$ROSTA_RELEASE_SHA"
test "$(git -C "$ROOT_DIR" rev-parse HEAD)" = "$ROSTA_RELEASE_SHA"
test -z "$(git -C "$ROOT_DIR" status --porcelain --untracked-files=all)"   || fail "Server release worktree is not clean"

export ROSTA_ROOT_DIR="$ROOT_DIR"
export ROSTA_IMAGE_TAG="$ROSTA_RELEASE_SHA"
export ROSTA_FRONTEND_ENV_PATH="${ROSTA_FRONTEND_ENV_PATH:-/etc/rosta/staging/frontend.env}"
export ROSTA_BACKEND_ENV_PATH="${ROSTA_BACKEND_ENV_PATH:-/etc/rosta/staging/backend.env}"
export ROSTA_STATE_DIR="${ROSTA_STATE_DIR:-/var/lib/rosta/staging}"

if [[ -n "${GHCR_TOKEN:-}" ]]; then
  : "${GHCR_USERNAME:?GHCR_USERNAME is required when GHCR_TOKEN is set}"
  log "Authenticating to GHCR with stdin-only credential"
  printf '%s' "$GHCR_TOKEN"     | docker login ghcr.io -u "$GHCR_USERNAME" --password-stdin >/dev/null
fi

log "Pulling immutable prebuilt application images"
docker pull "$ROSTA_API_REMOTE_IMAGE"
docker pull "$ROSTA_API_WEB_REMOTE_IMAGE"
docker pull "$ROSTA_FRONTEND_REMOTE_IMAGE"

log "Tagging remote images to the exact local compose identity"
docker tag "$ROSTA_API_REMOTE_IMAGE" "rosta-api:$ROSTA_IMAGE_TAG"
docker tag "$ROSTA_API_WEB_REMOTE_IMAGE" "rosta-api-web:$ROSTA_IMAGE_TAG"
docker tag "$ROSTA_FRONTEND_REMOTE_IMAGE" "rosta-frontend:$ROSTA_IMAGE_TAG"

log "Pulling pinned runtime dependencies used by the staging compose contract"
docker pull mysql:8.4
docker pull redis:7.4-alpine
docker pull caddy:2-alpine

SCRIPT_DIR="$ROOT_DIR/deploy/staging"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"
load_staging_environment
assert_staging_contract

# Frozen staging examples contain bootstrap image tags. After loading env files,
# Compose is re-locked to the exact locally accepted server-ready release.
export ROSTA_IMAGE_TAG="$ROSTA_RELEASE_SHA"

log "Validating staging compose contract without building"
rosta_compose config --quiet

mkdir -p "$ROSTA_STATE_DIR/reports/fastpath"
chmod 700 "$ROSTA_STATE_DIR/reports/fastpath"

{
  echo "release_sha=$ROSTA_RELEASE_SHA"
  echo "release_tag=$ROSTA_RELEASE_TAG"
  echo "historical_ps12_ancestor=$HISTORICAL_PS12_SHA"
  echo "api_remote=$ROSTA_API_REMOTE_IMAGE"
  echo "api_web_remote=$ROSTA_API_WEB_REMOTE_IMAGE"
  echo "frontend_remote=$ROSTA_FRONTEND_REMOTE_IMAGE"
  echo "api_local=$(docker image inspect "rosta-api:$ROSTA_IMAGE_TAG" --format '{{.Id}}')"
  echo "api_web_local=$(docker image inspect "rosta-api-web:$ROSTA_IMAGE_TAG" --format '{{.Id}}')"
  echo "frontend_local=$(docker image inspect "rosta-frontend:$ROSTA_IMAGE_TAG" --format '{{.Id}}')"
} > "$ROSTA_STATE_DIR/reports/fastpath/image-identities.txt"
chmod 600 "$ROSTA_STATE_DIR/reports/fastpath/image-identities.txt"

backup_database "pre-$ROSTA_IMAGE_TAG"

log "Starting MySQL and Redis from pre-pulled images"
rosta_compose up -d --no-build --pull never --wait mysql redis

log "Applying forward-only Laravel migrations"
rosta_compose run --rm --no-build --pull never api   php artisan migrate --force --no-interaction

log "Starting API, web, worker, scheduler, SSR frontend and Caddy without builds"
rosta_compose up -d --no-build --pull never --wait   api api-web worker scheduler frontend edge

log "Running real staging acceptance"
ROSTA_RELEASE_TAG="$ROSTA_RELEASE_SHA" "$SCRIPT_DIR/acceptance.sh"

python3 - "$ROSTA_STATE_DIR/reports/latest.json" "$ROSTA_RELEASE_SHA" <<'PY'
import json
import sys

path, expected = sys.argv[1:]
with open(path, encoding="utf-8") as handle:
    payload = json.load(handle)

if payload.get("accepted") is not True:
    raise SystemExit("latest acceptance is not accepted=true")
if payload.get("release") != expected:
    raise SystemExit(
        f"acceptance release mismatch: {payload.get('release')} != {expected}"
    )
PY

record_release_tag "$ROSTA_RELEASE_SHA"

{
  echo "captured_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  free -h || true
  echo
  docker stats --no-stream || true
  echo
  rosta_compose ps || true
} > "$ROSTA_STATE_DIR/reports/fastpath/runtime-snapshot.txt"
chmod 600 "$ROSTA_STATE_DIR/reports/fastpath/runtime-snapshot.txt"

sha256sum   "$ROSTA_STATE_DIR/reports/fastpath/image-identities.txt"   "$ROSTA_STATE_DIR/reports/fastpath/runtime-snapshot.txt"   "$ROSTA_STATE_DIR/reports/latest.json"   > "$ROSTA_STATE_DIR/reports/fastpath/evidence.sha256"

log "Fast-path deployment accepted"
echo "staging_runtime_accepted=ready"
