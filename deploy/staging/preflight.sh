#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/staging/lib.sh
source "$SCRIPT_DIR/lib.sh"

for command in bash curl df docker getent git grep gzip openssl python3 sha256sum stat; do
  require_command "$command"
done

load_staging_environment
assert_staging_contract

failures=0
pass() { printf '[PASS] %s\n' "$*"; }
reject() { printf '[FAIL] %s\n' "$*" >&2; failures=$((failures + 1)); }

check_secret_permissions() {
  local file="$1" mode other
  mode="$(stat -c '%a' "$file")"
  other="${mode: -1}"
  if [[ "$other" == "0" ]]; then
    pass "$file is not accessible to other users (mode $mode)"
  else
    reject "$file must not be accessible to other users (mode $mode)"
  fi
}

check_dns() {
  local domain="$1"
  if getent ahostsv4 "$domain" | awk '{print $1}' | grep -Eq '^[0-9]+(\.[0-9]+){3}$'; then
    pass "$domain resolves through DNS"
  else
    reject "$domain does not resolve to an IPv4 address"
  fi
}

check_secret_permissions "$ROSTA_FRONTEND_ENV_FILE"
check_secret_permissions "$ROSTA_BACKEND_ENV_FILE"

check_dns "$STAGING_SITE_DOMAIN"
check_dns "$STAGING_API_DOMAIN"
check_dns "$STAGING_MEDIA_DOMAIN"

if docker info >/dev/null 2>&1; then
  pass "Docker daemon is available"
else
  reject "Docker daemon is unavailable to the current user"
fi

if docker compose version >/dev/null 2>&1; then
  pass "Docker Compose plugin is available"
else
  reject "Docker Compose plugin is unavailable"
fi

available_kb="$(df -Pk "$ROSTA_ROOT_DIR" | awk 'NR==2 {print $4}')"
if [[ "${available_kb:-0}" -ge 10485760 ]]; then
  pass "Repository filesystem has more than 10 GiB free"
else
  reject "At least 10 GiB free disk is required"
fi

memory_kb="$(awk '/MemTotal:/ {print $2}' /proc/meminfo)"
if [[ "${memory_kb:-0}" -ge 3145728 ]]; then
  pass "Host has at least 3 GiB RAM"
else
  reject "At least 3 GiB RAM is required for the full staging build"
fi

for script in "$SCRIPT_DIR"/*.sh; do
  if bash -n "$script"; then
    pass "Shell syntax: $(basename "$script")"
  else
    reject "Invalid shell syntax: $(basename "$script")"
  fi
done

export ROSTA_IMAGE_TAG="${ROSTA_IMAGE_TAG:-preflight}"
if rosta_compose config --quiet; then
  pass "Docker Compose configuration resolves with the staging contract"
else
  reject "Docker Compose configuration is invalid"
fi

if docker run --rm \
  -e ACME_EMAIL="$ACME_EMAIL" \
  -e STAGING_SITE_DOMAIN="$STAGING_SITE_DOMAIN" \
  -e STAGING_API_DOMAIN="$STAGING_API_DOMAIN" \
  -e STAGING_MEDIA_DOMAIN="$STAGING_MEDIA_DOMAIN" \
  -v "$SCRIPT_DIR/Caddyfile:/etc/caddy/Caddyfile:ro" \
  caddy:2-alpine caddy validate --config /etc/caddy/Caddyfile >/dev/null; then
  pass "Caddy configuration validates"
else
  reject "Caddy configuration validation failed"
fi

host_uid="$(id -u)"
host_gid="$(id -g)"
if docker run --rm \
  --user "$host_uid:$host_gid" \
  -v "$ROSTA_ROOT_DIR:/repo" \
  -w /repo \
  oven/bun:1.2.22-alpine \
  bun run audit:phase22 >/dev/null; then
  pass "Frontend phase 22 permanent audit passes"
else
  reject "Frontend phase 22 permanent audit failed"
fi

if docker run --rm \
  --user "$host_uid:$host_gid" \
  -v "$ROSTA_ROOT_DIR:/repo" \
  -w /repo/backend \
  php:8.3-cli \
  php scripts/audit-staging-deployment-contract.php >/dev/null; then
  pass "Backend phase 22 permanent audit passes"
else
  reject "Backend phase 22 permanent audit failed"
fi

if [[ -s "$ROSTA_ROOT_DIR/backend/composer.lock" ]]; then
  pass "backend/composer.lock is present and deploy.sh will consume the reviewed lock"
else
  reject "backend/composer.lock is required; deploy.sh never generates dependencies"
fi

if [[ "$failures" -ne 0 ]]; then
  fail "Staging preflight failed with $failures problem(s)"
fi

log "Staging preflight passed"
