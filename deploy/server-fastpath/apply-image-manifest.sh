#!/usr/bin/env bash
set -Eeuo pipefail

MANIFEST_FILE="${1:-}"
BUNDLE_DIR="${2:-.server-ready}"

fail() {
  printf '[apply-image-manifest] ERROR: %s\n' "$*" >&2
  exit 1
}

[[ -n "$MANIFEST_FILE" ]] || fail "Usage: $0 <manifest.env> [bundle-dir]"
[[ -s "$MANIFEST_FILE" ]] || fail "Manifest file is missing or empty: $MANIFEST_FILE"
[[ -s "$BUNDLE_DIR/server-entry.env" ]] || fail "Missing $BUNDLE_DIR/server-entry.env"

# Load expected local bundle identity first.
set -a
# shellcheck disable=SC1090
source "$BUNDLE_DIR/server-entry.env"
set +a

expected_sha="$ROSTA_RELEASE_SHA"
expected_tag="$ROSTA_RELEASE_TAG"
expected_config="$ROSTA_CONFIG_ID"
expected_site="$STAGING_SITE_DOMAIN"
expected_api="$STAGING_API_DOMAIN"
expected_media="$STAGING_MEDIA_DOMAIN"

# Load workflow-produced manifest in a subshell-safe namespace.
manifest_value() {
  local key="$1"
  awk -F= -v key="$key" '$1 == key {sub(/^[^=]*=/, ""); print; exit}' "$MANIFEST_FILE"
}

manifest_sha="$(manifest_value ROSTA_RELEASE_SHA)"
manifest_tag="$(manifest_value ROSTA_RELEASE_TAG)"
manifest_config="$(manifest_value ROSTA_CONFIG_ID)"
manifest_site="$(manifest_value STAGING_SITE_DOMAIN)"
manifest_api="$(manifest_value STAGING_API_DOMAIN)"
manifest_media="$(manifest_value STAGING_MEDIA_DOMAIN)"
api_digest="$(manifest_value ROSTA_API_DIGEST)"
api_web_digest="$(manifest_value ROSTA_API_WEB_DIGEST)"
frontend_digest="$(manifest_value ROSTA_FRONTEND_DIGEST)"

test "$manifest_sha" = "$expected_sha" || fail "Manifest release SHA mismatch"
test "$manifest_tag" = "$expected_tag" || fail "Manifest release tag mismatch"
test "$manifest_config" = "$expected_config" || fail "Manifest config ID mismatch"
test "$manifest_site" = "$expected_site" || fail "Manifest site domain mismatch"
test "$manifest_api" = "$expected_api" || fail "Manifest API domain mismatch"
test "$manifest_media" = "$expected_media" || fail "Manifest media domain mismatch"

for ref in "$api_digest" "$api_web_digest" "$frontend_digest"; do
  [[ "$ref" =~ ^ghcr\.io/sajadkhavas/[a-z0-9._/-]+@sha256:[0-9a-f]{64}$ ]]     || fail "Manifest contains a non-immutable or unexpected GHCR digest reference: $ref"
done

python3 - "$BUNDLE_DIR/server-entry.env" "$api_digest" "$api_web_digest" "$frontend_digest" <<'PY'
import sys
from pathlib import Path

path = Path(sys.argv[1])
replacements = {
    "ROSTA_API_REMOTE_IMAGE": sys.argv[2],
    "ROSTA_API_WEB_REMOTE_IMAGE": sys.argv[3],
    "ROSTA_FRONTEND_REMOTE_IMAGE": sys.argv[4],
}

lines = path.read_text(encoding="utf-8").splitlines()
seen = set()
out = []
for line in lines:
    key = line.split("=", 1)[0] if "=" in line else None
    if key in replacements:
        out.append(f"{key}={replacements[key]}")
        seen.add(key)
    else:
        out.append(line)

missing = set(replacements) - seen
if missing:
    raise SystemExit(f"Missing image keys in server-entry.env: {sorted(missing)}")

path.write_text("\n".join(out) + "\n", encoding="utf-8")
PY

chmod 600 "$BUNDLE_DIR/server-entry.env"

sha256sum   "$BUNDLE_DIR/frontend.env"   "$BUNDLE_DIR/backend.env"   "$BUNDLE_DIR/server-entry.env"   "$BUNDLE_DIR/release-request.txt"   > "$BUNDLE_DIR/bundle.sha256"
chmod 600 "$BUNDLE_DIR/bundle.sha256"

echo "image_manifest_applied=ready"
echo "release_sha=$expected_sha"
echo "release_tag=$expected_tag"
echo "config_id=$expected_config"
