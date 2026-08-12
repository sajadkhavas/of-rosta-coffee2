#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
STAGING_DIR="$ROOT_DIR/deploy/staging"

for script in "$STAGING_DIR"/*.sh; do
  bash -n "$script"
done

grep -Fq 'backend/composer.lock is required; deploy.sh never generates dependencies' "$STAGING_DIR/preflight.sh"
grep -Fq 'backend/composer.lock is required; deployment never generates dependencies' "$STAGING_DIR/deploy.sh"
! grep -Eq 'deploy\.sh will generate|composer update|ensure_composer_lock' "$STAGING_DIR/preflight.sh" "$STAGING_DIR/deploy.sh"

grep -Fxq 'SESSION_COOKIE=rosta_staging_session' "$ROOT_DIR/backend/.env.staging.example"
grep -Fxq 'SESSION_DOMAIN=.staging.rosta.shop' "$ROOT_DIR/backend/.env.staging.example"
grep -Fxq 'STAGING_API_DOMAIN=api.staging.rosta.shop' "$ROOT_DIR/.env.staging.example"
grep -Fxq 'STAGING_MEDIA_DOMAIN=media.staging.rosta.shop' "$ROOT_DIR/.env.staging.example"
! grep -Fxq 'SESSION_DOMAIN=.rosta.shop' "$ROOT_DIR/backend/.env.staging.example"
grep -Fxq 'ROSTA_CONTRACT_VERSION=2026-07-26-r5c' "$ROOT_DIR/backend/.env.staging.example"

printf 'PS1 staging shell contract passed.\n'
