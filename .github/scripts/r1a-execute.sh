#!/usr/bin/env bash
set -Eeuo pipefail

EXPECTED_HEAD="cc2f0238e8ce7c09c0702b3095ac8ab75936318b"
TARGET_BRANCH="program/r1a-dependency-contract"
EVIDENCE_DIR="${EVIDENCE_DIR:-/tmp/rosta-r1a-evidence}"
export PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
mkdir -p "$EVIDENCE_DIR"

record_state() {
  {
    echo "branch=$(git branch --show-current)"
    echo "head=$(git rev-parse HEAD)"
    echo "tree=$(git rev-parse 'HEAD^{tree}')"
    echo "node=$(node --version)"
    echo "bun=$(bun --version)"
    git status --short --branch
  } | tee "$EVIDENCE_DIR/starting-state.txt"
}

record_state
test "$(git rev-parse HEAD)" = "$EXPECTED_HEAD"
test "$(node -p 'process.versions.node.split(".")[0]')" = "22"
test "$(bun --version)" = "1.2.22"
test -z "$(git status --porcelain)"
sha256sum package.json bun.lock src/lib/animations.ts | tee "$EVIDENCE_DIR/hashes-start.txt"

rm -rf node_modules
/usr/bin/time -f 'elapsed=%e' bun install --frozen-lockfile 2>&1 | tee "$EVIDENCE_DIR/initial-frozen-install.log"

node --input-type=module <<'NODE' | tee "$EVIDENCE_DIR/initial-installed-versions.json"
import fs from 'node:fs';
const read = (name) => {
  const file = `node_modules/${name}/package.json`;
  if (!fs.existsSync(file)) return null;
  return JSON.parse(fs.readFileSync(file, 'utf8')).version;
};
console.log(JSON.stringify({
  '@playwright/test': read('@playwright/test'),
  playwright: read('playwright'),
  'axe-core': read('axe-core'),
  three: read('three'),
  '@types/three': read('@types/three'),
  lenis: read('lenis'),
  '@studio-freight/lenis': read('@studio-freight/lenis'),
  '@tanstack/zod-adapter': read('@tanstack/zod-adapter'),
  'react-day-picker': read('react-day-picker'),
  'react-resizable-panels': read('react-resizable-panels'),
}, null, 2));
NODE

set +e
bun run typecheck >"$EVIDENCE_DIR/typecheck-baseline.log" 2>&1
baseline_status=$?
set -e
echo "$baseline_status" > "$EVIDENCE_DIR/typecheck-baseline.exit"
baseline_count="$(grep -c 'error TS[0-9]' "$EVIDENCE_DIR/typecheck-baseline.log" || true)"
echo "$baseline_count" > "$EVIDENCE_DIR/typecheck-baseline.count"
grep -oE '^([^:(]+\.(ts|tsx|js|jsx))' "$EVIDENCE_DIR/typecheck-baseline.log" | sort -u > "$EVIDENCE_DIR/typecheck-baseline.files" || true

cat > /tmp/r1a-import-audit.mjs <<'NODE'
import fs from 'node:fs';
import path from 'node:path';
import { builtinModules } from 'node:module';

const root = process.cwd();
const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
const declared = new Set([
  ...Object.keys(pkg.dependencies ?? {}),
  ...Object.keys(pkg.devDependencies ?? {}),
  ...Object.keys(pkg.optionalDependencies ?? {}),
]);
const builtins = new Set([
  ...builtinModules,
  ...builtinModules.map((name) => `node:${name}`),
  'bun:test',
  'bun:sqlite',
  'bun:jsc',
  'bun:ffi',
]);
const extensions = new Set(['.ts', '.tsx', '.js', '.jsx', '.mjs', '.cjs']);
const files = [];
function walk(dir) {
  if (!fs.existsSync(dir)) return;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full);
    else if (extensions.has(path.extname(entry.name))) files.push(full);
  }
}
for (const dir of ['src', 'tests', 'scripts']) walk(path.join(root, dir));
for (const name of [
  'vite.config.ts', 'vite.config.js', 'vite.config.mjs',
  'eslint.config.js', 'eslint.config.mjs', 'eslint.config.cjs',
  'postcss.config.js', 'tailwind.config.ts',
]) {
  const full = path.join(root, name);
  if (fs.existsSync(full)) files.push(full);
}
const specs = new Map();
const patterns = [
  /(?:import|export)\s+(?:[^'"`]*?\s+from\s+)?["']([^"']+)["']/g,
  /import\(\s*["']([^"']+)["']\s*\)/g,
  /require\(\s*["']([^"']+)["']\s*\)/g,
];
for (const file of files) {
  const source = fs.readFileSync(file, 'utf8');
  for (const pattern of patterns) {
    pattern.lastIndex = 0;
    for (const match of source.matchAll(pattern)) {
      const spec = match[1];
      if (
        !spec ||
        spec.startsWith('.') ||
        spec.startsWith('/') ||
        spec.startsWith('@/') ||
        spec.startsWith('virtual:') ||
        spec.startsWith('#') ||
        builtins.has(spec)
      ) continue;
      const top = spec.startsWith('@')
        ? spec.split('/').slice(0, 2).join('/')
        : spec.split('/')[0];
      if (!specs.has(top)) specs.set(top, new Set());
      specs.get(top).add(path.relative(root, file));
    }
  }
}
const imported = [...specs.keys()].sort();
const missing = imported.filter((name) => !declared.has(name));
const report = {
  importedDeclared: imported.filter((name) => declared.has(name)),
  importedUndeclared: missing,
  declaredNotDirectlyImported: [...declared].filter((name) => !specs.has(name)).sort(),
  evidence: Object.fromEntries([...specs].map(([name, set]) => [name, [...set].sort()])),
};
fs.writeFileSync(
  `${process.env.EVIDENCE_DIR}/${process.env.AUDIT_NAME}.json`,
  `${JSON.stringify(report, null, 2)}\n`,
);
console.log(JSON.stringify(report, null, 2));
const expected = JSON.parse(process.env.EXPECTED_MISSING ?? '[]');
if (JSON.stringify(missing) !== JSON.stringify(expected)) {
  console.error(`Expected missing ${JSON.stringify(expected)}, found ${JSON.stringify(missing)}`);
  process.exit(20);
}
NODE

EVIDENCE_DIR="$EVIDENCE_DIR" \
AUDIT_NAME="direct-import-audit-before" \
EXPECTED_MISSING='["@studio-freight/lenis","@tanstack/zod-adapter","axe-core","playwright","three"]' \
node /tmp/r1a-import-audit.mjs

node --input-type=module <<'NODE'
import fs from 'node:fs';
const file = 'src/lib/animations.ts';
const before = fs.readFileSync(file, 'utf8');
const from = 'import Lenis from "@studio-freight/lenis";';
const to = 'import Lenis from "lenis";';
if (!before.includes(from)) throw new Error('Expected legacy Lenis import was not found.');
const after = before.replace(from, to);
if (after === before || after.includes('@studio-freight/lenis')) {
  throw new Error('Lenis import migration did not complete safely.');
}
fs.writeFileSync(file, after);
NODE

proposal=/tmp/rosta-r1a-lock-proposal
rm -rf "$proposal"
mkdir -p "$proposal"
cp package.json bun.lock "$proposal/"
[ ! -f bunfig.toml ] || cp bunfig.toml "$proposal/"

node - "$proposal/package.json" <<'NODE'
const fs = require('node:fs');
const file = process.argv[2];
const pkg = JSON.parse(fs.readFileSync(file, 'utf8'));
pkg.dependencies['@tanstack/zod-adapter'] = '1.167.0';
pkg.dependencies['react-day-picker'] = '9.14.0';
pkg.dependencies['react-resizable-panels'] = '4.6.5';
pkg.dependencies.three = '0.185.1';
pkg.devDependencies['@types/three'] = '0.185.1';
pkg.devDependencies.playwright = '1.61.1';
pkg.devDependencies['axe-core'] = '4.12.1';
pkg.dependencies = Object.fromEntries(
  Object.entries(pkg.dependencies).sort(([a], [b]) => a.localeCompare(b)),
);
pkg.devDependencies = Object.fromEntries(
  Object.entries(pkg.devDependencies).sort(([a], [b]) => a.localeCompare(b)),
);
fs.writeFileSync(file, `${JSON.stringify(pkg, null, 2)}\n`);
NODE

pushd "$proposal" >/dev/null
/usr/bin/time -f 'elapsed=%e' bun install --lockfile-only 2>&1 | tee "$EVIDENCE_DIR/lock-proposal.log"
rm -rf node_modules
bun install --frozen-lockfile 2>&1 | tee "$EVIDENCE_DIR/proposal-frozen-install.log"

node --input-type=module <<'NODE' | tee "$EVIDENCE_DIR/version-proof.json"
import fs from 'node:fs';
const read = (name) => JSON.parse(fs.readFileSync(`node_modules/${name}/package.json`, 'utf8'));
const dayPkg = read('react-day-picker');
const panelsPkg = read('react-resizable-panels');
const adapterPkg = read('@tanstack/zod-adapter');
const routerPkg = read('@tanstack/react-router');
const zodPkg = read('zod');
const threePkg = read('three');
const threeTypesPkg = read('@types/three');
const playwrightPkg = read('playwright');
const playwrightTestPkg = read('@playwright/test');
const axePkg = read('axe-core');
const lenisPkg = read('lenis');
const day = await import('react-day-picker');
const panels = await import('react-resizable-panels');
const adapter = await import('@tanstack/zod-adapter');
const three = await import('three');
const playwright = await import('playwright');
const axeModule = await import('axe-core');
const axe = axeModule.default ?? axeModule;
const lenisModule = await import('lenis');
const proof = {
  versions: {
    'react-day-picker': dayPkg.version,
    'react-resizable-panels': panelsPkg.version,
    '@tanstack/zod-adapter': adapterPkg.version,
    '@tanstack/react-router': routerPkg.version,
    zod: zodPkg.version,
    three: threePkg.version,
    '@types/three': threeTypesPkg.version,
    playwright: playwrightPkg.version,
    '@playwright/test': playwrightTestPkg.version,
    'axe-core': axePkg.version,
    lenis: lenisPkg.version,
  },
  peerDependencies: {
    'react-day-picker': dayPkg.peerDependencies ?? {},
    'react-resizable-panels': panelsPkg.peerDependencies ?? {},
    '@tanstack/zod-adapter': adapterPkg.peerDependencies ?? {},
  },
  exports: {
    DayButton: Boolean(day.DayButton),
    DayPicker: Boolean(day.DayPicker),
    getDefaultClassNames: typeof day.getDefaultClassNames === 'function',
    Group: Boolean(panels.Group),
    Panel: Boolean(panels.Panel),
    Separator: Boolean(panels.Separator),
    fallback: typeof adapter.fallback === 'function',
    zodValidator: typeof adapter.zodValidator === 'function',
    ThreeScene: typeof three.Scene === 'function',
    PlaywrightChromium: Boolean(playwright.chromium),
    AxeSource: typeof axe.source === 'string' && axe.source.length > 1000,
    LenisDefault: typeof lenisModule.default === 'function',
  },
};
console.log(JSON.stringify(proof, null, 2));
const expectedVersions = {
  'react-day-picker': '9.14.0',
  'react-resizable-panels': '4.6.5',
  '@tanstack/zod-adapter': '1.167.0',
  three: '0.185.1',
  '@types/three': '0.185.1',
  playwright: '1.61.1',
  '@playwright/test': '1.61.1',
  'axe-core': '4.12.1',
};
for (const [name, expected] of Object.entries(expectedVersions)) {
  if (proof.versions[name] !== expected) {
    console.error(`${name}: expected ${expected}, got ${proof.versions[name]}`);
    process.exit(31);
  }
}
if (Object.values(proof.exports).some((value) => !value)) process.exit(34);
NODE
popd >/dev/null

cp "$proposal/package.json" package.json
cp "$proposal/bun.lock" bun.lock

EVIDENCE_DIR="$EVIDENCE_DIR" \
AUDIT_NAME="direct-import-audit-after" \
EXPECTED_MISSING='[]' \
node /tmp/r1a-import-audit.mjs

sha256sum package.json bun.lock src/lib/animations.ts | tee "$EVIDENCE_DIR/hashes-before-final-installs.txt"
rm -rf node_modules
/usr/bin/time -f 'elapsed=%e' bun install --frozen-lockfile 2>&1 | tee "$EVIDENCE_DIR/frozen-run-1.log"
sha256sum package.json bun.lock src/lib/animations.ts | tee "$EVIDENCE_DIR/hashes-after-run-1.txt"
rm -rf node_modules
/usr/bin/time -f 'elapsed=%e' bun install --frozen-lockfile 2>&1 | tee "$EVIDENCE_DIR/frozen-run-2.log"
sha256sum package.json bun.lock src/lib/animations.ts | tee "$EVIDENCE_DIR/hashes-after-run-2.txt"
cmp "$EVIDENCE_DIR/hashes-after-run-1.txt" "$EVIDENCE_DIR/hashes-after-run-2.txt"

set +e
bun run typecheck >"$EVIDENCE_DIR/typecheck-final.log" 2>&1
final_status=$?
set -e
echo "$final_status" > "$EVIDENCE_DIR/typecheck-final.exit"
final_count="$(grep -c 'error TS[0-9]' "$EVIDENCE_DIR/typecheck-final.log" || true)"
echo "$final_count" > "$EVIDENCE_DIR/typecheck-final.count"
test "$final_count" -le "$baseline_count"
! grep -q 'src/components/ui/calendar.tsx' "$EVIDENCE_DIR/typecheck-final.log"
! grep -q 'src/components/ui/resizable.tsx' "$EVIDENCE_DIR/typecheck-final.log"
! grep -q "Cannot find module '@tanstack/zod-adapter'" "$EVIDENCE_DIR/typecheck-final.log"
! grep -q "Cannot find module '@studio-freight/lenis'" "$EVIDENCE_DIR/typecheck-final.log"
! grep -q "Cannot find module 'three'" "$EVIDENCE_DIR/typecheck-final.log"
bun run audit:phase22 2>&1 | tee "$EVIDENCE_DIR/audit-phase22.log"

git diff --check
changed="$(git diff --name-only | sort)"
printf '%s\n' "$changed" | tee "$EVIDENCE_DIR/changed-files.txt"
test "$changed" = $'bun.lock\npackage.json\nsrc/lib/animations.ts'
test -z "$(git diff --name-only -- tests scripts backend deploy .github public 2>/dev/null || true)"
test "$(git diff --name-only -- src | sort)" = 'src/lib/animations.ts'
git diff --stat | tee "$EVIDENCE_DIR/diff-stat.txt"

git config user.name 'Rosta Program Automation'
git config user.email 'actions@users.noreply.github.com'
git add package.json bun.lock src/lib/animations.ts
git commit -m 'chore(frontend): reconcile clean dependency contract'
git push origin HEAD:"$TARGET_BRANCH"
git rev-parse HEAD | tee "$EVIDENCE_DIR/result-commit.txt"
echo 'ROSTA_R1A_DEPENDENCY_CONTRACT_COMPLETE' | tee "$EVIDENCE_DIR/result.txt"
