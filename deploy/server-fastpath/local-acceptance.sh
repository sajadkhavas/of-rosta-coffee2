#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
HEAD_SHA="$(git -C "$ROOT_DIR" rev-parse HEAD)"
EVIDENCE_ROOT="${ROSTA_LOCAL_EVIDENCE_DIR:-$ROOT_DIR/.server-ready/local-acceptance-$HEAD_SHA}"

fail() {
  printf '[rosta-local-acceptance] ERROR: %s\n' "$*" >&2
  exit 1
}

[[ "$HEAD_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Exact Git SHA is required"
test -z "$(git -C "$ROOT_DIR" status --porcelain --untracked-files=all)"   || fail "Worktree must be clean before Local Acceptance"

mkdir -p "$EVIDENCE_ROOT"
chmod 700 "$EVIDENCE_ROOT"

echo "============================================================"
echo " ROSTA LOCAL / VSCODE ACCEPTANCE"
echo " candidate=$HEAD_SHA"
echo "============================================================"

echo
echo "=== L1 / Integrated Browser Acceptance ==="
ROSTA_LOCAL_BROWSER_EVIDENCE_DIR="$EVIDENCE_ROOT/browser"   bash "$ROOT_DIR/deploy/server-fastpath/local-browser-acceptance.sh"

echo
echo "=== L2 / Full Staging Rehearsal ==="
EVIDENCE_DIR="$EVIDENCE_ROOT/rehearsal" ROSTA_ALLOW_REHEARSAL=true   bash "$ROOT_DIR/deploy/staging/rehearsal.sh"

echo
echo "=== L3 / Final Source Integrity ==="
git -C "$ROOT_DIR" diff --check
test -z "$(git -C "$ROOT_DIR" status --porcelain --untracked-files=all)"   || fail "Full Local Acceptance left unexpected source changes"

sha256sum   "$ROOT_DIR/package.json"   "$ROOT_DIR/bun.lock"   "$ROOT_DIR/backend/composer.json"   "$ROOT_DIR/backend/composer.lock"   > "$EVIDENCE_ROOT/final-locks.sha256"

cat > "$EVIDENCE_ROOT/result.txt" <<EOF
local_workstation_accepted=ready
candidate_sha=$HEAD_SHA
manual_ui_smoke=required_before_server_ready_tag
EOF
chmod 600 "$EVIDENCE_ROOT/result.txt"

echo
echo "AUTOMATED LOCAL ACCEPTANCE PASSED"
echo "local_workstation_accepted=ready"
echo "candidate_sha=$HEAD_SHA"
echo
echo "Manual Buyer/Seller/Admin/Cafe-B2B UI smoke is still required before"
echo "creating the immutable rosta-server-ready-* tag."
