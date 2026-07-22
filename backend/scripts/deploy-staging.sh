#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

if [[ ! -f .env.staging ]]; then
  echo "Missing backend/.env.staging. Copy .env.staging.example and set real secrets." >&2
  exit 1
fi

if [[ ! -s composer.lock ]]; then
  echo "backend/composer.lock is required before staging deployment." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env.staging
set +a

required=(
  APP_KEY
  DB_DATABASE
  DB_USERNAME
  DB_PASSWORD
  MYSQL_ROOT_PASSWORD
  REDIS_PASSWORD
)

for name in "${required[@]}"; do
  value="${!name:-}"
  if [[ -z "$value" || "$value" == "CHANGE_ME" ]]; then
    echo "$name must contain a real staging value." >&2
    exit 1
  fi
done

if [[ "${APP_DEBUG:-true}" != "false" ]]; then
  echo "APP_DEBUG must be false on staging." >&2
  exit 1
fi

compose=(docker compose --env-file .env.staging -f docker-compose.staging.yml)

"${compose[@]}" config --quiet
"${compose[@]}" build --pull app web
"${compose[@]}" up -d --wait mysql redis
"${compose[@]}" run --rm app php artisan migrate --force --no-interaction
"${compose[@]}" up -d --wait app worker scheduler web
"${compose[@]}" run --rm app php artisan optimize --no-interaction

api_port="${ROSTA_API_PORT:-8080}"
health_url="http://127.0.0.1:${api_port}/api/v1/health/ready"

for attempt in $(seq 1 30); do
  if curl --fail --silent --show-error --max-time 5 "$health_url" >/dev/null; then
    echo "Rosta staging API is ready: $health_url"
    exit 0
  fi
  sleep 2
done

"${compose[@]}" ps
"${compose[@]}" logs --tail=120 app web worker scheduler

echo "Staging readiness check failed." >&2
exit 1
