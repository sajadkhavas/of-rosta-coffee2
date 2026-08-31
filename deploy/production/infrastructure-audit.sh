#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"

fail() {
  printf '[ps8c-infrastructure] ERROR: %s\n' "$*" >&2
  exit 1
}

for command in docker python3 grep git; do
  command -v "$command" >/dev/null 2>&1 || fail "Missing command: $command"
done
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required"

required=(
  "$SCRIPT_DIR/docker-compose.yml"
  "$SCRIPT_DIR/Caddyfile"
  "$SCRIPT_DIR/frontend.env.example"
  "$SCRIPT_DIR/backend.env.example"
  "$SCRIPT_DIR/preflight.sh"
  "$SCRIPT_DIR/deploy.sh"
  "$SCRIPT_DIR/acceptance.sh"
  "$SCRIPT_DIR/backup.sh"
  "$SCRIPT_DIR/restore-backup.sh"
  "$SCRIPT_DIR/rollback.sh"
  "$SCRIPT_DIR/rehearsal.sh"
  "$ROOT_DIR/Dockerfile.production"
  "$ROOT_DIR/backend/Dockerfile.production"
  "$ROOT_DIR/.dockerignore"
)
for path in "${required[@]}"; do
  [[ -f "$path" ]] || fail "Missing infrastructure acceptance input: $path"
done

TMP_DIR="$(mktemp -d)"
cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

set -a
# shellcheck disable=SC1091
source "$SCRIPT_DIR/frontend.env.example"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/backend.env.example"
set +a

export ROSTA_IMAGE_TAG="0000000000000000000000000000000000000000"
export ROSTA_BACKEND_ENV_FILE="$SCRIPT_DIR/backend.env.example"
export ACME_EMAIL="ps8c-audit@example.invalid"
export APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="
export DB_PASSWORD="ps8c-audit-db"
export MYSQL_ROOT_PASSWORD="ps8c-audit-root"
export REDIS_PASSWORD="ps8c-audit-redis"
export ROSTA_CARRIER_WEBHOOK_SECRET="ps8c-audit-carrier"

COMPOSE_JSON="$TMP_DIR/compose.json"
docker compose \
  --project-name rosta-ps8c-contract \
  --env-file "$SCRIPT_DIR/frontend.env.example" \
  -f "$SCRIPT_DIR/docker-compose.yml" \
  config --format json > "$COMPOSE_JSON"

python3 - "$COMPOSE_JSON" "${PS8C_AUDIT_OUTPUT:-}" <<'PY'
import datetime
import json
import pathlib
import sys

compose_path, output_path = sys.argv[1:]
with open(compose_path, encoding='utf-8') as handle:
    compose = json.load(handle)

services = compose.get('services', {})
networks = compose.get('networks', {})
volumes = compose.get('volumes', {})
expected_services = {'mysql', 'redis', 'api', 'api-web', 'worker', 'scheduler', 'frontend', 'edge'}
release = '0000000000000000000000000000000000000000'


def network_names(service):
    value = service.get('networks', {})
    if isinstance(value, dict):
        return set(value.keys())
    if isinstance(value, list):
        return set(value)
    return set()


def has_healthy_dependency(service, dependency):
    depends = service.get('depends_on', {})
    item = depends.get(dependency) if isinstance(depends, dict) else None
    return isinstance(item, dict) and item.get('condition') == 'service_healthy'


def has_volume_target(service, target, read_only=None):
    for item in service.get('volumes', []) or []:
        if not isinstance(item, dict) or item.get('target') != target:
            continue
        if read_only is None:
            return True
        return bool(item.get('read_only', False)) is read_only
    return False

rules = {}
rules['exact production service set'] = set(services.keys()) == expected_services
rules['backend network is externally isolated'] = bool(networks.get('backend', {}).get('internal'))
rules['stateful services are backend-only'] = all(
    network_names(services[name]) == {'backend'} for name in ('mysql', 'redis')
)
rules['edge is attached only to edge network'] = network_names(services['edge']) == {'edge'}
rules['api processes have explicit egress plus private backend'] = all(
    network_names(services[name]) == {'backend', 'egress'} for name in ('api', 'worker', 'scheduler')
)
rules['frontend and api web bridge only edge and backend'] = all(
    network_names(services[name]) == {'backend', 'edge'} for name in ('api-web', 'frontend')
)
rules['only edge publishes host ports'] = bool(services['edge'].get('ports')) and all(
    not services[name].get('ports') for name in expected_services - {'edge'}
)
rules['all long lived services restart unless stopped'] = all(
    services[name].get('restart') == 'unless-stopped' for name in expected_services
)
rules['all long lived services forbid privilege escalation'] = all(
    'no-new-privileges:true' in (services[name].get('security_opt') or [])
    for name in expected_services
)
rules['no privileged host namespace or device escape'] = all(
    not services[name].get('privileged', False)
    and services[name].get('network_mode') != 'host'
    and services[name].get('pid') != 'host'
    and services[name].get('ipc') != 'host'
    and not services[name].get('devices')
    and not services[name].get('cap_add')
    for name in expected_services
)
rules['critical startup dependencies are health gated'] = (
    has_healthy_dependency(services['api'], 'mysql')
    and has_healthy_dependency(services['api'], 'redis')
    and has_healthy_dependency(services['api-web'], 'api')
    and has_healthy_dependency(services['frontend'], 'api-web')
    and has_healthy_dependency(services['edge'], 'frontend')
    and has_healthy_dependency(services['edge'], 'api-web')
)
rules['critical services define healthchecks'] = all(
    bool(services[name].get('healthcheck'))
    for name in ('mysql', 'redis', 'api', 'api-web', 'frontend', 'edge')
)
rules['application images are keyed by exact release sha'] = (
    services['api'].get('image') == f'rosta-api:{release}'
    and services['worker'].get('image') == f'rosta-api:{release}'
    and services['scheduler'].get('image') == f'rosta-api:{release}'
    and services['api-web'].get('image') == f'rosta-api-web:{release}'
    and services['frontend'].get('image') == f'rosta-frontend:{release}'
)
rules['external runtime images are explicit non-latest tags'] = all(
    isinstance(services[name].get('image'), str)
    and ':' in services[name]['image']
    and not services[name]['image'].endswith(':latest')
    for name in ('mysql', 'redis', 'edge')
)
rules['durable service volumes are declared'] = {
    'mysql-data', 'redis-data', 'app-storage', 'caddy-data', 'caddy-config'
}.issubset(set(volumes.keys()))
rules['database and redis use durable volume targets'] = (
    has_volume_target(services['mysql'], '/var/lib/mysql')
    and has_volume_target(services['redis'], '/data')
)
rules['application storage is shared and web mount is read only'] = (
    has_volume_target(services['api'], '/var/www/html/storage')
    and has_volume_target(services['worker'], '/var/www/html/storage')
    and has_volume_target(services['scheduler'], '/var/www/html/storage')
    and has_volume_target(services['api-web'], '/var/www/html/storage', True)
)
rules['caddy config bind is read only and state is durable'] = (
    has_volume_target(services['edge'], '/etc/caddy/Caddyfile', True)
    and has_volume_target(services['edge'], '/data')
    and has_volume_target(services['edge'], '/config')
)
rules['worker has bounded retry timeout and lifetime'] = all(
    marker in ' '.join(str(v) for v in (services['worker'].get('command') or []))
    for marker in ('--tries=3', '--timeout=90', '--max-time=3600')
)
rules['production compose never uses staging dockerfile'] = all(
    'Dockerfile.staging' not in json.dumps(services[name].get('build', {}))
    for name in expected_services
)

failed = [name for name, passed in rules.items() if not passed]
report = {
    'generated_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
    'marker': 'ROSTA_PS8C_INFRASTRUCTURE_TOPOLOGY_CLEAN' if not failed else None,
    'passed': not failed,
    'rules': rules,
    'scope': 'source_and_ci_only',
    'production_runtime_claimed': False,
    'external_image_digest_pinning_claimed': False,
}
if output_path:
    path = pathlib.Path(output_path)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

if failed:
    print('PS8C infrastructure topology audit failed.', file=sys.stderr)
    for name in failed:
        print(f'- {name}', file=sys.stderr)
    raise SystemExit(1)

print(f'ROSTA_PS8C_INFRASTRUCTURE_TOPOLOGY_CLEAN ({len(rules)} rules)')
PY

for dockerfile in "$ROOT_DIR/Dockerfile.production" "$ROOT_DIR/backend/Dockerfile.production"; do
  if grep -Eq '^FROM[[:space:]]+[^[:space:]]*:latest([[:space:]]|$)' "$dockerfile"; then
    fail "Mutable latest base image detected in $dockerfile"
  fi
done

grep -q '^USER node$' "$ROOT_DIR/Dockerfile.production" \
  || fail "Frontend runtime must run as the node user"
grep -q '^USER www-data$' "$ROOT_DIR/backend/Dockerfile.production" \
  || fail "Backend application runtime must run as www-data"
grep -q '^\.env$' "$ROOT_DIR/.dockerignore" \
  || fail "Root .env must be excluded from Docker build context"
grep -q '^backend/\.env$' "$ROOT_DIR/.dockerignore" \
  || fail "Backend .env must be excluded from Docker build context"
grep -q 'docker inspect --format' "$SCRIPT_DIR/acceptance.sh" \
  || fail "Runtime acceptance must inspect actual container image identity"
grep -q 'sha256sum -c' "$SCRIPT_DIR/restore-backup.sh" \
  || fail "Restore must verify the backup checksum before destructive mutation"
grep -q 'backup_database pre-restore' "$SCRIPT_DIR/restore-backup.sh" \
  || fail "Restore must create a mandatory pre-restore safety backup"
grep -q 'ROSTA_CONFIRM_PRODUCTION_ROLLBACK' "$SCRIPT_DIR/rollback.sh" \
  || fail "Rollback must require explicit operator confirmation"
grep -q 'ROSTA_CONFIRM_PRODUCTION_DEPLOY' "$SCRIPT_DIR/deploy.sh" \
  || fail "Deploy must require explicit operator confirmation"

if grep -R -nE '(^|[^A-Za-z])(:latest|latest:)' "$SCRIPT_DIR" --include='*.yml' --include='*.yaml' --include='Caddyfile' >/dev/null 2>&1; then
  fail "Mutable latest image reference detected in executable production topology"
fi

if grep -R -nE 'docker\.sock|network_mode:[[:space:]]*host|privileged:[[:space:]]*true' "$SCRIPT_DIR" \
  --include='*.yml' --include='*.yaml' >/dev/null 2>&1; then
  fail "Forbidden host-level container privilege detected"
fi

git -C "$ROOT_DIR" diff --check
printf 'PS8C production infrastructure audit passed.\n'
