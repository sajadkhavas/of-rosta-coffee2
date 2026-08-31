#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.rehearsal.yml"

[[ "${ROSTA_ALLOW_REHEARSAL:-}" == "true" ]] \
  || { printf '[ps7-rehearsal] ERROR: set ROSTA_ALLOW_REHEARSAL=true to run the isolated rehearsal\n' >&2; exit 1; }

for command in docker curl grep python3; do
  command -v "$command" >/dev/null 2>&1 \
    || { printf '[ps7-rehearsal] ERROR: missing command: %s\n' "$command" >&2; exit 1; }
done
docker compose version >/dev/null 2>&1

export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-rosta-ps7-rehearsal-${GITHUB_RUN_ID:-$$}}"
export ROSTA_REHEARSAL_PORT="${ROSTA_REHEARSAL_PORT:-18080}"
BASE_URL="http://127.0.0.1:${ROSTA_REHEARSAL_PORT}"
TMP_DIR="$(mktemp -d)"

cleanup() {
  docker compose -f "$COMPOSE_FILE" down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

printf '[ps7-rehearsal] validating isolated compose topology\n'
docker compose -f "$COMPOSE_FILE" config --quiet

docker run --rm \
  -v "$SCRIPT_DIR/Caddyfile.rehearsal:/etc/caddy/Caddyfile:ro" \
  caddy:2.10.2-alpine \
  caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null

printf '[ps7-rehearsal] starting disposable frontend/api/edge topology\n'
docker compose -f "$COMPOSE_FILE" up -d --wait

curl --fail --silent --show-error --max-time 15 \
  --dump-header "$TMP_DIR/home.headers" "$BASE_URL/" > "$TMP_DIR/home.html"
curl --fail --silent --show-error --max-time 15 \
  --dump-header "$TMP_DIR/robots.headers" "$BASE_URL/robots.txt" > "$TMP_DIR/robots.txt"
curl --fail --silent --show-error --max-time 15 \
  --dump-header "$TMP_DIR/private.headers" "$BASE_URL/hub" > "$TMP_DIR/private.html"
curl --fail --silent --show-error --max-time 15 \
  --dump-header "$TMP_DIR/api.headers" "$BASE_URL/api/v1/health/live" > "$TMP_DIR/api.json"

grep -q 'rosta-production-rehearsal' "$TMP_DIR/home.html"
grep -Eiq '^content-security-policy:' "$TMP_DIR/home.headers"
grep -Eiq '^x-content-type-options:[[:space:]]*nosniff' "$TMP_DIR/home.headers"
grep -Eiq '^x-frame-options:[[:space:]]*DENY' "$TMP_DIR/home.headers"
grep -Eiq '^cache-control:[[:space:]]*private, no-store' "$TMP_DIR/private.headers"
grep -Eiq '^x-robots-tag:[[:space:]]*noindex' "$TMP_DIR/private.headers"
grep -Eiq '^cache-control:[[:space:]]*no-store' "$TMP_DIR/api.headers"

if grep -Eiq 'disallow[[:space:]]*:[[:space:]]*/([[:space:]]|$)' "$TMP_DIR/robots.txt"; then
  printf '[ps7-rehearsal] ERROR: rehearsal robots unexpectedly blocks the root\n' >&2
  exit 1
fi

python3 - "$TMP_DIR/api.json" <<'PY'
import json, sys
payload = json.load(open(sys.argv[1], encoding='utf-8'))
if payload.get('data', {}).get('status') != 'ok':
    raise SystemExit('rehearsal API health payload is not canonical ok')
PY

printf '[ps7-rehearsal] production edge rehearsal passed\n'
