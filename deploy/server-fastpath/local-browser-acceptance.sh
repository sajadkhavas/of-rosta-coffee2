#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/deploy/server-fastpath/docker-compose.local-browser.yml"
HEAD_SHA="$(git -C "$ROOT_DIR" rev-parse HEAD)"
EVIDENCE_DIR="${ROSTA_LOCAL_BROWSER_EVIDENCE_DIR:-$ROOT_DIR/.server-ready/local-browser-$HEAD_SHA}"

fail() {
  printf '[rosta-local-browser] ERROR: %s\n' "$*" >&2
  exit 1
}

log() {
  printf '[rosta-local-browser] %s\n' "$*"
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1"
}

for command in git docker bun node php composer curl python3 sha256sum; do
  require_command "$command"
done

docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required"

[[ "$HEAD_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Exact Git SHA is required"
git -C "$ROOT_DIR" merge-base --is-ancestor   4a54780d504b91527a86777e7f04368022354686 "$HEAD_SHA"   || fail "Local candidate must remain a descendant of frozen PS12"

test -z "$(git -C "$ROOT_DIR" status --porcelain --untracked-files=all)"   || fail "Worktree must be clean before local browser acceptance"

node_major="$(node -p 'process.versions.node.split(".")[0]')"
test "$node_major" = "22" || fail "Node 22 is required to mirror Browser Acceptance CI"

bun_version="$(bun --version)"
test "$bun_version" = "1.2.22"   || fail "Bun 1.2.22 is required to mirror Browser Acceptance CI; found $bun_version"

php_minor="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
test "$php_minor" = "8.3" || fail "PHP 8.3 is required; found $php_minor"

mkdir -p "$EVIDENCE_DIR"
chmod 700 "$EVIDENCE_DIR"

compose() {
  docker compose     --project-name rosta-local-browser     -f "$COMPOSE_FILE"     "$@"
}

pids=()
cleanup() {
  local exit_code=$?
  for pid in "${pids[@]:-}"; do
    kill "$pid" >/dev/null 2>&1 || true
  done
  compose down --volumes --remove-orphans >/dev/null 2>&1 || true
  if [[ $exit_code -ne 0 ]]; then
    log "FAILED — evidence retained at $EVIDENCE_DIR"
  fi
  exit "$exit_code"
}
trap cleanup EXIT INT TERM

log "Starting isolated MySQL 8.4 and Redis 7.4"
compose down --volumes --remove-orphans >/dev/null 2>&1 || true
compose up -d --wait mysql redis

export APP_ENV=testing
export APP_DEBUG=false
export APP_URL=http://127.0.0.1:8000
export APP_KEY="base64:$(python3 - <<'PY'
import base64, secrets
print(base64.b64encode(secrets.token_bytes(32)).decode())
PY
)"
export LOG_CHANNEL=stack
export LOG_STACK=single
export FRONTEND_ALLOWED_ORIGINS=http://127.0.0.1:3000,http://localhost:5173,http://127.0.0.1:5173
export SANCTUM_STATEFUL_DOMAINS=127.0.0.1:3000,localhost:5173,127.0.0.1:5173
export SESSION_DRIVER=redis
export SESSION_SECURE_COOKIE=false
export SESSION_SAME_SITE=lax
export CACHE_STORE=redis
export QUEUE_CONNECTION=redis
export DB_CONNECTION=mysql
export DB_HOST=127.0.0.1
export DB_PORT=13306
export DB_DATABASE=rosta_local_acceptance
export DB_USERNAME=rosta
export DB_PASSWORD=rosta_local_acceptance
export REDIS_CLIENT=phpredis
export REDIS_HOST=127.0.0.1
export REDIS_PORT=16379
export SMS_DRIVER=acceptance
export ROSTA_SMS_ENABLED=false
export ROSTA_PAYMENT_ENABLED=true
export PAYMENT_DRIVER=testing
export PAYMENT_AMOUNT_MULTIPLIER=1
export PAYMENT_CALLBACK_URL=http://127.0.0.1:3000/checkout
export ROSTA_ALLOWED_PAYMENT_REDIRECT_HOSTS=127.0.0.1
export ROSTA_REFUND_ENABLED=false
export ROSTA_MEDIA_UPLOADS_ENABLED=false
export ROSTA_MAX_DISPATCH_ROAST_AGE_DAYS=30
export VITE_SITE_URL=http://127.0.0.1:3000
export VITE_API_URL=http://127.0.0.1:8000/api/v1
export VITE_PAYMENT_REDIRECT_HOSTS=127.0.0.1:8000,127.0.0.1:3000
export VITE_ALLOW_INDEXING=false
export ROSTA_INTERNAL_API_URL=http://127.0.0.1:8000/api/v1
export R3C_FRONTEND_BASE=http://127.0.0.1:3000
export NODE_ENV=production
export NITRO_PRESET=node-server

sha256sum   "$ROOT_DIR/package.json"   "$ROOT_DIR/bun.lock"   "$ROOT_DIR/backend/composer.json"   "$ROOT_DIR/backend/composer.lock"   > "$EVIDENCE_DIR/dependencies-before.sha256"

log "Installing exact locked dependencies"
(
  cd "$ROOT_DIR"
  bun install --frozen-lockfile
  composer --working-dir=backend install     --no-interaction     --prefer-dist     --no-progress
)

log "Running dependency and aggregate source gates"
(
  cd "$ROOT_DIR"
  bun run audit:dependencies
  bun run check:all
) 2>&1 | tee "$EVIDENCE_DIR/check-all.log"

mkdir -p   "$ROOT_DIR/backend/bootstrap/cache"   "$ROOT_DIR/backend/storage/app/private"   "$ROOT_DIR/backend/storage/app/public"   "$ROOT_DIR/backend/storage/framework/cache/data"   "$ROOT_DIR/backend/storage/framework/sessions"   "$ROOT_DIR/backend/storage/framework/views"   "$ROOT_DIR/backend/storage/logs"

log "Preparing isolated acceptance database and fixtures"
(
  cd "$ROOT_DIR/backend"
  php artisan migrate:fresh --force
  php artisan rosta:readiness --strict --json
  php artisan rosta:acceptance-fixtures --json
) 2>&1 | tee "$EVIDENCE_DIR/backend-prep.log"

grep -q 'ROSTA_R3A_ACCEPTANCE_FIXTURES_COMPLETE' "$EVIDENCE_DIR/backend-prep.log"   || fail "Acceptance fixture completion marker is missing"

log "Building production SSR"
(
  cd "$ROOT_DIR"
  bun run build
) 2>&1 | tee "$EVIDENCE_DIR/frontend-build.log"

log "Starting Laravel API, notification worker and production SSR"
(
  cd "$ROOT_DIR/backend"
  php artisan serve --host=127.0.0.1 --port=8000
) > "$EVIDENCE_DIR/laravel-runtime.log" 2>&1 &
pids+=("$!")

(
  cd "$ROOT_DIR/backend"
  php artisan queue:work redis     --queue=notifications     --sleep=1     --tries=1     --timeout=30
) > "$EVIDENCE_DIR/queue-worker.log" 2>&1 &
pids+=("$!")

(
  cd "$ROOT_DIR"
  HOST=127.0.0.1 PORT=3000 node .output/server/index.mjs
) > "$EVIDENCE_DIR/frontend-runtime.log" 2>&1 &
pids+=("$!")

for attempt in $(seq 1 60); do
  if curl --fail --silent http://127.0.0.1:8000/api/v1/health/live >/dev/null     && curl --fail --silent http://127.0.0.1:3000/robots.txt >/dev/null; then
    break
  fi
  if [[ "$attempt" -eq 60 ]]; then
    cat "$EVIDENCE_DIR/laravel-runtime.log" >&2 || true
    cat "$EVIDENCE_DIR/frontend-runtime.log" >&2 || true
    fail "Local integrated runtime did not become healthy"
  fi
  sleep 1
done

log "Checking local Chromium availability"
if ! bunx playwright install --dry-run chromium >/dev/null 2>&1; then
  fail "Playwright is unavailable"
fi
bunx playwright install chromium >/dev/null

log "Running R3 browser contract audits and real browser journeys"
(
  cd "$ROOT_DIR"
  bun run audit:r3c
  bun run audit:r3c2
  export R3C_JUNIT_PATH="$EVIDENCE_DIR/r3c-junit.xml"
  bun run test:browser -- --config=playwright.config.ts
) 2>&1 | tee "$EVIDENCE_DIR/browser.log"

grep -q 'failures="0"' "$EVIDENCE_DIR/r3c-junit.xml"   || fail "Playwright JUnit report contains failures"

for pid in "${pids[@]}"; do
  kill -0 "$pid" 2>/dev/null || fail "A required runtime process exited unexpectedly"
done

sha256sum   "$ROOT_DIR/package.json"   "$ROOT_DIR/bun.lock"   "$ROOT_DIR/backend/composer.json"   "$ROOT_DIR/backend/composer.lock"   > "$EVIDENCE_DIR/dependencies-after.sha256"
cmp "$EVIDENCE_DIR/dependencies-before.sha256" "$EVIDENCE_DIR/dependencies-after.sha256"

rm -f   "$ROOT_DIR/r3c-browser-audit.json"   "$ROOT_DIR/r3c2-commerce-roles-audit.json"   "$ROOT_DIR/backend/"*-audit.json 2>/dev/null || true
rm -rf   "$ROOT_DIR/backend/storage/framework/cache/phpstan"   "$ROOT_DIR/backend/storage/framework/testing"   "$ROOT_DIR/test-results" 2>/dev/null || true

if ! git -C "$ROOT_DIR" diff --quiet -- src/routeTree.gen.ts; then
  git -C "$ROOT_DIR" checkout -- src/routeTree.gen.ts
fi

git -C "$ROOT_DIR" diff --check
test -z "$(git -C "$ROOT_DIR" status --porcelain --untracked-files=all)"   || fail "Local browser acceptance left unexpected source changes"

echo "local_browser_acceptance=ready"
echo "candidate_sha=$HEAD_SHA"
