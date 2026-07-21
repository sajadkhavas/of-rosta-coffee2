# Rosta Backend

Security-first Laravel API for the Rosta whole-bean coffee marketplace.

## Permanent boundaries

- Only whole coffee beans are supported. Grind state is invalid in every request, model, migration and response.
- Every customer order belongs to exactly one roastery.
- Laravel is authoritative for identity, ownership, price, stock, roast batch, delivery, discounts, totals, order state and payment truth.
- Browser data and payment callback query parameters are untrusted.
- SMS and payment providers are disabled until real credentials and approved callback settings are supplied.
- The frozen frontend contract is `docs/openapi/rosta-v1-phase6.yaml`.

## Requirements

- PHP 8.2+
- Composer 2
- Docker with Compose v2
- PHP extensions: PDO MySQL, Redis, Mbstring, OpenSSL, JSON, Tokenizer, XML, Ctype, BCMath

## One-command setup

```bash
cd backend
chmod +x scripts/setup.sh
./scripts/setup.sh
```

The setup script:

1. creates the required Laravel runtime directories;
2. copies `.env.example` to `.env` when needed;
3. starts MySQL 8.4 and Redis 7.4;
4. installs Composer dependencies;
5. generates `APP_KEY`;
6. runs migrations and the local seeder;
7. runs tests, Larastan and Pint checks.

## Run locally

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

In separate terminals:

```bash
cd backend
php artisan horizon
```

```bash
cd backend
php artisan schedule:work
```

Health endpoints:

- `GET http://127.0.0.1:8000/api/v1/health/live`
- `GET http://127.0.0.1:8000/api/v1/health/ready`

The frontend should use:

```env
VITE_API_URL=http://127.0.0.1:8000/api/v1
```

## Quality commands

```bash
composer test
composer analyse
composer format
composer check
```

`composer check` is the permanent local quality gate.

## Current Phase 7 scope

Implemented in this foundation:

- Laravel 11 application entrypoints;
- Sanctum SPA session foundation;
- strict credentialed CORS;
- stable API success/error envelopes;
- request IDs and contract-version headers;
- liveness/readiness endpoints;
- Redis cache, sessions and queues;
- Horizon scheduling foundation;
- rate-limit definitions for API and OTP flows;
- OTP-first ULID user model;
- immutable encrypted audit logs;
- MySQL migrations, factories and seeders;
- PHPUnit, Larastan and Pint gates;
- local Docker dependencies and setup automation.

Not yet implemented:

- real OTP challenge storage and SMS provider;
- roles and permissions;
- addresses;
- catalog and inventory;
- checkout reservations;
- payment provider and ledger;
- seller/admin panels.

These are implemented in the next roadmap phases without changing the frozen public contract.
