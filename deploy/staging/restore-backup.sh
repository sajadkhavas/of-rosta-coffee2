#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/staging/lib.sh
source "$SCRIPT_DIR/lib.sh"

require_command docker
require_command gzip
require_command sha256sum
require_command flock

load_staging_environment
assert_staging_contract

backup_path="${1:-}"
[[ -n "$backup_path" ]] || fail "Usage: ROSTA_CONFIRM_RESTORE=restore-staging $0 /absolute/path/backup.sql.gz"
[[ "$backup_path" = /* ]] || fail "Backup path must be absolute"
require_file "$backup_path"
require_file "$backup_path.sha256"
[[ "${ROSTA_CONFIRM_RESTORE:-}" == "restore-staging" ]] \
  || fail "Set ROSTA_CONFIRM_RESTORE=restore-staging to acknowledge destructive restore"

gzip -t "$backup_path" || fail "Backup gzip validation failed"
(
  cd "$(dirname "$backup_path")"
  sha256sum --check "$(basename "$backup_path").sha256"
) || fail "Backup checksum validation failed"

exec 9>"${ROSTA_DEPLOY_LOCK_FILE:-/tmp/rosta-staging-deploy.lock}"
flock -n 9 || fail "Another staging deployment or restore is running"

backup_database "pre-destructive-restore"

log "Stopping application services before database restore"
rosta_compose stop edge frontend worker scheduler api-web api
rosta_compose up -d --wait mysql redis

log "Restoring $backup_path into staging database $DB_DATABASE"
gzip -dc "$backup_path" | rosta_compose exec -T \
  -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" \
  mysql mysql --user=root "$DB_DATABASE"

log "Starting the recorded current release after restore"
current="$(current_release_tag)"
[[ -n "$current" ]] || fail "No current release is recorded"
export ROSTA_IMAGE_TAG="$current"
rosta_compose up -d --no-build --wait api api-web worker scheduler frontend edge
rosta_compose exec -T api php artisan migrate --force --no-interaction

ROSTA_RELEASE_TAG="$current-restored" "$SCRIPT_DIR/acceptance.sh"
log "Database restore accepted"
