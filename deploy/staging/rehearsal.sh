#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.rehearsal.yml"
EVIDENCE_DIR="${EVIDENCE_DIR:-/tmp/rosta-r4a-evidence}"
BACKUP_FILE="${TMPDIR:-/tmp}/rosta-r4a-${GITHUB_RUN_ID:-$$}.sql.gz"

fail() {
  printf '[r4a] ERROR: %s\n' "$*" >&2
  exit 1
}

log() {
  printf '[r4a] %s\n' "$*"
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1"
}

for command in docker curl gzip sha256sum python3 git; do
  require_command "$command"
done

[[ "${ROSTA_ALLOW_REHEARSAL:-}" == "true" ]] \
  || fail "Set ROSTA_ALLOW_REHEARSAL=true to run the isolated staging rehearsal"

test -s "$ROOT_DIR/bun.lock" || fail "bun.lock is required"
test -s "$ROOT_DIR/backend/composer.lock" || fail "backend/composer.lock is required"
test -f "$COMPOSE_FILE" || fail "Missing rehearsal compose file"

rm -rf "$EVIDENCE_DIR"
mkdir -p "$EVIDENCE_DIR"
chmod 700 "$EVIDENCE_DIR"

random_hex() {
  python3 - <<'PY'
import secrets
print(secrets.token_hex(24))
PY
}

random_base64_key() {
  python3 - <<'PY'
import base64, secrets
print("base64:" + base64.b64encode(secrets.token_bytes(32)).decode())
PY
}

export ROSTA_IMAGE_TAG="$(git -C "$ROOT_DIR" rev-parse HEAD)"
[[ "$ROSTA_IMAGE_TAG" =~ ^[0-9a-f]{40}$ ]] || fail "Rehearsal requires an immutable commit SHA"
export R4A_APP_KEY="$(random_base64_key)"
export R4A_DB_PASSWORD="$(random_hex)"
export R4A_MYSQL_ROOT_PASSWORD="$(random_hex)"
export R4A_REDIS_PASSWORD="$(random_hex)"
export R4A_S3_ACCESS_KEY="r4a$(random_hex | cut -c1-16)"
export R4A_S3_SECRET_KEY="$(random_hex)$(random_hex)"

compose() {
  docker compose \
    --project-name rosta-r4a-rehearsal \
    -f "$COMPOSE_FILE" \
    "$@"
}

collect_runtime_evidence() {
  compose ps > "$EVIDENCE_DIR/compose-ps.txt" 2>&1 || true
  compose logs --no-color --tail=160 api api-web frontend worker scheduler edge minio \
    > "$EVIDENCE_DIR/runtime-tail.log" 2>&1 || true
}

cleanup() {
  local exit_code=$?
  collect_runtime_evidence
  compose down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -f "$BACKUP_FILE" "$BACKUP_FILE.sha256"
  if [[ $exit_code -ne 0 ]]; then
    log "Rehearsal failed; redacted evidence retained at $EVIDENCE_DIR"
  fi
  exit "$exit_code"
}
trap cleanup EXIT

{
  echo "head=$ROSTA_IMAGE_TAG"
  echo "tree=$(git -C "$ROOT_DIR" rev-parse 'HEAD^{tree}')"
  echo "docker=$(docker --version)"
  echo "compose=$(docker compose version)"
} | tee "$EVIDENCE_DIR/versions.txt"

sha256sum \
  "$ROOT_DIR/package.json" \
  "$ROOT_DIR/bun.lock" \
  "$ROOT_DIR/backend/composer.json" \
  "$ROOT_DIR/backend/composer.lock" \
  | tee "$EVIDENCE_DIR/dependencies-before.sha256"

log "Rendering the isolated staging package"
compose config --quiet
compose config --no-interpolate > "$EVIDENCE_DIR/compose-contract.yml"

log "Building immutable backend, Nginx and frontend images"
compose build --pull api api-web frontend 2>&1 | tee "$EVIDENCE_DIR/docker-build.log"

for image in \
  "rosta-r4a-api:$ROSTA_IMAGE_TAG" \
  "rosta-r4a-api-web:$ROSTA_IMAGE_TAG" \
  "rosta-r4a-frontend:$ROSTA_IMAGE_TAG"; do
  docker image inspect --format '{{.Id}}' "$image"
done | tee "$EVIDENCE_DIR/image-identities.txt"

docker tag "rosta-r4a-api:$ROSTA_IMAGE_TAG" rosta-r4a-api:r4a-baseline
docker tag "rosta-r4a-api-web:$ROSTA_IMAGE_TAG" rosta-r4a-api-web:r4a-baseline
docker tag "rosta-r4a-frontend:$ROSTA_IMAGE_TAG" rosta-r4a-frontend:r4a-baseline

log "Starting private MySQL, Redis and S3-compatible object storage"
compose up -d --wait mysql redis
compose up -d minio
compose run --rm --no-deps minio-init 2>&1 | tee "$EVIDENCE_DIR/minio-init.log"

log "Applying forward-only migrations to the empty rehearsal database"
compose run --rm api php artisan migrate --force --no-interaction \
  2>&1 | tee "$EVIDENCE_DIR/migrations.log"

log "Starting the complete staging service topology"
compose up -d --wait api api-web worker scheduler frontend edge

log "Running strict Laravel readiness"
compose exec -T api php artisan rosta:readiness --strict --json \
  | tee "$EVIDENCE_DIR/readiness.json"
python3 - "$EVIDENCE_DIR/readiness.json" <<'PY'
import json, sys
payload = json.load(open(sys.argv[1], encoding="utf-8"))
if payload.get("ready") is not True:
    raise SystemExit("strict readiness did not return ready=true")
PY

log "Running real MySQL, Redis, queue and S3-compatible acceptance"
compose exec -T api php artisan rosta:staging-acceptance --json \
  | tee "$EVIDENCE_DIR/staging-acceptance.json"
python3 - "$EVIDENCE_DIR/staging-acceptance.json" <<'PY'
import json, sys
payload = json.load(open(sys.argv[1], encoding="utf-8"))
if payload.get("accepted") is not True:
    raise SystemExit("staging acceptance did not return accepted=true")
required = {
    "mysql_connection",
    "migrations_current",
    "redis_round_trip",
    "redis_queue",
    "r2_private_round_trip",
    "r2_public_delivery",
    "r2_cors",
    "r2_cleanup",
}
checks = payload.get("checks", {})
missing = sorted(required.difference(checks))
failed = sorted(name for name in required if not checks.get(name, {}).get("passed"))
if missing or failed:
    raise SystemExit(f"incomplete staging acceptance: missing={missing}; failed={failed}")
PY

log "Accepting local edge, SSR, noindex and security-header boundaries"
curl --fail --silent --show-error --dump-header "$EVIDENCE_DIR/home.headers" \
  http://127.0.0.1:18080/ > "$EVIDENCE_DIR/home.html"
curl --fail --silent --show-error --dump-header "$EVIDENCE_DIR/api.headers" \
  http://127.0.0.1:18080/api/v1/health/live > "$EVIDENCE_DIR/api-live.json"
curl --fail --silent --show-error --dump-header "$EVIDENCE_DIR/robots.headers" \
  http://127.0.0.1:18080/robots.txt > "$EVIDENCE_DIR/robots.txt"

grep -Eiq '^x-robots-tag:.*noindex' "$EVIDENCE_DIR/home.headers"
grep -Eiq '^strict-transport-security:' "$EVIDENCE_DIR/home.headers"
grep -Eiq '^content-security-policy:' "$EVIDENCE_DIR/home.headers"
grep -Eiq 'disallow[[:space:]]*:[[:space:]]*/' "$EVIDENCE_DIR/robots.txt"
python3 - "$EVIDENCE_DIR/api-live.json" <<'PY'
import json, sys
payload = json.load(open(sys.argv[1], encoding="utf-8"))
if payload.get("data", {}).get("status") != "ok":
    raise SystemExit("edge API liveness did not return canonical ok status")
PY

log "Creating and verifying a transaction-consistent database backup"
compose exec -T -e MYSQL_PWD="$R4A_MYSQL_ROOT_PASSWORD" mysql \
  mysqldump --user=root --single-transaction --quick --routines --events --triggers \
  --set-gtid-purged=OFF rosta_rehearsal | gzip -9 > "$BACKUP_FILE"
test -s "$BACKUP_FILE"
gzip -t "$BACKUP_FILE"
sha256sum "$BACKUP_FILE" | awk '{print $1}' | tee "$EVIDENCE_DIR/backup.sha256"

source_migrations="$(compose exec -T -e MYSQL_PWD="$R4A_MYSQL_ROOT_PASSWORD" mysql \
  mysql --user=root --batch --skip-column-names \
  -e 'SELECT COUNT(*) FROM rosta_rehearsal.migrations')"
compose exec -T -e MYSQL_PWD="$R4A_MYSQL_ROOT_PASSWORD" mysql \
  mysql --user=root -e 'DROP DATABASE IF EXISTS rosta_rehearsal_restore; CREATE DATABASE rosta_rehearsal_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
gzip -dc "$BACKUP_FILE" | compose exec -T -e MYSQL_PWD="$R4A_MYSQL_ROOT_PASSWORD" mysql \
  mysql --user=root rosta_rehearsal_restore
restored_migrations="$(compose exec -T -e MYSQL_PWD="$R4A_MYSQL_ROOT_PASSWORD" mysql \
  mysql --user=root --batch --skip-column-names \
  -e 'SELECT COUNT(*) FROM rosta_rehearsal_restore.migrations')"
test "$source_migrations" = "$restored_migrations"
printf 'source_migrations=%s\nrestored_migrations=%s\n' \
  "$source_migrations" "$restored_migrations" \
  | tee "$EVIDENCE_DIR/restore-drill.txt"
rm -f "$BACKUP_FILE"

log "Exercising image-only rollback without schema rollback"
accepted_tag="$ROSTA_IMAGE_TAG"
export ROSTA_IMAGE_TAG=r4a-baseline
compose up -d --no-build --wait api api-web worker scheduler frontend edge
curl --fail --silent --show-error http://127.0.0.1:18080/api/v1/health/live \
  > "$EVIDENCE_DIR/rollback-api-live.json"
curl --fail --silent --show-error http://127.0.0.1:18080/robots.txt \
  > "$EVIDENCE_DIR/rollback-robots.txt"
grep -Eiq 'disallow[[:space:]]*:[[:space:]]*/' "$EVIDENCE_DIR/rollback-robots.txt"
export ROSTA_IMAGE_TAG="$accepted_tag"
compose up -d --no-build --wait api api-web worker scheduler frontend edge

log "Exercising release current/previous bookkeeping"
export ROSTA_ROOT_DIR="$ROOT_DIR"
export ROSTA_STATE_DIR="$EVIDENCE_DIR/release-state"
mkdir -p "$ROSTA_STATE_DIR"
# shellcheck source=deploy/staging/lib.sh
source "$SCRIPT_DIR/lib.sh"
record_release_tag r4a-baseline
record_release_tag "$accepted_tag"
test "$(current_release_tag)" = "$accepted_tag"
test "$(previous_release_tag)" = "r4a-baseline"
printf 'current=%s\nprevious=%s\n' \
  "$(current_release_tag)" "$(previous_release_tag)" \
  | tee "$EVIDENCE_DIR/release-state.txt"

sha256sum \
  "$ROOT_DIR/package.json" \
  "$ROOT_DIR/bun.lock" \
  "$ROOT_DIR/backend/composer.json" \
  "$ROOT_DIR/backend/composer.lock" \
  | tee "$EVIDENCE_DIR/dependencies-after.sha256"
cmp "$EVIDENCE_DIR/dependencies-before.sha256" "$EVIDENCE_DIR/dependencies-after.sha256"

collect_runtime_evidence
for secret in \
  "$R4A_APP_KEY" \
  "$R4A_DB_PASSWORD" \
  "$R4A_MYSQL_ROOT_PASSWORD" \
  "$R4A_REDIS_PASSWORD" \
  "$R4A_S3_ACCESS_KEY" \
  "$R4A_S3_SECRET_KEY"; do
  if grep -R -F -- "$secret" "$EVIDENCE_DIR" >/dev/null 2>&1; then
    fail "Ephemeral rehearsal secret entered evidence"
  fi
done

if grep -R -E 'PAYMENT_MERCHANT_ID|KAVENEGAR_API_KEY|APP_KEY=|DB_PASSWORD=|S3_SECRET_ACCESS_KEY=' "$EVIDENCE_DIR" >/dev/null 2>&1; then
  fail "Secret-shaped material entered rehearsal evidence"
fi

if find "$EVIDENCE_DIR" -type f \( -name '*.sql' -o -name '*.sql.gz' -o -name '*.env' -o -name '*.png' -o -name '*.webm' \) | grep -q .; then
  fail "Private, visual or database payload entered rehearsal evidence"
fi

git -C "$ROOT_DIR" diff --check
git -C "$ROOT_DIR" status --porcelain --untracked-files=all \
  | tee "$EVIDENCE_DIR/git-status-final.txt"
test -z "$(git -C "$ROOT_DIR" status --porcelain --untracked-files=all)"

{
  echo "ROSTA_R3A_ACCEPTANCE_FIXTURES_COMPLETE"
  echo "ROSTA_R3B_INTEGRATED_RUNTIME_COMPLETE"
  echo "ROSTA_R3C2_COMMERCE_ROLES_COMPLETE"
  echo "all_surfaces_integrated=ready"
  echo "ROSTA_R4A_STAGING_PACKAGE_COMPLETE"
} | tee "$EVIDENCE_DIR/result.txt"

trap - EXIT
compose down --volumes --remove-orphans >/dev/null
rm -f "$BACKUP_FILE" "$BACKUP_FILE.sha256"
log "Hosted staging package rehearsal completed"
