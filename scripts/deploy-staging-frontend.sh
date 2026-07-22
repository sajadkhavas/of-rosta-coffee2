#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

if [[ ! -f .env.staging ]]; then
  echo "Missing .env.staging. Copy .env.staging.example and review every value." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env.staging
set +a

if [[ "${VITE_ALLOW_INDEXING:-}" != "false" ]]; then
  echo "Staging deployment requires VITE_ALLOW_INDEXING=false." >&2
  exit 1
fi

if [[ "${VITE_SITE_URL:-}" != "https://staging.rosta.shop" ]]; then
  echo "VITE_SITE_URL must be https://staging.rosta.shop for this script." >&2
  exit 1
fi

if [[ "${VITE_API_URL:-}" != https://* ]]; then
  echo "VITE_API_URL must be an HTTPS URL." >&2
  exit 1
fi

if [[ -z "${CLOUDFLARE_API_TOKEN:-}" ]]; then
  echo "CLOUDFLARE_API_TOKEN must be exported in the deployment shell." >&2
  exit 1
fi

bun install --frozen-lockfile
bun run check

test -s .output/server/wrangler.json
bunx wrangler deploy \
  --config .output/server/wrangler.json \
  --name rosta-staging

echo "Frontend worker deployed with indexing disabled."
