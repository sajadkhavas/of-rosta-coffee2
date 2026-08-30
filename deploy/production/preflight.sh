#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/production/lib.sh
source "$SCRIPT_DIR/lib.sh"

for command in git docker gzip sha256sum curl python3; do
  require_command "$command"
done

docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required"

load_production_environment
assert_production_contract

require_file "$ROSTA_ROOT_DIR/bun.lock"
require_file "$ROSTA_ROOT_DIR/backend/composer.lock"
require_file "$SCRIPT_DIR/Caddyfile"
require_file "$ROSTA_COMPOSE_FILE"

[[ -z "$(git -C "$ROSTA_ROOT_DIR" status --porcelain --untracked-files=all)" ]] \
  || fail "Production source checkout must be clean"

git -C "$ROSTA_ROOT_DIR" diff --check

log "Rendering production Compose contract"
rosta_compose config --quiet

if grep -R -nE '(^|[^A-Za-z])(:latest|latest:)' "$SCRIPT_DIR" --include='*.yml' --include='*.yaml' --include='Caddyfile' >/dev/null 2>&1; then
  fail "Mutable latest image reference detected in production package"
fi

log "Production preflight passed for $ROSTA_IMAGE_TAG"
