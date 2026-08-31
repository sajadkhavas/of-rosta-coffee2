#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"

fail() {
  printf '[ps7-contract] ERROR: %s\n' "$*" >&2
  exit 1
}

for command in bash grep git docker; do
  command -v "$command" >/dev/null 2>&1 || fail "Missing command: $command"
done

docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required"

required=(
  Caddyfile
  docker-compose.yml
  frontend.env.example
  backend.env.example
  lib.sh
  preflight.sh
  deploy.sh
  acceptance.sh
  backup.sh
  restore-backup.sh
  rollback.sh
  rehearsal.sh
  docker-compose.rehearsal.yml
  Caddyfile.rehearsal
  generate-sbom.sh
  README.md
)
for file in "${required[@]}"; do
  [[ -f "$SCRIPT_DIR/$file" ]] || fail "Missing production package file: $file"
done
[[ -f "$ROOT_DIR/Dockerfile.production" ]] || fail "Missing dedicated root Dockerfile.production"
[[ -f "$ROOT_DIR/backend/Dockerfile.production" ]] || fail "Missing backend Dockerfile.production"

for script in "$SCRIPT_DIR"/*.sh; do
  bash -n "$script"
done

if command -v shellcheck >/dev/null 2>&1; then
  shellcheck "$SCRIPT_DIR"/*.sh
fi

grep -q '^VITE_ALLOW_INDEXING=true$' "$SCRIPT_DIR/frontend.env.example" \
  || fail "Production frontend example must enable indexing"
grep -q '^APP_ENV=production$' "$SCRIPT_DIR/backend.env.example" \
  || fail "Production backend example must use APP_ENV=production"
grep -q '^ROSTA_PAYMENT_ENABLED=false$' "$SCRIPT_DIR/backend.env.example" \
  || fail "Payment must be fail-closed in the source example"
grep -q '^ROSTA_SMS_ENABLED=false$' "$SCRIPT_DIR/backend.env.example" \
  || fail "SMS must be fail-closed in the source example"
grep -q '^ROSTA_MEDIA_UPLOADS_ENABLED=false$' "$SCRIPT_DIR/backend.env.example" \
  || fail "Media provider activation must be fail-closed in the source example"
grep -q 'ROSTA_IMAGE_TAG must be the full release SHA' "$SCRIPT_DIR/docker-compose.yml" \
  || fail "Production images must be keyed by immutable release SHA"
grep -q 'dockerfile: Dockerfile.production' "$SCRIPT_DIR/docker-compose.yml" \
  || fail "Production topology must use production Dockerfiles"
if grep -q 'Dockerfile.staging' "$SCRIPT_DIR/docker-compose.yml"; then
  fail "Production topology must not build from Dockerfile.staging"
fi
grep -q 'VITE_ALLOW_INDEXING: "true"' "$SCRIPT_DIR/docker-compose.yml" \
  || fail "Production image build must be indexable"
grep -q 'Cache-Control "private, no-store' "$SCRIPT_DIR/Caddyfile" \
  || fail "Private workspaces require no-store edge policy"
grep -q 'X-Robots-Tag "noindex' "$SCRIPT_DIR/Caddyfile" \
  || fail "Private workspaces require noindex edge policy"

grep -q 'assert_production_runtime_contract' "$SCRIPT_DIR/lib.sh" \
  || fail "Runtime acceptance contract must be separate from source identity"
grep -q 'ROSTA_ACCEPTANCE_RELEASE_TAG' "$SCRIPT_DIR/acceptance.sh" \
  || fail "Acceptance must support an explicit runtime release target"
grep -q "docker inspect --format '{{.Config.Image}}'" "$SCRIPT_DIR/acceptance.sh" \
  || fail "Acceptance must verify actual running image identity"
grep -q 'ROSTA_ACCEPTANCE_RELEASE_TAG="$previous"' "$SCRIPT_DIR/rollback.sh" \
  || fail "Rollback must accept the previous runtime release explicitly"

if grep -R -nE 'image:[[:space:]]+[^#[:space:]]*:latest([[:space:]]|$)' "$SCRIPT_DIR" --include='*.yml' --include='*.yaml'; then
  fail "Mutable latest image tag is forbidden"
fi

if grep -R -nE 'APP_ENV[=:][[:space:]]*staging|rosta-staging|staging\.rosta\.shop|Dockerfile\.staging' "$SCRIPT_DIR" \
  --exclude='README.md'; then
  fail "Staging namespace leaked into executable production package"
fi

if grep -R -nE '(sk-proj-|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----)' "$SCRIPT_DIR"; then
  fail "Secret material detected in production package"
fi

set -a
# shellcheck disable=SC1091
source "$SCRIPT_DIR/frontend.env.example"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/backend.env.example"
set +a
export ROSTA_BACKEND_ENV_FILE="$SCRIPT_DIR/backend.env.example"
export ROSTA_IMAGE_TAG="0000000000000000000000000000000000000000"

docker compose \
  --project-name rosta-ps7-contract \
  --env-file "$SCRIPT_DIR/frontend.env.example" \
  -f "$SCRIPT_DIR/docker-compose.yml" \
  config --quiet

docker compose \
  --project-name rosta-ps7-rehearsal-contract \
  -f "$SCRIPT_DIR/docker-compose.rehearsal.yml" \
  config --quiet

docker run --rm \
  -e ACME_EMAIL=contract@example.com \
  -e PRODUCTION_SITE_DOMAIN=rosta.shop \
  -e PRODUCTION_API_DOMAIN=api.rosta.shop \
  -e PRODUCTION_MEDIA_DOMAIN=media.rosta.shop \
  -v "$SCRIPT_DIR/Caddyfile:/etc/caddy/Caddyfile:ro" \
  caddy:2.10.2-alpine \
  caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null

docker run --rm \
  -v "$SCRIPT_DIR/Caddyfile.rehearsal:/etc/caddy/Caddyfile:ro" \
  caddy:2.10.2-alpine \
  caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null

git -C "$ROOT_DIR" diff --check
printf 'PS7 production package contract passed.\n'
