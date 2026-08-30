#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/production/lib.sh
source "$SCRIPT_DIR/lib.sh"

require_command syft
require_command sha256sum
require_command git

load_production_environment
assert_release_identity

output_dir="${1:-$ROSTA_STATE_DIR/sbom/$ROSTA_IMAGE_TAG}"
mkdir -p "$output_dir"
chmod 700 "$output_dir"

for component in api api-web frontend; do
  image="rosta-${component}:$ROSTA_IMAGE_TAG"
  syft "$image" -o "spdx-json=$output_dir/${component}.spdx.json"
  test -s "$output_dir/${component}.spdx.json" || fail "SBOM is empty for $image"
done

sha256sum "$output_dir"/*.spdx.json > "$output_dir/SHA256SUMS"
chmod 600 "$output_dir"/*.spdx.json "$output_dir/SHA256SUMS"
log "SBOM set generated for $ROSTA_IMAGE_TAG"
