#!/usr/bin/env bash
set -euo pipefail

cat \
  scripts/r5i-full-parts/part-00.b64 \
  scripts/r5i-full-parts/part-01.b64 \
  scripts/r5i-correct/part-02a.b64 \
  scripts/r5i-correct/part-02b-0.b64 \
  scripts/r5i-correct/part-02b-1.b64 \
  scripts/r5i-correct/part-02b-2.b64 \
  scripts/r5i-full-parts/part-03.b64 \
  > /tmp/r5i-product.patch.gz.b64

test "$(wc -c < /tmp/r5i-product.patch.gz.b64)" = "45332"
test "$(sha256sum /tmp/r5i-product.patch.gz.b64 | awk '{print $1}')" = "daa0ab344192986316ab28e197994cf98e89e94721b5e993083317b754ee7f60"
test "$(git hash-object /tmp/r5i-product.patch.gz.b64)" = "0ee91be1eab9a236b6153178cdc7afe1a459cfdf"
base64 -d /tmp/r5i-product.patch.gz.b64 | gzip -d > /tmp/r5i-product.patch
git apply --check /tmp/r5i-product.patch
git apply /tmp/r5i-product.patch

apply_correction() {
  local source="$1" bytes="$2" sha256="$3" blob="$4" target="$5"
  test "$(wc -c < "$source")" = "$bytes"
  test "$(sha256sum "$source" | awk '{print $1}')" = "$sha256"
  test "$(git hash-object "$source")" = "$blob"
  base64 -d "$source" | gzip -d > "$target"
  git apply --check "$target"
  git apply "$target"
}

apply_raw_correction() {
  local source="$1" bytes="$2" sha256="$3" blob="$4"
  test "$(wc -c < "$source")" = "$bytes"
  test "$(sha256sum "$source" | awk '{print $1}')" = "$sha256"
  test "$(git hash-object "$source")" = "$blob"
  git apply --check "$source"
  git apply "$source"
}

apply_correction \
  scripts/r5i-correct/r5i-correction.patch.gz.b64 \
  1832 \
  fabf27c7fbb3d71e6bdd0b00c6fab4749bb753d23eab61c469d883d8540ee701 \
  cb7b49d095b34f43197a61bc0a406ac8c6d10dd2 \
  /tmp/r5i-correction.patch

apply_raw_correction \
  scripts/r5i-correct/r5i-correction-2.patch \
  1319 \
  1a3d25b6a9c6183094a36f2f3e5b17234c10b82f753549322b06da7800c352ea \
  fc9ce761146f44583bff0b6513829ed86df3e7b9

apply_raw_correction \
  scripts/r5i-correct/r5i-correction-3.patch \
  957 \
  92fc5db1bd22973f55af0337949909199c86f11e6ababc66322bd29c55d4b644 \
  09f9fc53d2b9cf5f37028f3f4b6af14e9bfd1c47

bun install --frozen-lockfile
(
  cd backend
  composer install --no-interaction --no-progress --prefer-dist
)

base='da5c8b77f372037ab3b3cf8d8981892e57b86025'
mapfile -t frontend_files < <(git diff --name-only "$base" -- '*.ts' '*.tsx' '*.mjs' '*.json' '*.md' '*.yml' '*.yaml' | while read -r file; do test -f "$file" && printf '%s\n' "$file"; done)
if ((${#frontend_files[@]})); then
  bunx prettier --write "${frontend_files[@]}"
fi
mapfile -t backend_files < <(git diff --name-only "$base" -- 'backend/*.php' 'backend/**/*.php' | sed 's#^backend/##' | while read -r file; do test -f "backend/$file" && printf '%s\n' "$file"; done)
if ((${#backend_files[@]})); then
  (cd backend && vendor/bin/pint "${backend_files[@]}")
fi

bun run audit:r5i
bun test tests/unit/r5i-delivery-settlement.test.ts tests/unit/financial-contracts.test.ts
bun run routes:generate
bun run typecheck

(
  cd backend
  composer audit:r5i
  composer audit:openapi
  composer audit:finance
  composer audit:fulfillment
  php artisan test --filter=R5IDeliverySettlementTest
  php artisan test --filter=FulfillmentLifecycleTest
  php artisan test --filter=R5HFulfillmentCommitmentTest
  php artisan test --filter=PaymentLifecycleTest
  vendor/bin/phpstan analyse --memory-limit=1G
  vendor/bin/pint --parallel --test
)

rm -rf scripts/r5i-parts scripts/r5i-full-parts scripts/r5i-correct
rm -f scripts/run-r5i-final-executor.sh
rm -f .github/workflows/r5i-source-export.yml
rm -f .github/workflows/r5i-product-executor.yml
rm -f .github/workflows/r5i-final-executor.yml
find backend -maxdepth 1 -type f -name '*audit.json' -delete
rm -rf backend/.phpstan.cache

git config user.name "Rosta R5I Executor"
git config user.email "actions@users.noreply.github.com"
git add -A
git diff --cached --check
git diff --cached --stat
git commit -m "R5I implement delivery settlement release and roastery payouts"
git push origin HEAD:program/r5i-delivery-settlement-payouts
