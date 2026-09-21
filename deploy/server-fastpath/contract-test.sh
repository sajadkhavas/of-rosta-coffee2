#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FAST_DIR="$ROOT_DIR/deploy/server-fastpath"
WORKFLOW="$ROOT_DIR/.github/workflows/ps12-prebuilt-images.yml"
HISTORICAL_PS12_SHA="4a54780d504b91527a86777e7f04368022354686"

for script in "$FAST_DIR"/*.sh; do
  bash -n "$script"
done

grep -Fq "HISTORICAL_PS12_SHA=\"$HISTORICAL_PS12_SHA\"" "$FAST_DIR/deploy-prebuilt.sh"
grep -Fq "HISTORICAL_PS12_SHA=\"$HISTORICAL_PS12_SHA\"" "$FAST_DIR/prepare-server-bundle.sh"
grep -Fq "HISTORICAL_PS12_SHA: $HISTORICAL_PS12_SHA" "$WORKFLOW"

grep -Fq 'release_tag:' "$WORKFLOW"
grep -Fq 'git merge-base --is-ancestor "$HISTORICAL_PS12_SHA" "$RELEASE_SHA"' "$WORKFLOW"
grep -Fq 'git rev-list -n 1 "$RELEASE_TAG"' "$WORKFLOW"
grep -Fq 'rosta-server-ready-' "$WORKFLOW"

grep -Fq -- '--no-build --pull never --wait mysql redis' "$FAST_DIR/deploy-prebuilt.sh"
grep -Fq -- '--no-build --pull never --wait' "$FAST_DIR/deploy-prebuilt.sh"
! grep -Eq 'docker (compose )?build|composer update|bun install' "$FAST_DIR/deploy-prebuilt.sh"

grep -Fq 'ROSTA_PAYMENT_ENABLED=false' "$FAST_DIR/backend.env.example"
grep -Fq 'ROSTA_REFUND_ENABLED=false' "$FAST_DIR/backend.env.example"
grep -Fq 'ROSTA_SMS_ENABLED=false' "$FAST_DIR/backend.env.example"
grep -Fq 'ROSTA_MEDIA_UPLOADS_ENABLED=true' "$FAST_DIR/backend.env.example"
grep -Fq 'VITE_ALLOW_INDEXING=false' "$FAST_DIR/frontend.env.example"
grep -Fq 'ROSTA_CONTRACT_VERSION=2026-07-26-r5c' "$FAST_DIR/backend.env.example"

grep -Fq 'payment_redirect_hosts="$site_domain,$api_domain,sandbox.zarinpal.com"'   "$FAST_DIR/prepare-server-bundle.sh" "$WORKFLOW"

grep -Fq 'export ROSTA_IMAGE_TAG="$ROSTA_RELEASE_SHA"' "$FAST_DIR/deploy-prebuilt.sh"
grep -Fq 'staging_runtime_accepted=ready' "$FAST_DIR/deploy-prebuilt.sh"
grep -Fq 'server_ready_bundle=valid' "$FAST_DIR/verify-server-bundle.sh"
grep -Fq 'image_manifest_applied=ready' "$FAST_DIR/apply-image-manifest.sh"

grep -Fq 'packages: write' "$WORKFLOW"
grep -Fq 'actions/checkout@11d5960a326750d5838078e36cf38b85af677262' "$WORKFLOW"
grep -Fq 'actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02' "$WORKFLOW"
grep -Fq 'docker login "$REGISTRY"' "$WORKFLOW"
grep -Fq 'docker push "$API_IMAGE"' "$WORKFLOW"
grep -Fq 'docker push "$API_WEB_IMAGE"' "$WORKFLOW"
grep -Fq 'docker push "$FRONTEND_IMAGE"' "$WORKFLOW"

# Historical PS12 remains a required ancestor, never the silently substituted
# deployment identity after a security refresh/local recertification.
! grep -Fq 'EXPECTED_RELEASE_SHA=' "$FAST_DIR/deploy-prebuilt.sh"
! grep -Fq 'EXPECTED_RELEASE_SHA=' "$FAST_DIR/prepare-server-bundle.sh"
! grep -Fq 'EXPECTED_RELEASE_SHA:' "$WORKFLOW"

credential_pattern='(^|[^A-Z_])(ghp_|github_pat_|sk-[A-Za-z0-9]|AKIA[A-Z0-9]{16})'
credential_hit="$(
  find "$FAST_DIR" -type f ! -name 'contract-test.sh'     -exec grep -H -E "$credential_pattern" {} + 2>/dev/null || true
)"
if [[ -n "$credential_hit" ]] || grep -E "$credential_pattern" "$WORKFLOW"; then
  echo "Credential-shaped material found in server fast-path source." >&2
  [[ -z "$credential_hit" ]] || printf '%s\n' "$credential_hit" >&2
  exit 1
fi

# Full executable bundle generation needs Git ancestry/tag verification. The
# normal CI host has Git and must execute this path. Minimal Alpine production
# build stages intentionally do not install Git; they still execute all static
# and shell-contract assertions above.
if command -v git >/dev/null 2>&1   && git -C "$ROOT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then

  tmp_dir="$(mktemp -d)"
  tmp_tag="rosta-server-ready-2099-01-01-ci"

  cleanup() {
    rm -rf "$tmp_dir"
    git -C "$ROOT_DIR" tag -d "$tmp_tag" >/dev/null 2>&1 || true
  }
  trap cleanup EXIT

  head_sha="$(git -C "$ROOT_DIR" rev-parse HEAD)"
  git -C "$ROOT_DIR" tag -f "$tmp_tag" "$head_sha" >/dev/null

  S3_ACCESS_KEY_ID=ci_access_key   S3_SECRET_ACCESS_KEY=ci_secret_key   S3_BUCKET=rosta-ci-bucket   S3_ENDPOINT=https://example.r2.cloudflarestorage.com   ROSTA_SERVER_READY_DIR="$tmp_dir"     bash "$FAST_DIR/prepare-server-bundle.sh"       "$head_sha"       "$tmp_tag"       staging.rosta.shop       ci@example.invalid       >/dev/null

  cat > "$tmp_dir/manifest.env" <<EOF
ROSTA_RELEASE_SHA=$head_sha
ROSTA_RELEASE_TAG=$tmp_tag
ROSTA_CONFIG_ID=$(awk -F= '$1=="ROSTA_CONFIG_ID"{print $2}' "$tmp_dir/server-entry.env")
STAGING_SITE_DOMAIN=staging.rosta.shop
STAGING_API_DOMAIN=api.staging.rosta.shop
STAGING_MEDIA_DOMAIN=media.staging.rosta.shop
ROSTA_API_DIGEST=ghcr.io/sajadkhavas/rosta-api@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
ROSTA_API_WEB_DIGEST=ghcr.io/sajadkhavas/rosta-api-web@sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
ROSTA_FRONTEND_DIGEST=ghcr.io/sajadkhavas/rosta-frontend@sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc
EOF

  bash "$FAST_DIR/apply-image-manifest.sh" "$tmp_dir/manifest.env" "$tmp_dir" >/dev/null
  bash "$FAST_DIR/verify-server-bundle.sh" "$tmp_dir" >/dev/null

  grep -Fxq "ROSTA_RELEASE_SHA=$head_sha" "$tmp_dir/server-entry.env"
  grep -Fxq "ROSTA_RELEASE_TAG=$tmp_tag" "$tmp_dir/server-entry.env"
  grep -Fxq 'ROSTA_PAYMENT_ENABLED=false' "$tmp_dir/backend.env"
  grep -Fxq 'ROSTA_REFUND_ENABLED=false' "$tmp_dir/backend.env"
  grep -Fxq 'ROSTA_SMS_ENABLED=false' "$tmp_dir/backend.env"
  grep -Fxq 'ROSTA_MEDIA_UPLOADS_ENABLED=true' "$tmp_dir/backend.env"
  grep -Fxq 'VITE_ALLOW_INDEXING=false' "$tmp_dir/frontend.env"
fi

printf 'ROSTA server fast-path contract passed.\n'
