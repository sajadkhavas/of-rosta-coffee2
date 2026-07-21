#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required." >&2
  exit 1
fi

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

echo "Waiting for MySQL and Redis..."
for attempt in {1..60}; do
  mysql_id="$(docker compose ps -q mysql)"
  redis_id="$(docker compose ps -q redis)"
  mysql_state="$(docker inspect --format='{{.State.Health.Status}}' "$mysql_id" 2>/dev/null || true)"
  redis_state="$(docker inspect --format='{{.State.Health.Status}}' "$redis_id" 2>/dev/null || true)"

  if [[ "$mysql_state" == "healthy" && "$redis_state" == "healthy" ]]; then
    break
  fi

  if [[ "$attempt" -eq 60 ]]; then
    echo "MySQL or Redis did not become healthy." >&2
    docker compose ps
    exit 1
  fi

  sleep 2
done

docker compose build api
docker compose run --rm api composer install --no-interaction --prefer-dist --no-progress

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
  docker compose run --rm api php artisan key:generate --force
fi

docker compose run --rm api php artisan storage:link >/dev/null 2>&1 || true
docker compose run --rm api php artisan migrate --seed --force
docker compose run --rm api composer check

docker compose up -d api worker scheduler

echo "Waiting for the API health check..."
for attempt in {1..40}; do
  api_id="$(docker compose ps -q api)"
  api_state="$(docker inspect --format='{{.State.Health.Status}}' "$api_id" 2>/dev/null || true)"

  if [[ "$api_state" == "healthy" ]]; then
    break
  fi

  if [[ "$attempt" -eq 40 ]]; then
    echo "The API did not become healthy." >&2
    docker compose logs --tail=100 api worker scheduler
    exit 1
  fi

  sleep 2
done

echo
echo "Rosta backend is ready."
echo "API:       http://localhost:8000"
echo "Liveness:  http://localhost:8000/api/v1/health/live"
echo "Readiness: http://localhost:8000/api/v1/health/ready"
echo "Logs:      docker compose logs -f api worker scheduler"
