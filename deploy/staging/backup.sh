#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/staging/lib.sh
source "$SCRIPT_DIR/lib.sh"

require_command docker
require_command gzip
require_command sha256sum

load_staging_environment
assert_staging_contract
backup_database "manual"
log "Backup completed. Files are stored under $ROSTA_BACKUP_DIR"
