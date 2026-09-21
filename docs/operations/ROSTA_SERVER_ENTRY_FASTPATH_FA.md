# ROSTA — Server Entry Fast Path

Status: PRE-SERVER PREPARATION / NOT DEPLOYED
Ops branch: `ops/rosta-server-entry-fastpath-20260921`

Historical frozen PS12 source:
- tag: `rosta-pre-server-2026-09-05`
- commit: `4a54780d504b91527a86777e7f04368022354686`

The historical PS12 tag is immutable and remains the ancestry anchor. It is **not automatically the final VPS deploy identity anymore** because the pre-server recertification on 2026-09-21 discovered newly published High dependency advisories. The final deploy identity must be a later exact descendant SHA that passes the full Local/VSCode acceptance and is then tagged `rosta-server-ready-YYYY-MM-DD[...]`.

## هدف

ورود به VPS نباید محل build، dependency resolution، کشف bug یا تصمیم معماری باشد. قبل از SSH عملیاتی باید:

1. exact candidate source در VSCode/WSL کامل پذیرفته شود؛
2. dependency lockها و security audit سبز باشند؛
3. Frontend/Backend/browser/full-stack gates پاس شوند؛
4. Docker staging rehearsal محلی پاس شود؛
5. همان exact locally accepted SHA یک tag immutable از نوع `rosta-server-ready-*` بگیرد؛
6. imageهای Linux/amd64 از همان SHA و tag در GitHub Actions ساخته و در GHCR منتشر شوند؛
7. image digests و manifest ثبت شوند؛
8. DNS/R2/env bundle قبل از SSH آماده و verify شود؛
9. VPS فقط preflight، pull، migration، start، acceptance، backup/restore/restart/rollback evidence را اجرا کند.

## Source of Truth

برای تصمیم فنی، ابتدا مستند رسمی vendor/runtime بررسی می‌شود. Blog، Stack Overflow یا پاسخ انجمنی مبنای قرارداد production نیست مگر فقط برای سرنخ و بعد از تأیید با مرجع اصلی.

منابع مرجع:

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
- Bun install/lock behavior: https://bun.sh/docs/pm/cli/install
- TanStack Start production checklist: https://tanstack.com/start/latest/docs/framework/react/guide/production-checklist
- TanStack Start hosting: https://tanstack.com/start/latest/docs/framework/react/guide/hosting
- MySQL 8.4 container deployment: https://dev.mysql.com/doc/refman/8.4/en/linux-installation-docker.html
- Redis persistence: https://redis.io/docs/latest/operate/oss_and_stack/management/persistence/
- Caddy automatic HTTPS: https://caddyserver.com/docs/automatic-https
- Cloudflare R2 API: https://developers.cloudflare.com/r2/api/
- Cloudflare R2 S3 compatibility: https://developers.cloudflare.com/r2/api/s3/api/
- Cloudflare R2 CORS: https://developers.cloudflare.com/r2/buckets/cors/
- Cloudflare R2 public/custom domains: https://developers.cloudflare.com/r2/buckets/public-buckets/
- MinIO official container examples use Quay for the isolated S3-compatible rehearsal image. MinIO is rehearsal-only; real staging media remains Cloudflare R2.

هر تصمیم حقوقی، مالیاتی، پرداخت، پیامک تجاری، حریم خصوصی یا Terms باید در زمان فعال‌سازی از متن رسمی قانون/نهاد دولتی یا Provider رسمی بررسی شود. این سند به‌تنهایی هیچ ادعای حقوقی ایجاد نمی‌کند.

## Security refresh rule

اگر بعد از freeze قبلی advisory جدید High/Critical منتشر شود:

- هیچ allowlist دائمی برای عبور دادن audit ساخته نمی‌شود مگر با مستند threat assessment و approval مستقل؛
- patch رسمی/upstream یا یک override محدود و مستند انتخاب می‌شود؛
- lockfile دوباره تولید می‌شود؛
- تمام CI/browser/staging gates دوباره اجرا می‌شوند؛
- tag تاریخی جابه‌جا نمی‌شود؛
- بعد از Local Acceptance یک server-ready tag جدید ساخته می‌شود.

در refresh مورخ 2026-09-21:
- `sharp` به `0.35.4` قفل شد؛
- `js-yaml` به `4.3.2` قفل شد؛
- old PS12 tag بدون تغییر باقی ماند.

## Gate A — Local/VSCode acceptance

این Gate قبل از هر VPS mutation اجباری است.

- candidate SHA دقیق و worktree تمیز؛
- `bun install --frozen-lockfile` بدون تغییر lockfile؛
- `composer install` فقط از `composer.lock`؛
- dependency security audit سبز؛
- `bun run check:all` سبز؛
- MySQL + Redis + Laravel API + worker + scheduler healthy؛
- Production frontend build + Node SSR healthy؛
- Playwright browser acceptance سبز؛
- `ROSTA_ALLOW_REHEARSAL=true bash deploy/staging/rehearsal.sh` سبز؛
- backup/restore/rollback rehearsal سبز؛
- Buyer/Seller/Admin/Cafe-B2B manual smoke؛
- console/network بدون application error حل‌نشده؛
- هیچ تغییر ناخواسته در lock/source بعد از اجرای Gate باقی نماند.

Local exit marker:

`local_workstation_accepted=ready`

## Gate B — Create immutable server-ready release identity

فقط بعد از Gate A:

1. exact accepted commit SHA ثبت می‌شود؛
2. یک tag جدید مانند `rosta-server-ready-2026-09-21` دقیقاً روی همان SHA ساخته می‌شود؛
3. tag باید descendant از `4a54780...` باشد؛
4. tag و SHA دیگر در زمان deploy تغییر نمی‌کنند.

## Gate C — Prebuilt immutable images

workflow `.github/workflows/ps12-prebuilt-images.yml` سه input می‌گیرد:

- exact `release_sha`;
- exact `release_tag`;
- staging site domain.

Workflow فقط زمانی build می‌کند که:
- SHA دقیق 40 کاراکتری باشد؛
- tag از الگوی server-ready پیروی کند؛
- tag دقیقاً به همان SHA resolve شود؛
- SHA descendant از frozen PS12 باشد؛
- worktree clean باشد.

Build outputs:
- `rosta-api:<release_sha>`
- `rosta-api-web:<release_sha>`
- `rosta-frontend:<release_sha>-<config_id>`

Frontend `config_id` از site/API/media/payment-redirect/indexing contract hash می‌شود تا image با domain/config اشتباه قابل اشتباه گرفتن نباشد.

Server هیچ‌کدام از این application imageها را rebuild نمی‌کند. Bundle نهایی نیز باید tag referenceهای موقت را با digest referenceهای `ghcr.io/...@sha256:...` حاصل از manifest جایگزین کند؛ `verify-server-bundle.sh` بدون digest immutable موفق نمی‌شود.

## Gate D — Inputs prepared before SSH

قبل از ورود عملیاتی به VPS باید قطعی باشند:

- server-ready SHA/tag؛
- staging site domain؛
- API domain؛
- media domain؛
- ACME email؛
- Cloudflare R2 bucket؛
- R2 S3 endpoint؛
- R2 access key / secret key؛
- staging APP_KEY؛
- MySQL database/user/password/root password؛
- Redis password؛
- session cookie/domain؛
- GHCR read credential در صورت private بودن package.

`prepare-server-bundle.sh` APP_KEY/DB/Redis secretها را محلی می‌سازد. Secret واقعی در Git commit نمی‌شود و generated bundle در `.server-ready/` gitignored است.

## Gate E — DNS/R2 before server start

قبل از start:

- site/API A/AAAA به VPS هدف اشاره کنند؛
- ports 80/443 برای Caddy reachable باشند؛
- R2 custom domain در Cloudflare account درست فعال باشد؛
- CORS فقط Origin دقیق staging frontend را اجازه دهد؛
- bucket/credential مخصوص staging باشد؛
- `r2.dev` جای custom domain مورد پذیرش نهایی را نمی‌گیرد؛
- Caddy data/config persistent باشد.

## Gate F — Server Fast Path

روی VPS:

1. `preflight-readonly.sh`;
2. bootstrap فقط اگر Docker/user/path واقعاً وجود ندارند؛
3. fetch tags؛
4. verify server-ready tag -> exact SHA؛
5. verify PS12 ancestry؛
6. detached checkout روی exact SHA؛
7. نصب env bundle ازقبل‌ساخته‌شده؛
8. GHCR login فقط اگر package private است؛
9. pull prebuilt imageها؛
10. pull MySQL/Redis/Caddy dependency imageها؛
11. `docker compose ... up --no-build --pull never`;
12. forward-only migration؛
13. strict acceptance؛
14. backup/restore/restart/rollback/reboot/failure evidence طبق R4B؛
15. فقط با `accepted: true` ثبت GO.

Server exit marker:

`staging_runtime_accepted=ready`

## 1 GiB VPS policy

Build application image روی VPS ممنوع است. این بار توسط GHCR prebuild حذف می‌شود.

اما 1 GiB هنوز فقط زمانی پذیرفته می‌شود که runtime واقعی:
- OOM/restart loop نداشته باشد؛
- MySQL/Redis/API/worker/scheduler/SSR/edge healthy بمانند؛
- restart/reboot acceptance پاس شود.

اگر runtime پایدار نباشد، اندازه VPS افزایش می‌یابد؛ health/readiness gate ضعیف نمی‌شود.

## Firewall

Docker می‌تواند published container ports را خارج از انتظار ساده UFW expose کند. بنابراین R4B فقط `ufw status` را evidence کافی نمی‌داند؛ listenerها، Docker port mappings و Docker firewall chainها نیز باید audit شوند.

## Provider safety

در staging تا closure R4B:

- `ROSTA_PAYMENT_ENABLED=false`
- `ROSTA_REFUND_ENABLED=false`
- `ROSTA_SMS_ENABLED=false`
- `VITE_ALLOW_INDEXING=false`

Cloudflare R2 برای media acceptance فعال است ولی فقط با staging bucket/credential.

## Completion

Fast Path فقط وقتی READY است که:

- PR/CI سبز؛
- Local Gate سبز؛
- server-ready tag ساخته شده؛
- prebuilt image workflow سبز؛
- image digests/manifest ثبت؛
- manifest با `apply-image-manifest.sh` روی bundle اعمال شده؛
- server-ready bundle بدون placeholder، با digest immutable و verify شده؛
- DNS/R2 آماده؛
- one-command no-build deploy contract تست شده.

تا آن زمان R4B/PS13 = NOT DONE.
