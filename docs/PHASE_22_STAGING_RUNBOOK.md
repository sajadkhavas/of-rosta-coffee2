# Rosta Phase 22 — Staging Deployment and Acceptance Runbook

## Scope

This runbook deploys the complete Rosta stack to a dedicated Ubuntu staging host:

- TanStack Start SSR frontend on Node 22
- Laravel on PHP 8.3 FPM
- Nginx API web tier
- MySQL 8.4
- Redis 7.4 with AOF and password authentication
- Queue worker and scheduler
- Cloudflare R2 using the S3-compatible API
- Caddy TLS edge for the frontend and API

Staging remains fail-closed:

- payment disabled
- refund execution disabled
- SMS disabled
- indexing disabled
- R2 media enabled because it is part of Phase 22 acceptance
- whole-bean weights remain 50, 100, 250, 500 and 1000 grams

## Architecture

```text
Internet
  |
  +-- staging.rosta.shop ---------- Caddy ---- TanStack SSR :3000
  |
  +-- api-staging.rosta.shop ------ Caddy ---- Nginx :8080 ---- PHP-FPM :9000
                                                      |
                                                      +-- MySQL :3306 (private)
                                                      +-- Redis :6379 (private)
                                                      +-- R2 S3 API (controlled egress)

media-staging.rosta.shop ----------- Cloudflare R2 custom domain
```

Only ports 80 and 443 are published by Docker. MySQL, Redis, PHP-FPM, Nginx and the SSR server have no direct host port.

## 1. External prerequisites

### DNS

Create these records before deployment:

| Host | Destination | Initial mode |
|---|---|---|
| `staging.rosta.shop` | Staging server IPv4 | DNS only until the first certificate is issued |
| `api-staging.rosta.shop` | Staging server IPv4 | DNS only until the first certificate is issued |
| `media-staging.rosta.shop` | R2 custom domain | Managed by Cloudflare R2 |

Ports 80/TCP, 443/TCP and 443/UDP must reach the server.

### Cloudflare R2

Create a dedicated staging bucket. Do not reuse the production bucket.

Create an API token with Object Read & Write access restricted to that bucket. Record:

- Access Key ID
- Secret Access Key
- bucket name
- account S3 endpoint: `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`

Attach `media-staging.rosta.shop` as the bucket custom domain and enable public delivery for that custom domain.

Apply a browser CORS policy equivalent to:

```json
[
  {
    "AllowedOrigins": ["https://staging.rosta.shop"],
    "AllowedMethods": ["GET", "HEAD", "PUT"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

The Acceptance command writes one random object, reads it through the S3 API and custom domain, tests CORS, then deletes it.

## 2. Bootstrap Ubuntu 24.04

Run from the checked-out repository as root:

```bash
sudo bash deploy/staging/bootstrap-host.sh
```

The bootstrap installs Docker Engine, Buildx, Docker Compose, Git, Python, OpenSSL and UFW. It creates:

```text
/srv/rosta
/etc/rosta/staging
/var/lib/rosta/staging
/var/lib/rosta/staging/backups
/var/lib/rosta/staging/reports
```

The default deployment user is `rosta-deploy`. Log out and in after the bootstrap so Docker group membership is refreshed.

## 3. Clone the release stack

As `rosta-deploy`:

```bash
cd /srv/rosta
git clone git@github.com:sajadkhavas/of-rosta-coffee2.git app
cd app
git checkout agent/phase-22-staging-deployment-acceptance
chmod +x deploy/staging/*.sh
```

GitHub remains the source of truth. Do not edit tracked application files directly on the server.

## 4. Install environment files outside the repository

```bash
sudo cp .env.staging.example /etc/rosta/staging/frontend.env
sudo cp backend/.env.staging.example /etc/rosta/staging/backend.env
sudo chown rosta-deploy:rosta-deploy /etc/rosta/staging/*.env
sudo chmod 600 /etc/rosta/staging/*.env
```

Edit the files and replace every `CHANGE_ME`.

Useful secret generation commands:

```bash
openssl rand -hex 32
openssl rand -base64 32
```

Set Laravel `APP_KEY` in this form:

```text
APP_KEY=base64:<32-byte-base64-value>
```

Frontend host file must contain:

```text
VITE_ALLOW_INDEXING=false
STAGING_SITE_DOMAIN=staging.rosta.shop
STAGING_API_DOMAIN=api-staging.rosta.shop
STAGING_MEDIA_DOMAIN=media-staging.rosta.shop
ACME_EMAIL=<real operational email>
```

Backend host file must contain:

```text
APP_ENV=staging
APP_DEBUG=false
ROSTA_PAYMENT_ENABLED=false
ROSTA_REFUND_ENABLED=false
ROSTA_SMS_ENABLED=false
ROSTA_MEDIA_UPLOADS_ENABLED=true
ROSTA_MEDIA_UPLOAD_DISK=s3
S3_DEFAULT_REGION=auto
S3_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
S3_BUCKET=<staging bucket>
S3_PUBLIC_URL=https://media-staging.rosta.shop
S3_USE_PATH_STYLE_ENDPOINT=false
```

Provider credentials for payment and SMS remain empty during Phase 22.

## 5. Preflight

```bash
cd /srv/rosta/app
ROSTA_FRONTEND_ENV_PATH=/etc/rosta/staging/frontend.env \
ROSTA_BACKEND_ENV_PATH=/etc/rosta/staging/backend.env \
ROSTA_STATE_DIR=/var/lib/rosta/staging \
  bash deploy/staging/preflight.sh
```

Preflight verifies:

- secret file permissions
- DNS resolution
- Docker and Compose availability
- minimum free disk and RAM
- syntax of all deployment scripts
- fully resolved Compose configuration
- Caddy configuration
- permanent frontend and backend Phase 22 audits
- presence or planned generation of `backend/composer.lock`

## 6. First deployment

```bash
ROSTA_FRONTEND_ENV_PATH=/etc/rosta/staging/frontend.env \
ROSTA_BACKEND_ENV_PATH=/etc/rosta/staging/backend.env \
ROSTA_STATE_DIR=/var/lib/rosta/staging \
  bash deploy/staging/deploy.sh
```

The deploy pipeline performs, in order:

1. exclusive deployment lock
2. PHP 8.3 quality image build
3. `backend/composer.lock` generation when absent
4. locked Composer install
5. every backend audit
6. PHPUnit
7. Larastan
8. Pint check
9. Compose contract validation
10. pre-deploy MySQL backup when a database already exists
11. frontend frozen Bun install
12. frontend permanent audits
13. frontend unit tests, TypeScript, lint, production build and bundle budget
14. immutable backend and frontend image build
15. MySQL and Redis startup
16. forward-only Laravel migrations
17. API, worker, scheduler, SSR frontend and Caddy startup
18. internal and external Phase 22 acceptance
19. signed acceptance evidence and release metadata

Generated `backend/composer.lock` must be committed to GitHub before production release.

## 7. Acceptance evidence

Latest result:

```text
/var/lib/rosta/staging/reports/latest.json
/var/lib/rosta/staging/reports/latest.json.sha256
```

Per-run evidence is stored under:

```text
/var/lib/rosta/staging/reports/<timestamp>-<release>/
```

Acceptance fails unless all of these pass:

- all containers running
- Laravel readiness
- committed/generated Composer lock in the image
- current database schema
- MySQL real query
- Redis SETEX/GET/DEL round trip
- Redis queue connection
- R2 PUT/GET
- R2 public custom-domain delivery
- R2 CORS preflight
- R2 cleanup
- public API health
- product, roastery and content API responses
- SSR homepage response
- staging robots lock
- HSTS, CSP and `X-Robots-Tag`
- credentialed CORS boundary
- Secure/SameSite Sanctum CSRF cookie

Acceptance creates no business order, payment or SMS event.

## 8. Manual repeat of Acceptance

```bash
ROSTA_FRONTEND_ENV_PATH=/etc/rosta/staging/frontend.env \
ROSTA_BACKEND_ENV_PATH=/etc/rosta/staging/backend.env \
ROSTA_STATE_DIR=/var/lib/rosta/staging \
  bash deploy/staging/acceptance.sh
```

## 9. Self-hosted GitHub workflow

The repository includes `.github/workflows/staging-deploy.yml`.

Register a self-hosted runner on the staging server with labels:

```text
self-hosted
linux
x64
rosta-staging
```

The runner user must:

- have Docker access
- read `/etc/rosta/staging/*.env`
- write `/var/lib/rosta/staging`

The workflow deploys the exact checked-out commit, never an ambiguous branch head. It uploads only redacted Acceptance JSON and Compose process state; secrets and application logs are not uploaded.

The host scripts work directly even while GitHub-hosted Actions remain unavailable.

## 10. Backup

```bash
ROSTA_FRONTEND_ENV_PATH=/etc/rosta/staging/frontend.env \
ROSTA_BACKEND_ENV_PATH=/etc/rosta/staging/backend.env \
ROSTA_STATE_DIR=/var/lib/rosta/staging \
  bash deploy/staging/backup.sh
```

Backups are transaction-consistent compressed MySQL dumps with SHA-256 sidecars. Default retention is 14 days.

Copy backups to storage outside the VPS before production. Local staging backup alone is not disaster recovery.

## 11. Image rollback

Rollback switches to the previous immutable images. It does not execute down migrations.

```bash
ROSTA_FRONTEND_ENV_PATH=/etc/rosta/staging/frontend.env \
ROSTA_BACKEND_ENV_PATH=/etc/rosta/staging/backend.env \
ROSTA_STATE_DIR=/var/lib/rosta/staging \
  bash deploy/staging/rollback.sh
```

An explicit tag can be supplied:

```bash
bash deploy/staging/rollback.sh <release-tag>
```

Rollback takes a backup first and accepts the candidate. If candidate Acceptance fails, the script restores the current images automatically.

Schema changes must remain backward compatible across at least one release. Destructive schema reversal is never automatic.

## 12. Database restore

Database restore is intentionally destructive and requires an absolute path, checksum and explicit confirmation:

```bash
ROSTA_CONFIRM_RESTORE=restore-staging \
ROSTA_FRONTEND_ENV_PATH=/etc/rosta/staging/frontend.env \
ROSTA_BACKEND_ENV_PATH=/etc/rosta/staging/backend.env \
ROSTA_STATE_DIR=/var/lib/rosta/staging \
  bash deploy/staging/restore-backup.sh \
  /var/lib/rosta/staging/backups/<backup>.sql.gz
```

The script creates a pre-restore backup, stops application traffic, validates gzip and SHA-256, restores MySQL, runs forward migrations and executes complete Acceptance.

## 13. Failure inspection

```bash
source deploy/staging/lib.sh
load_staging_environment
rosta_compose ps
rosta_compose logs --tail=200 api api-web frontend worker scheduler edge
```

Do not paste environment files, R2 credentials, session cookies, OTP values or Provider payloads into issues or chat.

## Completion definition

Phase 22 is runtime-complete only when `latest.json` contains:

```json
{
  "accepted": true
}
```

Code preparation alone is marked `code_complete`; it is not represented as a successful live deployment until the server-generated Acceptance evidence exists.
