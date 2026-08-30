#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/production/lib.sh
source "$SCRIPT_DIR/lib.sh"

for command in docker curl python3 grep; do
  require_command "$command"
done

load_production_environment
assert_production_contract

REPORT_DIR="$ROSTA_STATE_DIR/reports"
REPORT_FILE="$REPORT_DIR/latest.json"
TMP_REPORT="$REPORT_FILE.tmp"
mkdir -p "$REPORT_DIR"
chmod 700 "$REPORT_DIR"

fail_with_report() {
  local reason="$1"
  python3 - "$TMP_REPORT" "$ROSTA_IMAGE_TAG" "$reason" <<'PY'
import json, sys, datetime
path, sha, reason = sys.argv[1:]
payload = {
    "accepted": False,
    "release_sha": sha,
    "checked_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
    "reason": reason,
}
with open(path, "w", encoding="utf-8") as handle:
    json.dump(payload, handle, ensure_ascii=False, indent=2)
    handle.write("\n")
PY
  chmod 600 "$TMP_REPORT"
  mv "$TMP_REPORT" "$REPORT_FILE"
  fail "$reason"
}

log "Checking production service topology"
for service in mysql redis api api-web worker scheduler frontend edge; do
  if ! rosta_compose ps --status running "$service" | grep -q "$service"; then
    fail_with_report "service_not_running:$service"
  fi
done

log "Checking strict application readiness"
if ! rosta_compose exec -T api php artisan rosta:readiness --strict --json > "$REPORT_DIR/readiness.json"; then
  fail_with_report "strict_readiness_failed"
fi
chmod 600 "$REPORT_DIR/readiness.json"
python3 - "$REPORT_DIR/readiness.json" <<'PY'
import json, sys
payload = json.load(open(sys.argv[1], encoding="utf-8"))
if payload.get("ready") is not True:
    raise SystemExit("strict readiness did not report ready=true")
PY

site="https://${PRODUCTION_SITE_DOMAIN}"
api="https://${PRODUCTION_API_DOMAIN}"

log "Checking public TLS edge and canonical endpoints"
curl --fail --silent --show-error --max-time "${ROSTA_ACCEPTANCE_TIMEOUT_SECONDS:-240}" \
  --dump-header "$REPORT_DIR/home.headers" "$site/" > "$REPORT_DIR/home.html" \
curl --fail --silent --show-error --max-time "${ROSTA_ACCEPTANCE_TIMEOUT_SECONDS:-240}" \
  --dump-header "$REPORT_DIR/robots.headers" "$site/robots.txt" > "$REPORT_DIR/robots.txt"
curl --fail --silent --show-error --max-time "${ROSTA_ACCEPTANCE_TIMEOUT_SECONDS:-240}" \
  --dump-header "$REPORT_DIR/api.headers" "$api/api/v1/health/live" > "$REPORT_DIR/api-live.json"

for header in strict-transport-security content-security-policy x-content-type-options x-frame-options; do
  grep -Eiq "^${header}:" "$REPORT_DIR/home.headers" || fail_with_report "missing_security_header:$header"
done

if grep -Eiq 'disallow[[:space:]]*:[[:space:]]*/([[:space:]]|$)' "$REPORT_DIR/robots.txt"; then
  fail_with_report "production_robots_blocks_root"
fi

python3 - "$REPORT_DIR/api-live.json" <<'PY'
import json, sys
payload = json.load(open(sys.argv[1], encoding="utf-8"))
if payload.get("data", {}).get("status") != "ok":
    raise SystemExit("API liveness did not return canonical ok status")
PY

if grep -R -E 'APP_KEY=|DB_PASSWORD=|MYSQL_ROOT_PASSWORD=|REDIS_PASSWORD=|KAVENEGAR_API_KEY=|PAYMENT_MERCHANT_ID=|S3_SECRET_ACCESS_KEY=' "$REPORT_DIR" >/dev/null 2>&1; then
  fail_with_report "secret_shaped_material_in_acceptance_evidence"
fi

python3 - "$TMP_REPORT" "$ROSTA_IMAGE_TAG" <<'PY'
import json, sys, datetime
path, sha = sys.argv[1:]
payload = {
    "accepted": True,
    "release_sha": sha,
    "checked_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
    "checks": {
        "services_running": True,
        "strict_readiness": True,
        "public_tls_edge": True,
        "security_headers": True,
        "robots_indexable": True,
        "api_liveness": True,
        "evidence_redacted": True,
    },
}
with open(path, "w", encoding="utf-8") as handle:
    json.dump(payload, handle, ensure_ascii=False, indent=2)
    handle.write("\n")
PY
chmod 600 "$TMP_REPORT"
mv "$TMP_REPORT" "$REPORT_FILE"
log "Production acceptance passed for $ROSTA_IMAGE_TAG"
