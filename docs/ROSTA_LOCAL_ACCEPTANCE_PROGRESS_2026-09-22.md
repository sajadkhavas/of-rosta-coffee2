# ROSTA — Local Acceptance / Pre-Server Progress Report

**Date:** 2026-09-22  
**Repository:** `sajadkhavas/of-rosta-coffee2`  
**Purpose:** preserve the exact pre-server local acceptance state so work can resume without re-discovery or repeating already-validated steps.

---

## 1. Current status

Local acceptance is **IN PROGRESS**.

The project has passed the major source/backend/build gates locally, but the full workstation acceptance has **not** completed because the local Fast Path still attempts to download Playwright-managed Chromium from a CDN endpoint that returns a regional `403 AccessDenied`.

There is **no** server-ready tag yet and VPS deployment must not start until:

1. automated local acceptance passes;
2. manual Buyer / Seller / Admin / Cafe-B2B UI smoke passes;
3. an immutable `rosta-server-ready-YYYY-MM-DD...` tag is created from the accepted SHA.

---

## 2. Provenance and release lineage

Historical frozen PS12 anchor:

`4a54780d504b91527a86777e7f04368022354686`

Historical tag:

`rosta-pre-server-2026-09-05`

That historical tag is immutable and must not be moved or reused.

Fast Path PR #114 was merged previously.

PR #114 merge SHA:

`b5e28888a8a3ae718f702f201d2db7b47bf6e06f`

During real local acceptance a stateful-origin mismatch was found and fixed through PR #115.

PR #115:

`fix: align local acceptance stateful origins`

PR #115 fix head:

`65389685d85fd05bd996251ac31f2fd6b2f635af`

PR #115 normal merge SHA:

`e0b34c2e83bf8d893c00e997022e219779fda6f5`

Current integration baseline verified on GitHub:

`integration/rosta-release-candidate @ e0b34c2e83bf8d893c00e997022e219779fda6f5`

Current local/browser investigation branch:

`fix/local-browser-system-chrome-20260922`

Base of that branch:

`e0b34c2e83bf8d893c00e997022e219779fda6f5`

---

## 3. Local workstation topology

Rosta is intentionally cloned inside the WSL Linux filesystem, not under Windows `C:\` or `/mnt/c`.

Project location:

`/home/sajad/projects/of-rosta-coffee2`

WSL distribution:

`Ubuntu-24.04`

Verified OS:

`Ubuntu 24.04.5 LTS`

The Docker Desktop installation is machine-wide and shared with other projects, therefore destructive global cleanup such as `docker system prune` must not be used.

---

## 4. Verified toolchain

Validated from the Rosta project environment:

- Node: `v22.23.2`
- npm: `10.9.8`
- Bun: `1.2.22`
- PHP: `8.3.6`
- Composer: `2.10.3`
- Docker: `29.8.0`
- Docker Compose: `v5.5.1`

Important paths:

- Node: `/home/sajad/.nvm/versions/node/v22.23.2/bin/node`
- Bun: `/home/sajad/.bun/bin/bun`
- PHP: `/usr/bin/php`
- Composer: `/home/sajad/.local/bin/composer`

Required PHP extensions were previously verified, including:

- bcmath
- ctype
- curl
- dom
- fileinfo
- imagick
- intl
- json
- mbstring
- openssl
- pcntl
- pdo_mysql
- posix
- redis
- simplexml
- sockets
- tokenizer
- xml
- xmlwriter
- zip

AVIF encoder support was also installed through:

`libheif-plugin-aomenc`

---

## 5. Docker verification

Docker Desktop integration with Ubuntu-24.04 was verified.

Validated:

- user belongs to `docker` group;
- `/var/run/docker.sock` permissions are correct;
- Docker Engine is reachable from WSL;
- Docker Compose is available;
- `docker run --rm hello-world` succeeded.

A later local acceptance attempt initially reported:

`Docker Compose v2 is required`

This was not a Rosta defect. Docker Desktop was simply not running. After opening Docker Desktop and waiting for `Engine running`, Docker/Compose worked again.

---

## 6. First Local Acceptance failure — interrupted Docker image download

Initial command:

`bun run local:accept`

The first L1 attempt stopped while pulling `mysql:8.4`.

Observed error:

`short read: expected ... unexpected EOF`

The user's internet package had run out during the image download.

This was classified as a network/download failure, not a Rosta code defect.

The exact images were then pulled manually:

- `mysql:8.4`
- `redis:7.4-alpine`

Verified markers:

`mysql_8_4=ready`

`redis_7_4=ready`

MySQL image digest observed during successful pull:

`sha256:85b9bf2e29cf836ecb8c2a15a935d4ba0c606631dff1dd79531a11983c638f2a`

Redis image digest observed:

`sha256:858f009f9709ce576febc734aa78b8f6d624b82571f9ddb6bda4377c833b3499`

No Docker prune was used.

---

## 7. Second Local Acceptance failure — stateful origin/session mismatch

After dependencies installed, backend tests failed in bulk with:

`RuntimeException: Session store not set on request.`

Approximately 71 feature tests were affected.

Diagnosis showed that the local Fast Path exported only the SSR origin as stateful while backend test requests also used the Vite-style local origins.

Before fix:

`SANCTUM_STATEFUL_DOMAINS=127.0.0.1:3000`

The Browser Acceptance CI contract already included:

- `127.0.0.1:3000`
- `localhost:5173`
- `127.0.0.1:5173`

The local acceptance contract was aligned with CI.

Fixed values:

`FRONTEND_ALLOWED_ORIGINS=http://127.0.0.1:3000,http://localhost:5173,http://127.0.0.1:5173`

`SANCTUM_STATEFUL_DOMAINS=127.0.0.1:3000,localhost:5173,127.0.0.1:5173`

Files changed by PR #115:

- `deploy/server-fastpath/local-browser-acceptance.sh`
- `deploy/server-fastpath/contract-test.sh`

The contract test was extended so this local/CI stateful-origin drift cannot silently recur.

PR #115 passed its gates and was normal-merged.

Current accepted integration SHA after that fix:

`e0b34c2e83bf8d893c00e997022e219779fda6f5`

The previous session-store failure did not recur after this merge.

---

## 8. Composer / Packagist network issue

A later full Local Acceptance run reached `composer audit --locked` and failed with a network timeout:

`curl error 28`

This was isolated from the source code.

A direct retry:

`composer audit --locked`

returned:

`No security vulnerability advisories found.`

with:

`audit_exit=0`

Therefore this failure was also classified as transient network connectivity, not a project defect.

---

## 9. Local backend/source acceptance already observed green

During the latest full local acceptance attempts, the following project gates were observed passing before the browser-download blocker:

- Browser acceptance contract audit
- R3C2 backend commerce and roles audit
- R5B marketplace schema audit
- R5C marketplace checkout
- PS2 OTP/notification audit
- PS4A financial truth audit
- PS4.2 refund/payout/reconciliation contract audit
- PS6B backend observability audit
- PS8B backend finance acceptance
- R5D product packaging
- R5E grinding capability
- R5F roastery grinding
- R5G hub grinding
- R5H fulfillment commitment
- R5I delivery settlement
- R5J hub operations

Laravel test aggregate observed:

`48 passed (6576 assertions)`

Additional Laravel tests were reported as warnings rather than failures due to their fail-closed/file-get-content instrumentation behavior.

PHPStan:

`448/448`

Result:

`[OK] No errors`

Laravel Pint:

`PASS — 563 files`

---

## 10. Isolated acceptance database and readiness

The local browser acceptance uses its own isolated MySQL database:

`rosta_local_acceptance`

Connection settings used by the script:

- host: `127.0.0.1`
- port: `13306`
- user: `rosta`
- database: `rosta_local_acceptance`

Redis:

- host: `127.0.0.1`
- port: `16379`

The script runs:

`php artisan migrate:fresh --force`

only against this isolated acceptance database.

It must never be redirected to a normal user/dev/production database.

All current migrations through the Cafe/Wholesale PS12 schema were observed completing successfully.

Strict readiness returned:

`"ready": true`

Validated checks included:

- composer lock
- APP_KEY
- debug disabled
- database
- redis
- schema current
- payment activation contract
- refund intentionally disabled
- SMS intentionally disabled
- OTP delivery intentionally disabled
- media uploads intentionally disabled
- non-production financial policy behavior

Readiness returned no warnings.

---

## 11. Acceptance fixtures

`php artisan rosta:acceptance-fixtures --json`

completed successfully.

Completion marker:

`ROSTA_R3A_ACCEPTANCE_FIXTURES_COMPLETE`

Fixture domains observed include:

- acceptance customer
- administrator
- seller / roastery owner
- verified roastery
- foreign-scope roastery for denial testing
- published whole-bean product
- 100g / 250g / 500g variants
- global administrator role
- global customer role
- scoped roastery_owner role

This confirms the integrated browser environment reaches the expected fixture preparation stage before browser execution.

---

## 12. Production SSR build

The local acceptance successfully built the production frontend.

Observed:

- client Vite build completed;
- SSR Vite build completed;
- Nitro node-server build completed;
- `.output/server/index.mjs` generated.

The build emitted non-fatal chunk-size warnings, including the large Three.js chunk.

No build failure resulted from these warnings.

Runtime topology started by the script:

Laravel API:

`php artisan serve --host=127.0.0.1 --port=8000`

Notification worker:

`php artisan queue:work redis --queue=notifications --sleep=1 --tries=1 --timeout=30`

Production SSR:

`HOST=127.0.0.1 PORT=3000 node .output/server/index.mjs`

The script health-gates both:

- `http://127.0.0.1:8000/api/v1/health/live`
- `http://127.0.0.1:3000/robots.txt`

The latest full Local Acceptance passed the build/runtime-start stage and then stopped at the Playwright Chromium installation stage.

---

## 13. Playwright CDN blocker

The current blocking step in:

`deploy/server-fastpath/local-browser-acceptance.sh`

is:

`bunx playwright install chromium`

Playwright version observed:

`1.62.1`

The requested Chrome for Testing build was:

`151.0.7922.34`

Request path:

`https://cdn.playwright.dev/builds/cft/151.0.7922.34/linux64/chrome-linux64.zip`

The CDN redirected toward Google Storage and returned:

`403 AccessDenied`

with:

`We're sorry, but this service is not available in your location`

Playwright retried multiple times and eventually reported:

`Failed to install browsers`

This is currently classified as an environment/network-distribution blocker, not evidence of a Rosta product defect.

---

## 14. Browser alternatives investigated

### 14.1 Windows Chrome from WSL

Windows Chrome existed at:

`/mnt/c/Program Files/Google/Chrome/Application/chrome.exe`

Trying to launch it from Linux Playwright failed.

Observed Chrome error:

`Remote debugging pipe file descriptors are not open.`

Exit code:

`13`

Conclusion:

Do not use the Windows Chrome executable directly from Linux Playwright inside WSL.

### 14.2 Playwright headless shell

Attempted:

`bunx playwright install --only-shell chromium`

It requested the Chrome Headless Shell build but received the same regional `403 AccessDenied`.

Therefore `--only-shell` does not resolve the local CDN restriction.

### 14.3 Ubuntu Chromium package

Attempting Ubuntu Chromium triggered the Ubuntu Snap wrapper.

Snap Store connectivity failed in WSL and left `chromium-browser` in an inconsistent package state.

The broken package was safely removed with forced dpkg cleanup.

Validated after cleanup:

- `sudo dpkg --configure -a` completed
- `sudo apt --fix-broken install` reported no remaining broken packages

No Chromium Snap remains installed.

### 14.4 Native Linux Google Chrome

Google Chrome's official Linux Debian package was downloaded manually from:

`https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb`

Successful download:

approximately `135.34M`

Installed version:

`Google Chrome 153.0.8010.52`

Executable:

`/usr/bin/google-chrome`

A direct Node/Playwright launch test succeeded and printed:

`PLAYWRIGHT_BROWSER_OK`

Therefore a Chromium-compatible native Linux browser is available and Playwright can launch it correctly.

---

## 15. Temporary local Playwright validation

A temporary local config was created only for diagnosis.

It preserved the canonical browser test set while setting:

`executablePath: /usr/bin/google-chrome`

Playwright test discovery returned:

`Total: 11 tests in 7 files`

The discovered suite included:

- PS1 private-cache
- PS5A quiz/review safety
- PS5B seller organization
- PS6A frontend quality freeze
- PS8A frontend acceptance
- R3C public/customer
- R3C2 commerce/roles

The temporary config successfully launched Chrome.

When the test suite was run without the Fast Path runtime active, failures were exclusively:

`net::ERR_CONNECTION_REFUSED at http://127.0.0.1:3000`

This was expected because SSR was not running at that moment.

That standalone run must not be interpreted as an application defect.

The temporary config and test-results were later removed to return the worktree to a clean state.

---

## 16. Canonical Playwright contract

Canonical file:

`playwright.config.ts`

It is used by:

- Browser Acceptance CI
- PS8A Frontend Acceptance
- R3 Final Gate
- PS9 Final Freeze
- Local Browser Acceptance
- R3C browser contract audit

Its original provenance includes:

`72e2cc1 R3C add privacy-safe browser acceptance configuration`

Because it is part of multiple release gates, it should not be casually converted into a machine-specific configuration.

Preferred direction for the current working branch:

- keep CI/default Playwright-managed Chromium behavior intact;
- add an explicit, fail-closed local-only system-browser path for WSL/local acceptance;
- use the existing native Linux `/usr/bin/google-chrome` only when explicitly selected/validated;
- preserve the same 11 browser tests, reporter/JUnit behavior, locale, timezone, viewport, SSR base URL and acceptance semantics;
- lock the local behavior with a contract test;
- do not introduce a production/server browser dependency.

Final implementation is still pending on this branch.

---

## 17. Current Fast Path behavior

`deploy/server-fastpath/local-browser-acceptance.sh` currently performs:

1. exact SHA / PS12 ancestry / clean worktree checks;
2. Node 22 / Bun 1.2.22 / PHP 8.3 validation;
3. isolated MySQL 8.4 and Redis 7.4 startup;
4. testing/staging-safe env export;
5. locked Bun and Composer installs;
6. dependency and aggregate source gates;
7. isolated `migrate:fresh`;
8. strict readiness;
9. acceptance fixtures;
10. production SSR build;
11. Laravel API + notification worker + SSR startup;
12. integrated health wait;
13. Playwright Chromium availability/install;
14. R3C and R3C2 audits;
15. real browser journeys;
16. JUnit `failures="0"` assertion;
17. process liveness checks;
18. dependency checksum verification;
19. source cleanup and clean-worktree assertion;
20. `local_browser_acceptance=ready` marker.

The current failure happens at step 13, before browser journey execution.

---

## 18. Full workstation acceptance

Top-level script:

`deploy/server-fastpath/local-acceptance.sh`

Its stages are:

### L1 — Integrated Browser Acceptance

Runs:

`deploy/server-fastpath/local-browser-acceptance.sh`

Expected marker:

`local_browser_acceptance=ready`

### L2 — Full Staging Rehearsal

Runs:

`deploy/staging/rehearsal.sh`

with:

`ROSTA_ALLOW_REHEARSAL=true`

### L3 — Final Source Integrity

Checks:

- `git diff --check`
- clean worktree
- final dependency lock checksums

Final expected markers:

`local_workstation_accepted=ready`

`manual_ui_smoke=required_before_server_ready_tag`

These markers have **not yet been achieved** on the current integration descendant.

---

## 19. Manual UI smoke still required

Even after automated Local Acceptance becomes green, the server-ready tag must not be created immediately.

Manual smoke is still required for:

- Buyer
- Seller / Roastery
- Administrator
- Cafe / B2B wholesale

The smoke must include at least:

- visible UI behavior
- route/navigation behavior
- console errors
- failed network requests
- authentication/session behavior
- major role boundaries
- checkout-facing paths

Only after this manual smoke passes can the immutable server-ready tag be created.

---

## 20. Server deployment remains blocked

Do not enter VPS deployment yet.

Still pending:

- automated Local Browser Acceptance
- full Local Workstation Acceptance
- manual UI smoke
- immutable server-ready tag
- prebuilt digest-bound application images
- server bundle with real staging domain/ACME/R2 settings
- VPS read-only preflight
- no-build server deployment
- real staging acceptance

Issue #55 / R4B-PS13 must remain open until real VPS acceptance records:

`staging_runtime_accepted=ready`

---

## 21. Provider safety

During local/staging preparation, keep production-impacting providers disabled/fail-closed.

Do not enable real:

- Zarinpal payment execution
- automated refunds
- production SMS
- production OTP delivery
- indexing
- production media bucket/domain behavior

The Fast Path must remain safe until real staging/server acceptance is complete.

---

## 22. Important do-not-do rules

Do not:

- mutate historical PS12 tag;
- use `4a54780...` automatically as the final VPS payload;
- create a server-ready tag before both automated and manual acceptance;
- deploy to VPS before Local Acceptance is green;
- run `migrate:fresh` against a normal dev, staging or production database;
- build application images on a constrained 1GB VPS;
- bypass dependency/security auditing;
- enable production payment/refund/SMS/indexing prematurely;
- run `docker system prune` because Docker is shared with other local projects;
- move the Rosta clone under `/mnt/c`;
- confuse Rosta with other repositories/projects.

---

## 23. Immediate next action

Continue on:

`fix/local-browser-system-chrome-20260922`

from:

`e0b34c2e83bf8d893c00e997022e219779fda6f5`

Implement a minimal, explicit local system-Chrome path that:

1. does not weaken CI;
2. does not alter production runtime;
3. does not skip browser journeys;
4. does not skip JUnit/failure assertions;
5. uses native Linux Chrome only for Local Acceptance when explicitly selected;
6. preserves canonical test semantics;
7. is protected by the Fast Path contract tests.

Then run the complete:

`bun run local:accept`

Do not declare success until both markers are observed:

`local_browser_acceptance=ready`

`local_workstation_accepted=ready`

Then perform the required manual UI smoke before creating a server-ready tag.

---

## 24. Resume checkpoint

At the time this document was created:

- GitHub integration baseline: `e0b34c2e83bf8d893c00e997022e219779fda6f5`
- local system Chrome: `153.0.8010.52`
- native browser launch via Playwright: **PASS**
- backend aggregate tests: **PASS**
- PHPStan: **PASS**
- Pint: **PASS**
- isolated migrations/readiness/fixtures: **PASS**
- production SSR build: **PASS**
- current blocker: **Playwright-managed Chromium CDN 403 in Local Acceptance**
- Local Browser Acceptance: **NOT COMPLETE**
- Full Local Workstation Acceptance: **NOT COMPLETE**
- Manual UI Smoke: **NOT STARTED**
- server-ready tag: **NOT CREATED**
- VPS deployment: **NOT STARTED**
