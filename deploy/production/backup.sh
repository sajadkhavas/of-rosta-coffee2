#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/production/lib.sh
source "$SCRIPT_DIR/lib.sh"

for command in docker gzip sha256sum; do
  require_command "$command"
done

load_production_environment
assert_production_environment_contract

if ! rosta_compose ps --status running mysql | grep -q mysql; then
  fail "MySQL must be running before a production backup"
fi

backup_database "${1:-manual}"
