# ROSTA — Server Entry Fast Path

Status: PRE-SERVER PREPARATION / NOT DEPLOYED
Ops branch: `ops/rosta-server-entry-fastpath-20260921`
Frozen application payload: `rosta-pre-server-2026-09-05`
Frozen commit: `4a54780d504b91527a86777e7f04368022354686`
Post-PS12 planning ancestry: `0c840c0159b3c79f743d332a0dc51c4a002727f5`

## هدف

ورود به VPS نباید محل build، dependency resolution، طراحی معماری یا debug اولیه باشد. قبل از ورود به سرور باید:

1. exact frozen source در VSCode/WSL کامل پذیرفته شود؛
2. dependency lockها بدون تغییر پاس شوند؛
3. Frontend/Backend quality gates پاس شوند؛
4. Docker staging rehearsal محلی پاس شود؛
5. imageهای Linux/amd64 از exact frozen SHA ساخته و در GHCR منتشر شوند؛
6. DNS/R2/env values از قبل آماده باشند؛
7. سرور فقط bootstrap محدود، pull، migration، start، acceptance و evidence را انجام دهد.

## اصل Source of Truth

برای تصمیم فنی، ابتدا مستند رسمی vendor/runtime بررسی می‌شود. Blog، Stack Overflow و پاسخ انجمنی نباید مبنای قرارداد deployment باشند مگر صرفاً برای سرنخ و با تأیید دوباره از مرجع رسمی.

منابع فنی مرجع این Fast Path:

- Docker Engine on Ubuntu: https://docs.docker.com/engine/install/ubuntu/
- Docker Compose up / no-build: https://docs.docker.com/reference/cli/docker/compose/up/
- Docker Compose pull: https://docs.docker.com/reference/cli/docker/compose/pull/
- Docker image save/load: https://docs.docker.com/reference/cli/docker/image/save/ و https://docs.docker.com/reference/cli/docker/image/load/
- GitHub Container Registry: https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry
- GitHub Actions image publishing: https://docs.github.com/en/actions/tutorials/publish-packages/publish-docker-images
- GitHub Actions security: https://docs.github.com/en/actions/reference/security/secure-use
- Ubuntu firewall: https://documentation.ubuntu.com/server/how-to/security/firewalls/
- Ubuntu security guidance: https://ubuntu.com/server/docs/explanation/security/security_suggestions/
- Laravel deployment: https://laravel.com/framework/docs/deployment
- Bun reproducible install: https://bun.sh/docs/pm/cli/install
- TanStack Start production checklist: https://tanstack.com/start/latest/docs/framework/react/guide/production-checklist
- TanStack Start hosting / Node runtime: https://tanstack.com/start/latest/docs/framework/react/guide/hosting
- MySQL 8.4 container deployment: https://dev.mysql.com/doc/refman/8.4/en/linux-installation-docker.html
- Redis persistence: https://redis.io/docs/latest/operate/oss_and_stack/management/persistence/
- Caddy automatic HTTPS: https://caddyserver.com/docs/automatic-https
- Cloudflare R2 API: https://developers.cloudflare.com/r2/api/
- Cloudflare R2 S3 compatibility: https://developers.cloudflare.com/r2/api/s3/api/
- Cloudflare R2 CORS: https://developers.cloudflare.com/r2/buckets/cors/
- Cloudflare R2 public/custom domains: https://developers.cloudflare.com/r2/buckets/public-buckets/

هر تصمیم حقوقی، مالیاتی، پرداخت، پیامک تجاری، حریم خصوصی یا شرایط سرویس باید جداگانه از متن رسمی قانون/نهاد دولتی یا مستند رسمی Provider در زمان فعال‌سازی بررسی شود. این سند هیچ ادعای حقوقی ایجاد نمی‌کند.

## Gate A — Local/VSCode acceptance

این Gate قبل از هر VPS mutation اجباری است.

- exact frozen SHA ثبت و worktree تمیز باشد؛
- `bun ci` یا `bun install --frozen-lockfile` بدون تغییر lockfile پاس شود؛
- `composer install` فقط از `composer.lock` انجام شود؛
- `bun run check:all` پاس شود؛
- MySQL + Redis + Laravel API + worker + scheduler محلی healthy باشند؛
- Production frontend build + Node SSR اجرا شود؛
- Playwright browser acceptance روی runtime واقعی محلی پاس شود؛
- `ROSTA_ALLOW_REHEARSAL=true bash deploy/staging/rehearsal.sh` پاس شود؛
- backup/restore/rollback داخل rehearsal پاس شود؛
- Buyer/Seller/Admin/Cafe-B2B manual smoke بدون console/network error حل‌نشده انجام شود.

Exit marker:

`local_workstation_accepted=ready`

## Gate B — Prebuilt immutable images

بعد از Local GO، workflow `.github/workflows/ps12-prebuilt-images.yml` اجرا می‌شود.

قواعد:

- فقط exact release SHA `4a54780d504b91527a86777e7f04368022354686`;
- backend app image از `backend/Dockerfile.production --target app`;
- backend web image از `--target web`;
- frontend image از `Dockerfile.staging`;
- frontend configuration identity از staging domain inputs hash می‌شود؛
- imageها به GHCR push می‌شوند؛
- server نباید این imageها را rebuild کند؛
- manifest و image digests باید evidence شوند.

برای GHCR خصوصی، VPS فقط read-only package token نیاز دارد. Token نباید در repo یا command history ثبت شود.

## Gate C — Inputs prepared before SSH

قبل از ورود عملیاتی به VPS این مقادیر باید قطعی باشند:

- staging site domain، مثال: `staging.rosta.shop`;
- API domain: `api.<staging-domain>`;
- media domain: `media.<staging-domain>`;
- ACME email؛
- R2 bucket؛
- R2 S3 endpoint؛
- R2 access key / secret key؛
- staging APP_KEY؛
- MySQL database/user/password/root password؛
- Redis password؛
- session cookie/domain؛
- GHCR read credential اگر package خصوصی است.

Secretها فقط در فایل‌های سرور با permission محدود قرار می‌گیرند. هیچ secret واقعی در GitHub commit نمی‌شود.

## Gate D — DNS/R2 prepared before deploy

قبل از start:

- site/API A/AAAA رکوردها باید به VPS هدف اشاره کنند؛
- ports 80/443 برای Caddy reachable باشند؛
- R2 custom domain باید در همان Cloudflare account مربوطه فعال باشد؛
- CORS باید Origin دقیق staging frontend را مجاز کند؛
- `r2.dev` جای custom domain نهایی staging را نمی‌گیرد؛
- Caddy data/config volume باید persistent باشد تا certificate state حفظ شود.

## Gate E — Server Fast Path

روی VPS:

1. read-only preflight؛
2. bootstrap فقط در صورت نبود Docker/user/path؛
3. repo checkout روی exact frozen SHA؛
4. نصب env files از templateهای ازقبل‌آماده؛
5. GHCR login فقط در صورت private بودن package؛
6. pull imageهای prebuilt؛
7. local tag به `rosta-api:<sha>`, `rosta-api-web:<sha>`, `rosta-frontend:<sha>`;
8. pull `mysql:8.4`, `redis:7.4-alpine`, `caddy:2-alpine`;
9. `docker compose ... up --no-build --pull never`;
10. migration؛
11. strict acceptance؛
12. backup/restore/restart/rollback evidence طبق R4B؛
13. فقط در صورت `accepted: true` ثبت GO.

Server exit marker:

`staging_runtime_accepted=ready`

## دلیل عدم Build روی VPS

مسیر committed فعلی `deploy/staging/preflight.sh` برای build کامل حداقل 3 GiB RAM می‌خواهد. VPS آزمایشی 1 GiB نباید با حذف کورکورانه این guard پذیرفته شود. Fast Path با prebuilt image، بار build را از VPS حذف می‌کند؛ ولی runtime هنوز باید memory/OOM gate واقعی را پاس کند.

## Firewall note

Docker رسماً هشدار می‌دهد که published container ports می‌توانند رفتار مورد انتظار UFW را دور بزنند. بنابراین فقط تکیه بر `ufw allow/deny` کافی نیست؛ در R4B باید exposed ports و Docker firewall/DOCKER-USER behavior هم بررسی شود. این نکته قبل از production cutover اجباری است.

## Provider safety

در staging:

- `ROSTA_PAYMENT_ENABLED=false`
- `ROSTA_REFUND_ENABLED=false`
- `ROSTA_SMS_ENABLED=false`
- `VITE_ALLOW_INDEXING=false`

R2 برای acceptance فعال است ولی باید bucket/credentials staging باشد.

## Completion

این Fast Path زمانی آماده تلقی می‌شود که:

- Local Gate سبز؛
- prebuilt image workflow سبز؛
- image identities/digests ثبت؛
- server env files قبل از SSH آماده؛
- DNS/R2 آماده؛
- one-command server deploy dry-run/contracts locally validated.

تا آن زمان R4B/PS13 = NOT DONE.
