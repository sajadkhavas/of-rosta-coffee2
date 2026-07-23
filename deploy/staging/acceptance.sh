#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=deploy/staging/lib.sh
source "$SCRIPT_DIR/lib.sh"

require_command docker
require_command curl
require_command python3
require_command grep
require_command sha256sum

load_staging_environment
assert_staging_contract

release_tag="${ROSTA_RELEASE_TAG:-${ROSTA_IMAGE_TAG:-unknown}}"
run_id="$(date -u +%Y%m%dT%H%M%SZ)-${release_tag}"
report_dir="$ROSTA_STATE_DIR/reports/$run_id"
results_file="$report_dir/results.tsv"
mkdir -p "$report_dir"
chmod 700 "$report_dir"
: > "$results_file"

timeout_seconds="${ROSTA_ACCEPTANCE_TIMEOUT_SECONDS:-180}"
site_url="https://${STAGING_SITE_DOMAIN}"
api_url="https://${STAGING_API_DOMAIN}/api/v1"
origin="$site_url"

record() {
  local name="$1" status="$2" evidence="$3"
  evidence="${evidence//$'\t'/ }"
  evidence="${evidence//$'\n'/ }"
  printf '%s\t%s\t%s\n' "$name" "$status" "$evidence" >> "$results_file"
  printf '[%s] %s — %s\n' "$status" "$name" "$evidence"
}

run_check() {
  local name="$1" evidence="$2"
  shift 2
  if "$@"; then
    record "$name" PASS "$evidence"
  else
    record "$name" FAIL "$evidence"
  fi
}

json_flag_true() {
  local file="$1" key="$2"
  python3 - "$file" "$key" <<'PY'
import json, sys
path, key = sys.argv[1:]
with open(path, encoding='utf-8') as handle:
    payload = json.load(handle)
value = payload
for segment in key.split('.'):
    value = value[segment]
raise SystemExit(0 if value is True else 1)
PY
}

header_contains() {
  local file="$1" name="$2" needle="$3"
  python3 - "$file" "$name" "$needle" <<'PY'
import sys
path, name, needle = sys.argv[1:]
headers = {}
with open(path, encoding='utf-8', errors='replace') as handle:
    for line in handle:
        if ':' not in line:
            continue
        key, value = line.split(':', 1)
        headers.setdefault(key.strip().lower(), []).append(value.strip())
joined = ','.join(headers.get(name.lower(), []))
raise SystemExit(0 if needle.lower() in joined.lower() else 1)
PY
}

all_services_running() {
  local required=(mysql redis api api-web worker scheduler frontend edge)
  local running
  running="$(rosta_compose ps --services --filter status=running)"
  local service
  for service in "${required[@]}"; do
    grep -qx "$service" <<<"$running" || return 1
  done
}

api_cors_ok() {
  local headers="$report_dir/cors.headers"
  curl --fail --silent --show-error \
    --max-time "$timeout_seconds" \
    --request OPTIONS \
    --header "Origin: $origin" \
    --header 'Access-Control-Request-Method: GET' \
    --header 'Access-Control-Request-Headers: content-type,x-request-id' \
    --dump-header "$headers" \
    --output /dev/null \
    "$api_url/products"
  header_contains "$headers" Access-Control-Allow-Origin "$origin" \
    && header_contains "$headers" Access-Control-Allow-Credentials true
}

secure_cookie_ok() {
  local headers="$report_dir/csrf.headers"
  curl --fail --silent --show-error \
    --max-time "$timeout_seconds" \
    --header "Origin: $origin" \
    --dump-header "$headers" \
    --output /dev/null \
    "https://${STAGING_API_DOMAIN}/sanctum/csrf-cookie"
  header_contains "$headers" Set-Cookie Secure \
    && header_contains "$headers" Set-Cookie SameSite=Lax
}

ssr_home_ok() {
  local body="$report_dir/home.html"
  curl --fail --silent --show-error \
    --max-time "$timeout_seconds" \
    --output "$body" \
    "$site_url/"
  grep -q 'رستا' "$body" \
    && ! grep -Eqi '(server\.unavailable|Application error|Internal Server Error)' "$body"
}

robots_locked() {
  local body="$report_dir/robots.txt"
  curl --fail --silent --show-error \
    --max-time "$timeout_seconds" \
    --output "$body" \
    "$site_url/robots.txt"
  grep -Eqi '^User-agent:[[:space:]]*\*$' "$body" \
    && grep -Eqi '^Disallow:[[:space:]]*/' "$body"
}

security_headers_ok() {
  local headers="$report_dir/site.headers"
  curl --fail --silent --show-error \
    --max-time "$timeout_seconds" \
    --head \
    --dump-header "$headers" \
    --output /dev/null \
    "$site_url/"
  header_contains "$headers" Strict-Transport-Security max-age= \
    && header_contains "$headers" Content-Security-Policy default-src \
    && header_contains "$headers" X-Robots-Tag noindex
}

public_contracts_ok() {
  curl --fail --silent --show-error --max-time "$timeout_seconds" \
    "$api_url/products?per_page=1" > "$report_dir/products.json" \
    && curl --fail --silent --show-error --max-time "$timeout_seconds" \
      "$api_url/roasteries?per_page=1" > "$report_dir/roasteries.json" \
    && curl --fail --silent --show-error --max-time "$timeout_seconds" \
      "$api_url/content?per_page=1" > "$report_dir/content.json"
}

log "Collecting container and application evidence for $release_tag"
rosta_compose ps > "$report_dir/compose-ps.txt"
rosta_compose exec -T api php artisan rosta:readiness --json > "$report_dir/backend-readiness.json" || true
rosta_compose exec -T api php artisan rosta:staging-acceptance --json > "$report_dir/infrastructure-acceptance.json" || true

run_check containers_running "MySQL, Redis, API, workers, SSR frontend and edge are running" all_services_running
run_check backend_readiness "Laravel readiness passes against the deployed schema" \
  json_flag_true "$report_dir/backend-readiness.json" ready
run_check infrastructure_acceptance "MySQL, Redis, queue and R2 real round trips pass" \
  json_flag_true "$report_dir/infrastructure-acceptance.json" accepted
run_check api_ready "Public API readiness endpoint returns HTTP 200" \
  curl --fail --silent --show-error --max-time "$timeout_seconds" --output "$report_dir/api-ready.json" "$api_url/health/ready"
run_check public_contracts "Product, roastery and content APIs respond" public_contracts_ok
run_check ssr_home "Homepage is server rendered without an application error" ssr_home_ok
run_check robots_noindex "Staging robots.txt blocks all crawling" robots_locked
run_check security_headers "TLS edge emits HSTS, CSP and X-Robots-Tag" security_headers_ok
run_check cors_credentials "API CORS allows only the staging frontend with credentials" api_cors_ok
run_check secure_csrf_cookie "Sanctum CSRF cookie is Secure and SameSite=Lax" secure_cookie_ok

python3 - "$results_file" "$report_dir/acceptance.json" "$release_tag" <<'PY'
import datetime as dt
import json
import sys

source, target, release = sys.argv[1:]
checks = []
with open(source, encoding='utf-8') as handle:
    for raw in handle:
        name, status, evidence = raw.rstrip('\n').split('\t', 2)
        checks.append({
            'name': name,
            'passed': status == 'PASS',
            'evidence': evidence,
        })
report = {
    'accepted': all(item['passed'] for item in checks),
    'release': release,
    'generated_at': dt.datetime.now(dt.timezone.utc).isoformat(),
    'checks': checks,
}
with open(target, 'w', encoding='utf-8') as handle:
    json.dump(report, handle, ensure_ascii=False, indent=2)
    handle.write('\n')
raise SystemExit(0 if report['accepted'] else 1)
PY

sha256sum "$report_dir/acceptance.json" > "$report_dir/acceptance.json.sha256"
cp "$report_dir/acceptance.json" "$ROSTA_STATE_DIR/reports/latest.json"
cp "$report_dir/acceptance.json.sha256" "$ROSTA_STATE_DIR/reports/latest.json.sha256"
log "Acceptance evidence: $report_dir/acceptance.json"
