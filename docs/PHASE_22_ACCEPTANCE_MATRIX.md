# Phase 22 Acceptance Matrix

| Gate | Tool | Fails deployment |
|---|---|---|
| Frozen frontend dependencies | `bun install --frozen-lockfile` | Yes |
| Frontend audits, unit tests, TypeScript, lint, build, bundle budget | `bun run check` | Yes |
| Composer lock | Existing lock or PHP 8.3 generation | Yes |
| Backend audits, PHPUnit, Larastan, Pint | `composer check` | Yes |
| Compose interpolation | `docker compose config --quiet` | Yes |
| MySQL backup before change | `backup_database` | Yes when database exists |
| MySQL and Redis health | Compose health checks | Yes |
| Forward migrations | `artisan migrate --force` | Yes |
| Required schema | `rosta:readiness --json` | Yes |
| MySQL query | `rosta:staging-acceptance` | Yes |
| Redis write/read/delete | `rosta:staging-acceptance` | Yes |
| Redis queue connection | `rosta:staging-acceptance` | Yes |
| R2 S3 PUT/GET | `rosta:staging-acceptance` | Yes |
| R2 custom-domain delivery | `rosta:staging-acceptance` | Yes |
| R2 CORS | `rosta:staging-acceptance` | Yes |
| R2 cleanup | `rosta:staging-acceptance` | Yes |
| External API readiness | `acceptance.sh` | Yes |
| Public data endpoints | `acceptance.sh` | Yes |
| SSR homepage | `acceptance.sh` | Yes |
| Robots lock | `acceptance.sh` | Yes |
| HSTS, CSP, noindex header | `acceptance.sh` | Yes |
| Credentialed API CORS | `acceptance.sh` | Yes |
| Secure Sanctum cookie | `acceptance.sh` | Yes |
| Acceptance evidence checksum | SHA-256 sidecar | Yes |
| Payment, refund and SMS disabled | Environment and Laravel checks | Yes |
| Grind state absent | Permanent audits | Yes |
