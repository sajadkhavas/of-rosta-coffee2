#!/usr/bin/env bash
set -Eeuo pipefail

ROSTA_ROOT_DIR="${ROSTA_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
ROSTA_COMPOSE_FILE="${ROSTA_COMPOSE_FILE:-$ROSTA_ROOT_DIR/deploy/production/docker-compose.yml}"
ROSTA_FRONTEND_ENV_FILE="${ROSTA_FRONTEND_ENV_FILE:-/etc/rosta/production/frontend.env}"
ROSTA_BACKEND_ENV_FILE="${ROSTA_BACKEND_ENV_FILE:-/etc/rosta/production/backend.env}"
ROSTA_STATE_DIR="${ROSTA_STATE_DIR:-/var/lib/rosta/production}"
ROSTA_BACKUP_DIR="${ROSTA_BACKUP_DIR:-$ROSTA_STATE_DIR/backups}"

log() {
  printf '[ps7-production] %s\n' "$*"
}

fail() {
  printf '[ps7-production] ERROR: %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1"
}

require_file() {
  [[ -f "$1" ]] || fail "Missing required file: $1"
}

require_secret() {
  local name="$1"
  local value="${!name:-}"
  [[ -n "$value" ]] || fail "$name is empty"
  [[ "$value" != "CHANGE_ME" ]] || fail "$name still contains CHANGE_ME"
}

assert_release_tag() {
  local tag="${1:-}"
  [[ "$tag" =~ ^[0-9a-f]{40}$ ]] \
    || fail "Release identity must be an exact 40-character lowercase Git SHA"
}

load_production_environment() {
  require_file "$ROSTA_FRONTEND_ENV_FILE"
  require_file "$ROSTA_BACKEND_ENV_FILE"

  set -a
  # shellcheck disable=SC1090
  source "$ROSTA_FRONTEND_ENV_FILE"
  # shellcheck disable=SC1090
  source "$ROSTA_BACKEND_ENV_FILE"
  set +a

  export ROSTA_ROOT_DIR ROSTA_COMPOSE_FILE ROSTA_FRONTEND_ENV_FILE ROSTA_BACKEND_ENV_FILE
  export ROSTA_STATE_DIR ROSTA_BACKUP_DIR
  export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-rosta-production}"

  mkdir -p "$ROSTA_STATE_DIR" "$ROSTA_BACKUP_DIR" "$ROSTA_STATE_DIR/reports"
  chmod 700 "$ROSTA_STATE_DIR" "$ROSTA_BACKUP_DIR" "$ROSTA_STATE_DIR/reports"
}

assert_release_identity() {
  assert_release_tag "${ROSTA_IMAGE_TAG:-}"

  local head
  head="$(git -C "$ROSTA_ROOT_DIR" rev-parse HEAD 2>/dev/null || true)"
  [[ "$head" == "$ROSTA_IMAGE_TAG" ]] \
    || fail "Checked-out source SHA does not match ROSTA_IMAGE_TAG"
}

assert_provider_boundary() {
  if [[ "${ROSTA_PAYMENT_ENABLED:-false}" == "true" ]]; then
    [[ "${PAYMENT_DRIVER:-}" == "zarinpal" ]] || fail "Enabled production payment requires PAYMENT_DRIVER=zarinpal"
    [[ "${ZARINPAL_SANDBOX:-}" == "false" ]] || fail "Production payment must not use Zarinpal sandbox"
    require_secret PAYMENT_MERCHANT_ID
  else
    [[ "${PAYMENT_DRIVER:-}" == "disabled" ]] || fail "Disabled production payment requires PAYMENT_DRIVER=disabled"
  fi

  if [[ "${ROSTA_REFUND_ENABLED:-false}" == "true" ]]; then
    [[ "${REFUND_DRIVER:-}" != "disabled" ]] || fail "Enabled refunds require an approved non-disabled execution driver"
  else
    [[ "${REFUND_DRIVER:-}" == "disabled" ]] || fail "Disabled refunds require REFUND_DRIVER=disabled"
  fi

  if [[ "${ROSTA_OTP_ENABLED:-false}" == "true" || "${ROSTA_SMS_ENABLED:-false}" == "true" ]]; then
    [[ "${SMS_DRIVER:-}" == "kavenegar" ]] || fail "Enabled production SMS/OTP requires SMS_DRIVER=kavenegar"
    require_secret KAVENEGAR_API_KEY
    require_secret KAVENEGAR_OTP_TEMPLATE_LOGIN
    require_secret KAVENEGAR_OTP_TEMPLATE_REGISTER
    require_secret KAVENEGAR_OTP_TEMPLATE_VERIFY_MOBILE
  else
    [[ "${SMS_DRIVER:-}" == "disabled" ]] || fail "Disabled production SMS requires SMS_DRIVER=disabled"
  fi

  if [[ "${ROSTA_MEDIA_UPLOADS_ENABLED:-false}" == "true" ]]; then
    [[ "${ROSTA_MEDIA_UPLOAD_DISK:-}" == "s3" ]] || fail "Enabled production media requires the S3/R2 disk"
    require_secret S3_ACCESS_KEY_ID
    require_secret S3_SECRET_ACCESS_KEY
    require_secret S3_BUCKET
    require_secret S3_ENDPOINT
  fi
}

assert_current_auth_cutover_contract() {
  [[ "${ROSTA_OTP_ENABLED:-false}" == "true" ]] \
    || fail "Current production auth implementation requires ROSTA_OTP_ENABLED=true before cutover; do not deploy an unauthenticated runtime"
}

assert_production_environment_contract() {
  [[ "${APP_ENV:-}" == "production" ]] || fail "APP_ENV must be production"
  [[ "${APP_DEBUG:-}" == "false" ]] || fail "APP_DEBUG must be false"
  [[ "${VITE_ALLOW_INDEXING:-}" == "true" ]] || fail "Production frontend indexing must be enabled"
  [[ "${QUEUE_CONNECTION:-}" == "redis" ]] || fail "Production queue must use Redis"
  [[ "${SESSION_DRIVER:-}" == "redis" ]] || fail "Production sessions must use Redis"
  [[ "${SESSION_ENCRYPT:-}" == "true" ]] || fail "Production sessions must be encrypted"
  [[ "${SESSION_SECURE_COOKIE:-}" == "true" ]] || fail "Production session cookies must be secure"
  [[ "${SESSION_SAME_SITE:-}" == "lax" ]] || fail "Production session SameSite must be lax"
  [[ "${SESSION_COOKIE:-}" == "rosta_session" ]] || fail "Production cookie namespace must be rosta_session"

  [[ "${PRODUCTION_SITE_DOMAIN:-}" == "rosta.shop" ]] || fail "Production site domain must be rosta.shop"
  [[ "${PRODUCTION_API_DOMAIN:-}" == "api.rosta.shop" ]] || fail "Production API domain must be api.rosta.shop"
  [[ "${PRODUCTION_MEDIA_DOMAIN:-}" == "media.rosta.shop" ]] || fail "Production media domain must be media.rosta.shop"
  [[ "${SESSION_DOMAIN:-}" == ".rosta.shop" ]] || fail "Production session domain must be .rosta.shop"
  [[ "${SANCTUM_STATEFUL_DOMAINS:-}" == "rosta.shop" ]] || fail "SANCTUM_STATEFUL_DOMAINS must match the production frontend"
  [[ "${FRONTEND_ALLOWED_ORIGINS:-}" == "https://rosta.shop" ]] || fail "FRONTEND_ALLOWED_ORIGINS must match production frontend"
  [[ "${VITE_SITE_URL:-}" == "https://rosta.shop" ]] || fail "VITE_SITE_URL must be the production site"
  [[ "${VITE_API_URL:-}" == "https://api.rosta.shop/api/v1" ]] || fail "VITE_API_URL must be the production API"
  [[ "${APP_URL:-}" == "https://api.rosta.shop" ]] || fail "APP_URL must be the production API origin"
  [[ "${ROSTA_MEDIA_PUBLIC_BASE_URL:-}" == "https://media.rosta.shop" ]] || fail "Media base URL must use the production media domain"

  local required=(
    APP_KEY
    DB_DATABASE
    DB_USERNAME
    DB_PASSWORD
    MYSQL_ROOT_PASSWORD
    REDIS_PASSWORD
    ACME_EMAIL
    ROSTA_CARRIER_WEBHOOK_SECRET
  )
  local name
  for name in "${required[@]}"; do
    require_secret "$name"
  done

  local observability=(
    ROSTA_OBSERVABILITY_LOG_CHANNEL
    ROSTA_OBSERVABILITY_QUEUES
    ROSTA_MAX_FAILED_JOBS
    ROSTA_MAX_FAILED_JOB_AGE_SECONDS
    ROSTA_MAX_QUEUE_DEPTH
  )
  for name in "${observability[@]}"; do
    require_secret "$name"
  done

  assert_provider_boundary
}

assert_production_contract() {
  assert_production_environment_contract
  assert_current_auth_cutover_contract
  assert_release_identity
}

assert_production_runtime_contract() {
  local tag="${1:-}"
  assert_production_environment_contract
  assert_release_tag "$tag"
}

rosta_compose() {
  docker compose \
    --project-name "$COMPOSE_PROJECT_NAME" \
    --env-file "$ROSTA_FRONTEND_ENV_FILE" \
    -f "$ROSTA_COMPOSE_FILE" \
    "$@"
}

current_release_tag() {
  if [[ -f "$ROSTA_STATE_DIR/current" ]]; then
    cat "$ROSTA_STATE_DIR/current"
  fi
  return 0
}

previous_release_tag() {
  if [[ -f "$ROSTA_STATE_DIR/previous" ]]; then
    cat "$ROSTA_STATE_DIR/previous"
  fi
  return 0
}

record_release_tag() {
  local tag="$1"
  assert_release_tag "$tag"
  local current
  current="$(current_release_tag)"
  if [[ -n "$current" && "$current" != "$tag" ]]; then
    printf '%s\n' "$current" > "$ROSTA_STATE_DIR/previous"
  fi
  printf '%s\n' "$tag" > "$ROSTA_STATE_DIR/current"
  chmod 600 "$ROSTA_STATE_DIR/current"
  [[ ! -f "$ROSTA_STATE_DIR/previous" ]] || chmod 600 "$ROSTA_STATE_DIR/previous"
}

backup_database() {
  local reason="${1:-manual}"
  local timestamp output
  timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
  output="$ROSTA_BACKUP_DIR/${timestamp}-${reason}.sql.gz"

  log "Creating transaction-consistent MySQL backup"
  rosta_compose exec -T \
    -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" \
    mysql mysqldump \
      --user=root \
      --single-transaction \
      --quick \
      --routines \
      --events \
      --triggers \
      --set-gtid-purged=OFF \
      "$DB_DATABASE" | gzip -9 > "$output"

  test -s "$output" || fail "Database backup is empty"
  gzip -t "$output" || fail "Database backup gzip validation failed"
  sha256sum "$output" > "$output.sha256"
  chmod 600 "$output" "$output.sha256"

  find "$ROSTA_BACKUP_DIR" -type f -mtime "+${ROSTA_BACKUP_RETENTION_DAYS:-30}" -delete
  printf '%s\n' "$output"
}
