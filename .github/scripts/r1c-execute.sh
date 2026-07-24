#!/usr/bin/env bash
set -Eeuo pipefail

EXPECTED_HEAD="ba954ad86f348151510bc25d608b3bc722d7dc99"
TARGET_BRANCH="program/r1c-route-generation"
EVIDENCE_DIR="${EVIDENCE_DIR:-/tmp/rosta-r1c-evidence}"
mkdir -p "$EVIDENCE_DIR"

{
  echo "head=$(git rev-parse HEAD)"
  echo "tree=$(git rev-parse 'HEAD^{tree}')"
  echo "node=$(node --version)"
  echo "bun=$(bun --version)"
  git status --short --branch
} | tee "$EVIDENCE_DIR/snapshot.txt"
test "$(git rev-parse HEAD)" = "$EXPECTED_HEAD"
test "$(node -p 'process.versions.node.split(".")[0]')" = "22"
test "$(bun --version)" = "1.2.22"
test -z "$(git status --porcelain)"
rm -rf node_modules
bun install --frozen-lockfile 2>&1 | tee "$EVIDENCE_DIR/install.log"
test "$(node -p "require('./node_modules/@tanstack/router-cli/package.json').version")" = "1.167.21"
bun run typecheck 2>&1 | tee "$EVIDENCE_DIR/typecheck-before.log"

cp src/routeTree.gen.ts "$EVIDENCE_DIR/routeTree-before.ts"
./node_modules/.bin/tsr generate 2>&1 | tee "$EVIDENCE_DIR/generate-run-1.log"
sha256sum src/routeTree.gen.ts | tee "$EVIDENCE_DIR/hash-run-1.txt"
cp src/routeTree.gen.ts "$EVIDENCE_DIR/routeTree-run-1.ts"
./node_modules/.bin/tsr generate 2>&1 | tee "$EVIDENCE_DIR/generate-run-2.log"
sha256sum src/routeTree.gen.ts | tee "$EVIDENCE_DIR/hash-run-2.txt"
cmp "$EVIDENCE_DIR/hash-run-1.txt" "$EVIDENCE_DIR/hash-run-2.txt"
cmp "$EVIDENCE_DIR/routeTree-run-1.ts" src/routeTree.gen.ts
for token in \
  AdminFinanceRouteImport \
  AdminOperationsRouteImport \
  PanelRouteImport \
  PanelManageRouteImport \
  RobotsDottxtRouteImport \
  SitemapDotxmlRouteImport \
  /admin/finance \
  /admin/operations \
  /panel/manage \
  /robots.txt \
  /sitemap.xml; do
  grep -Fq "$token" src/routeTree.gen.ts
 done

# Apply only the reviewed router/audit migration.
git show origin/automation/r1a-executor:.github/scripts/r1c-integrate-routes.py > /tmp/r1c-integrate-routes.py
python /tmp/r1c-integrate-routes.py
test ! -e src/routeTree.phase17.ts
grep -q 'from "./routeTree.gen"' src/router.tsx
bun run routes:generate 2>&1 | tee "$EVIDENCE_DIR/generate-package-script.log"
cmp "$EVIDENCE_DIR/routeTree-run-1.ts" src/routeTree.gen.ts
git diff --check

bun run typecheck 2>&1 | tee "$EVIDENCE_DIR/typecheck-after.log"
bun run audit:admin-finance 2>&1 | tee "$EVIDENCE_DIR/audit-admin-finance.log"
bun run audit:seller-operations 2>&1 | tee "$EVIDENCE_DIR/audit-seller-operations.log"
bun run audit:admin-operations 2>&1 | tee "$EVIDENCE_DIR/audit-admin-operations.log"
bun run audit:phase20 2>&1 | tee "$EVIDENCE_DIR/audit-phase20.log"
bun run audit:phase21 2>&1 | tee "$EVIDENCE_DIR/audit-phase21.log"
bun run audit:phase22 2>&1 | tee "$EVIDENCE_DIR/audit-phase22.log"
bun run test:unit 2>&1 | tee "$EVIDENCE_DIR/unit.log"

set +e
bun run audit:phase17 > "$EVIDENCE_DIR/audit-phase17.log" 2>&1
phase17_status=$?
set -e
echo "$phase17_status" > "$EVIDENCE_DIR/audit-phase17.exit"
node --input-type=module <<'NODE'
import fs from 'node:fs';
const report = JSON.parse(fs.readFileSync('frontend-phase17-audit.json', 'utf8'));
const failed = report.gates.filter((gate) => !gate.passed).map((gate) => gate.name);
const routeGate = report.gates.find((gate) => gate.name === 'active_route_tree_current');
if (!routeGate?.passed) throw new Error('Generated route-tree gate did not pass.');
if (JSON.stringify(failed) !== JSON.stringify(['deterministic_backend_dependencies'])) {
  throw new Error(`Unexpected Phase 17 failures: ${JSON.stringify(failed)}`);
}
NODE

expected=$'package.json\nscripts/audit-admin-finance.mjs\nscripts/audit-admin-operations.mjs\nscripts/audit-phase17-release-baseline.mjs\nscripts/audit-phase20-completion.mjs\nscripts/audit-seller-operations.mjs\nsrc/routeTree.gen.ts\nsrc/routeTree.phase17.ts\nsrc/router.tsx'
changed="$(git diff --name-only | sort)"
printf '%s\n' "$changed" | tee "$EVIDENCE_DIR/changed-files.txt"
test "$changed" = "$expected"
test -z "$(git diff --name-only -- backend deploy .github public tests 2>/dev/null || true)"
test -z "$(git diff --name-only -- bun.lock)"
git diff --stat | tee "$EVIDENCE_DIR/diff-stat.txt"
git config user.name 'Rosta Program Automation'
git config user.email 'actions@users.noreply.github.com'
git add package.json scripts src/router.tsx src/routeTree.gen.ts src/routeTree.phase17.ts
git commit -m 'refactor(frontend): activate generated TanStack route tree'
git push origin HEAD:"$TARGET_BRANCH"
git rev-parse HEAD | tee "$EVIDENCE_DIR/result-commit.txt"
echo 'ROSTA_R1C_ROUTE_GENERATION_COMPLETE' | tee "$EVIDENCE_DIR/result.txt"
