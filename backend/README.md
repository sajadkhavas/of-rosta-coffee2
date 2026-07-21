# Rosta Backend

Security-first Laravel API for the Rosta whole-bean coffee marketplace.

## Permanent boundaries

- Only whole coffee beans are supported. Grind state is invalid in every request, model, migration and response.
- Every customer order belongs to exactly one roastery.
- Laravel is authoritative for identity, ownership, price, stock, roast batch, delivery, discounts, totals, order state and payment truth.
- Browser data and payment callback query parameters are untrusted.
- SMS and payment providers are disabled until real credentials and approved callback settings are supplied.
- The frozen frontend contract is `docs/openapi/rosta-v1-phase6.yaml`.

## Framework decision

The backend targets Laravel 13 on PHP 8.3. Laravel 11 reached end of security support before this backend was created, so it is not used for a new production system.

The project uses Laravel's standard Redis queue worker. Horizon is intentionally not installed until an official Horizon release supports Laravel 13; queue processing does not wait for the dashboard package.

## Requirement

Only Docker Desktop with Compose v2 is required. On Windows, run the commands from WSL or Git Bash.

The PHP 8.3 runtime, Composer, required PHP extensions, MySQL 8.4, Redis 7.4, queue worker and scheduler run inside Docker.

## One-command setup

```bash
cd backend
chmod +x scripts/setup.sh
./scripts/setup.sh
```

The setup script:

1. creates the required Laravel runtime directories;
2. copies `.env.example` to `.env` when needed;
3. starts MySQL and Redis and waits for healthy status;
4. builds the reproducible PHP runtime image;
5. installs Composer dependencies into a shared Docker volume;
6. generates `APP_KEY`;
7. runs migrations and the local seeder;
8. runs dependency audit, business-contract audit, PHPUnit, Larastan and Pint;
9. starts the API, Redis queue worker and scheduler;
10. waits until the API health check is green.

The first successful installation creates `composer.lock`. Commit that file immediately so every later install resolves the exact same dependency graph.

## Local addresses

Use `localhost` consistently for the frontend and API so Sanctum session cookies remain first-party during local development.

- Frontend: `http://localhost:5173`
- API: `http://localhost:8000`
- Liveness: `http://localhost:8000/api/v1/health/live`
- Readiness: `http://localhost:8000/api/v1/health/ready`

The frontend should use:

```env
VITE_API_URL=http://localhost:8000/api/v1
```

## Daily commands

From the `backend` directory:

```bash
docker compose up -d api worker scheduler mysql redis
docker compose logs -f api worker scheduler
docker compose run --rm api composer check
docker compose run --rm api php artisan migrate
docker compose down
```

From the repository root:

```bash
bun run backend:setup
bun run backend:up
bun run backend:logs
bun run backend:check
bun run backend:down
bun run check:all
```

## Current Phase 7 scope

Implemented in this foundation:

- Laravel 13 application entrypoints;
- reproducible PHP 8.3 Docker runtime;
- Sanctum SPA session foundation;
- strict credentialed CORS for Vite origins;
- stable API success/error envelopes;
- request IDs and contract-version headers;
- liveness/readiness endpoints;
- Redis cache, sessions and queues;
- Redis queue worker and scheduler services;
- rate-limit definitions for API and OTP flows;
- OTP-first ULID user model;
- encrypted append-oriented audit records;
- MySQL migrations, factories and seeders;
- Composer dependency audit and business-contract audit;
- PHPUnit, Larastan and Pint gates;
- local Docker automation and backend CI.

Not yet implemented:

- real OTP challenge storage and SMS provider;
- roles and permissions;
- addresses;
- catalog and inventory;
- checkout reservations;
- payment provider and ledger;
- seller/admin panels.

These are implemented in the next roadmap phases without changing the frozen public contract.
