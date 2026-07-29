# Phase 22 — Staging Status

## Repository status

```text
phase: 22
scope: complete staging deployment and runtime acceptance
code_complete: true
runtime_deployed: false
runtime_accepted: false
providers_enabled: false
indexing_enabled: false
```

## Completed in repository

- PHP 8.3 backend quality image with MySQL, SQLite, Redis and cURL extensions
- complete Composer quality gate before deployment
- required committed `backend/composer.lock`; deployment fails closed if it is missing
- Node 22 TanStack SSR image built with frozen Bun dependencies and complete frontend checks
- MySQL 8.4 and Redis 7.4 persistent services
- private database/cache network and separate controlled backend egress
- PHP-FPM, Nginx, queue worker and scheduler
- Caddy TLS edge for frontend and API
- staging noindex at application and edge layers
- internal Docker API route for SSR without leaking private hostnames to browsers
- R2 S3 API and custom-domain acceptance
- transaction-consistent database backup with checksum
- serialized deploy lock
- forward-only migrations
- immutable release tags
- image rollback with automatic candidate acceptance
- guarded database restore with checksum and explicit confirmation
- self-hosted GitHub staging workflow
- host bootstrap and preflight scripts
- permanent frontend and backend Phase 22 audits
- full staging operations runbook

## Runtime acceptance gates

The phase becomes runtime-complete only after a staging server generates:

```text
/var/lib/rosta/staging/reports/latest.json
```

with:

```json
{
  "accepted": true
}
```

The report covers:

- all service health
- PHP/Laravel readiness
- current database schema
- MySQL real query
- Redis write/read/delete
- Redis queue
- R2 write/read/public delivery/CORS/delete
- public API endpoints
- SSR homepage
- absolute SSR canonical and noindex metadata
- sitemap index plus static, product, roastery and content shards
- 1200×630 PNG Open Graph delivery
- robots noindex
- TLS security headers
- credentialed CORS
- secure Sanctum cookie

## External inputs still required

These values cannot be created or inferred from the repository:

- staging server access
- public server IP
- DNS records
- operational ACME email
- Laravel APP_KEY
- MySQL and Redis passwords
- R2 bucket
- R2 Access Key ID and Secret
- R2 account endpoint
- R2 custom domain and CORS policy

The R5K program merge and release-candidate freeze are complete at the same
verified commit. Until the external values are installed on the server, this
branch is correctly classified as:

```text
code-level complete
runtime unverified
release candidate frozen; staging inputs pending
```

Payment, refund execution, SMS and Google indexing remain disabled throughout Phase 22.
