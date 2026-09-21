#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FAST_DIR="$ROOT_DIR/deploy/server-fastpath"
WORKFLOW="$ROOT_DIR/.github/workflows/ps12-prebuilt-images.yml"
EXPECTED_SHA="4a54780d504b91527a86777e7f04368022354686"

for script in "$FAST_DIR"/*.sh; do
  bash -n "$script"
done

grep -Fq "EXPECTED_RELEASE_SHA=\"$EXPECTED_SHA\"" "$FAST_DIR/deploy-prebuilt.sh"
grep -Fq "EXPECTED_RELEASE_SHA=\"$EXPECTED_SHA\"" "$FAST_DIR/prepare-server-bundle.sh"
grep -Fq "EXPECTED_RELEASE_SHA: $EXPECTED_SHA" "$WORKFLOW"

grep -Fq 'rosta-pre-server-2026-09-05' "$FAST_DIR/deploy-prebuilt.sh"
grep -Fq 'rosta-pre-server-2026-09-05' "$WORKFLOW"

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

grep -Fq 'packages: write' "$WORKFLOW"
grep -Fq 'docker login "$REGISTRY"' "$WORKFLOW"
grep -Fq 'docker push "$API_IMAGE"' "$WORKFLOW"
grep -Fq 'docker push "$API_WEB_IMAGE"' "$WORKFLOW"
grep -Fq 'docker push "$FRONTEND_IMAGE"' "$WORKFLOW"

credential_pattern='(^|[^A-Z_])(ghp_|github_pat_|sk-[A-Za-z0-9]|AKIA[A-Z0-9]{16})'
if grep -R -E --exclude='contract-test.sh' "$credential_pattern" "$FAST_DIR" \
  || grep -E "$credential_pattern" "$WORKFLOW"; then
  echo "Credential-shaped material found in server fast-path source." >&2
  exit 1
fi

printf 'ROSTA server fast-path contract passed.\n'
