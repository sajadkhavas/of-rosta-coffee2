#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

for command in php composer docker; do
  if ! command -v "$command" >/dev/null 2>&1; then
    echo "Missing required command: $command" >&2
    exit 1
  fi
done

if ! docker compose version >/dev/null 2>&1; then
  echo "Docker Compose v2 is required." >&2
  exit 1
fi

mkdir -p \
  bootstrap/cache \
  storage/app/private \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

docker compose up -d mysql redis

composer install --no-interaction --prefer-dist

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
  php artisan key:generate --force
fi

php artisan storage:link >/dev/null 2>&1 || true

echo "Waiting for MySQL and Redis health checks..."
for attempt in {1..60}; do
  mysql_state="$(docker inspect --format='{{.State.Health.Status}}' "$(docker compose ps -q mysql)" 2>/dev/null || true)"
  redis_state="$(docker inspect --format='{{.State.Health.Status}}' "$(docker compose ps -q redis)" 2>/dev/null || true)"

  if [[ "$mysql_state" == "healthy" && "$redis_state" == "healthy" ]]; then
    break
  fi

  if [[ "$attempt" -eq 60 ]]; then
    echo "Dependencies did not become healthy." >&2
    docker compose ps
    exit 1
  fi

  sleep 2
done

php artisan migrate --seed --force
composer check

echo
echo "Rosta backend is ready."
echo "API:      php artisan serve --host=127.0.0.1 --port=8000"
echo "Queue:    php artisan horizon"
echo "Health:   http://127.0.0.1:8000/api/v1/health/live"
echo "Readiness:http://127.0.0.1:8000/api/v1/health/ready"
