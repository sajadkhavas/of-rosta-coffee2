#!/usr/bin/env bash
set -Eeuo pipefail

ROSTA_ROOT_DIR="${ROSTA_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
ROSTA_COMPOSE_FILE="${ROSTA_COMPOSE_FILE:-$ROSTA_ROOT_DIR/deploy/staging/docker-compose.yml}"
ROSTA_FRONTEND_ENV_FILE="${ROSTA_FRONTEND_ENV_FILE:-$ROSTA_ROOT_DIR/.env.staging}"
ROSTA_BACKEND_ENV_FILE="${ROSTA_BACKEND_ENV_FILE:-$ROSTA_ROOT_DIR/backend/.env.staging}"
ROSTA_STATE_DIR="${ROSTA_STATE_DIR:-$ROSTA_ROOT_DIR/.staging-state}"
ROSTA_BACKUP_DIR="${ROSTA_BACKUP_DIR:-$ROSTA_STATE_DIR/backups}"

log() {
  printf '[phase22] %s\n' "$*"
}

fail() {
  printf '[phase22] ERROR: %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1"
}

require_file() {
  [[ -f "$1" ]] || fail "Missing required file: $1"
}

load_staging_environment() {
  require_file "$ROSTA_FRONTEND_ENV_FILE"
  require_file "$ROSTA_BACKEND_ENV_FILE"

  set -a
  # shellcheck disable=SC1090
  source "$ROSTA_FRONTEND_ENV_FILE"
  # shellcheck disable=SC1090
  source "$ROSTA_BACKEND_ENV_FILE"
  set +a

  export ROSTA_ROOT_DIR ROSTA_COMPOSE_FILE ROSTA_FRONTEND_ENV_FILE
  export ROSTA_BACKEND_ENV_FILE ROSTA_STATE_DIR ROSTA_BACKUP_DIR
  export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-rosta-staging}"
  export ROSTA_IMAGE_TAG="${ROSTA_IMAGE_TAG:-$(git -C "$ROSTA_ROOT_DIR" rev-parse --short=12 HEAD 2>/dev/null || date -u +%Y%m%d%H%M%S)}"

  mkdir -p "$ROSTA_STATE_DIR" "$ROSTA_BACKUP_DIR" "$ROSTA_STATE_DIR/reports"
}

require_secret() {
  local name="$1"
  local value="${!name:-}"
  [[ -n "$value" ]] || fail "$name is empty"
  [[ "$value" != "CHANGE_ME" ]] || fail "$name still contains CHANGE_ME"
}

assert_staging_contract() {
  [[ "${APP_ENV:-}" == "staging" ]] || fail "APP_ENV must be staging"
  [[ "${APP_DEBUG:-}" == "false" ]] || fail "APP_DEBUG must be false"
  [[ "${VITE_ALLOW_INDEXING:-}" == "false" ]] || fail "VITE_ALLOW_INDEXING must be false"
  [[ "${ROSTA_PAYMENT_ENABLED:-}" == "false" ]] || fail "Payments must remain disabled"
  [[ "${ROSTA_REFUND_ENABLED:-}" == "false" ]] || fail "Refund execution must remain disabled"
  [[ "${ROSTA_SMS_ENABLED:-}" == "false" ]] || fail "SMS must remain disabled"
  [[ "${ROSTA_MEDIA_UPLOADS_ENABLED:-}" == "true" ]] || fail "R2 media uploads must be enabled for phase 22 acceptance"
  [[ "${ROSTA_MEDIA_UPLOAD_DISK:-}" == "s3" ]] || fail "ROSTA_MEDIA_UPLOAD_DISK must be s3"
  [[ "${SESSION_SECURE_COOKIE:-}" == "true" ]] || fail "Secure session cookies are required"

  local required=(
    APP_KEY
    DB_DATABASE
    DB_USERNAME
    DB_PASSWORD
    MYSQL_ROOT_PASSWORD
    REDIS_PASSWORD
    S3_ACCESS_KEY_ID
    S3_SECRET_ACCESS_KEY
    S3_BUCKET
    S3_ENDPOINT
    S3_PUBLIC_URL
    ACME_EMAIL
    STAGING_SITE_DOMAIN
    STAGING_API_DOMAIN
  )
  local name
  for name in "${required[@]}"; do
    require_secret "$name"
  done

  [[ "${VITE_SITE_URL:-}" == "https://${STAGING_SITE_DOMAIN}" ]] \
    || fail "VITE_SITE_URL must match STAGING_SITE_DOMAIN"
  [[ "${VITE_API_URL:-}" == "https://${STAGING_API_DOMAIN}/api/v1" ]] \
    || fail "VITE_API_URL must match STAGING_API_DOMAIN"
  [[ "${APP_URL:-}" == "https://${STAGING_API_DOMAIN}" ]] \
    || fail "APP_URL must match STAGING_API_DOMAIN"
}

rosta_compose() {
  docker compose \
    --project-name "$COMPOSE_PROJECT_NAME" \
    --env-file "$ROSTA_FRONTEND_ENV_FILE" \
    -f "$ROSTA_COMPOSE_FILE" \
    "$@"
}

current_release_tag() {
  [[ -f "$ROSTA_STATE_DIR/current" ]] && cat "$ROSTA_STATE_DIR/current" || true
}

previous_release_tag() {
  [[ -f "$ROSTA_STATE_DIR/previous" ]] && cat "$ROSTA_STATE_DIR/previous" || true
}

record_release_tag() {
  local tag="$1"
  local current
  current="$(current_release_tag)"
  if [[ -n "$current" && "$current" != "$tag" ]]; then
    printf '%s\n' "$current" > "$ROSTA_STATE_DIR/previous"
  fi
  printf '%s\n' "$tag" > "$ROSTA_STATE_DIR/current"
}

backup_database() {
  local reason="${1:-manual}"
  if ! rosta_compose ps --status running mysql 2>/dev/null | grep -q mysql; then
    log "MySQL is not running; no pre-deploy backup was created"
    return 0
  fi

  local timestamp output
  timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
  output="$ROSTA_BACKUP_DIR/${timestamp}-${reason}.sql.gz"
  log "Creating transaction-consistent MySQL backup: $output"
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
  sha256sum "$output" > "$output.sha256"

  find "$ROSTA_BACKUP_DIR" -type f \
    -mtime "+${ROSTA_BACKUP_RETENTION_DAYS:-14}" \
    -delete
}
