#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/production/lib.sh
source "$SCRIPT_DIR/lib.sh"

[[ "${ROSTA_CONFIRM_PRODUCTION_RESTORE:-}" == "yes" ]] \
  || fail "Set ROSTA_CONFIRM_PRODUCTION_RESTORE=yes for an intentional destructive restore"

backup_file="${1:-}"
[[ -n "$backup_file" ]] || fail "Usage: restore-backup.sh /absolute/path/to/backup.sql.gz"
[[ "$backup_file" = /* ]] || fail "Restore path must be absolute"
[[ -f "$backup_file" ]] || fail "Backup file does not exist"
[[ -f "$backup_file.sha256" ]] || fail "Backup checksum sidecar is required"

for command in docker gzip sha256sum; do
  require_command "$command"
done

load_production_environment
assert_production_contract
sha256sum -c "$backup_file.sha256"
gzip -t "$backup_file"

log "Creating mandatory pre-restore safety backup"
backup_database pre-restore >/dev/null

log "Stopping application writers before restore"
rosta_compose stop worker scheduler api-web frontend edge api

log "Restoring database from verified backup"
rosta_compose exec -T -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql \
  mysql --user=root -e "DROP DATABASE IF EXISTS \`$DB_DATABASE\`; CREATE DATABASE \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gzip -dc "$backup_file" | rosta_compose exec -T -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql \
  mysql --user=root "$DB_DATABASE"

log "Starting application against restored data"
rosta_compose up -d --wait api api-web worker scheduler frontend edge
"$SCRIPT_DIR/acceptance.sh"
log "Verified production restore completed"
