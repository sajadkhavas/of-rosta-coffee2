# Rosta PS7 — Production Deployment Runbook

This directory is the source-controlled production deployment contract for `rosta.shop` and `api.rosta.shop`.

It prepares and validates deployment machinery; it does **not** claim that a real server has been deployed merely because CI passes. Runtime acceptance is only proven after the commands in this runbook execute successfully on the production host and the resulting evidence is recorded.

## Invariants

- Release identity is always an exact 40-character Git SHA.
- Application images are tagged `rosta-api:<sha>`, `rosta-api-web:<sha>` and `rosta-frontend:<sha>`.
- Production source must be a clean checkout at the candidate SHA before a forward deployment.
- Runtime acceptance verifies the **running container image tags**, independently of the host checkout. This is required for safe image rollback.
- Database migrations are forward-only during normal deploy/rollback. Image rollback never automatically reverses migrations.
- A database backup is taken before a deployment when MySQL is already running.
- Destructive restore requires a verified SHA-256 sidecar and creates a mandatory pre-restore backup first.
- Provider rails remain fail-closed until their real production credentials/approvals are deliberately materialized.
- Real secrets live in protected host files and must never be committed.

## Required host layout

Recommended host paths:

```text
/opt/rosta/source                         clean Git checkout
/etc/rosta/production/frontend.env        root-owned runtime/build environment
/etc/rosta/production/backend.env         root-owned backend secrets/config
/var/lib/rosta/production/                release state, reports and backups
```

Create the protected directories before materializing secrets:

```bash
sudo install -d -m 0755 /opt/rosta
sudo install -d -m 0700 /etc/rosta/production
sudo install -d -m 0700 /var/lib/rosta/production
sudo install -d -m 0700 /var/lib/rosta/production/backups
sudo install -d -m 0700 /var/lib/rosta/production/reports
```

Copy the examples to the protected host locations and replace every required `CHANGE_ME` value locally:

```bash
sudo install -m 0600 deploy/production/frontend.env.example /etc/rosta/production/frontend.env
sudo install -m 0600 deploy/production/backend.env.example /etc/rosta/production/backend.env
sudoedit /etc/rosta/production/frontend.env
sudoedit /etc/rosta/production/backend.env
```

Never paste real secrets into GitHub issues, pull requests, shell history, screenshots or acceptance reports.

## Candidate materialization

On the production host, fetch the accepted release candidate and check it out without editing the working tree:

```bash
cd /opt/rosta/source
git fetch --all --prune
CANDIDATE_SHA='<FULL_ACCEPTED_SHA>'
git checkout --detach "$CANDIDATE_SHA"
test "$(git rev-parse HEAD)" = "$CANDIDATE_SHA"
test -z "$(git status --porcelain --untracked-files=all)"
```

Set `ROSTA_IMAGE_TAG` in `/etc/rosta/production/frontend.env` to the exact same SHA before a forward deploy.

## Source package checks

The permanent source contract is:

```bash
bash deploy/production/contract-test.sh
ROSTA_ALLOW_REHEARSAL=true bash deploy/production/rehearsal.sh
```

The rehearsal is disposable and isolated. It validates the production edge/security policy with mock frontend/API services and never touches production state, real DNS, real databases or secrets.

## Preflight

Run before every production deployment:

```bash
bash deploy/production/preflight.sh
```

Preflight fails closed when:

- the checkout SHA and `ROSTA_IMAGE_TAG` differ;
- production environment/domain/session/provider contracts are invalid;
- required lockfiles or deployment files are missing;
- the Git working tree is dirty;
- Docker Compose cannot render the production topology;
- a mutable `latest` application image reference is detected.

## Deploy

An intentional production cutover requires an explicit confirmation variable:

```bash
ROSTA_CONFIRM_PRODUCTION_DEPLOY=yes bash deploy/production/deploy.sh
```

The deployment sequence is:

1. validate source identity and environment contract;
2. run preflight;
3. take a pre-deploy database backup when MySQL is already running;
4. build SHA-tagged application images;
5. start/wait for MySQL and Redis health;
6. run forward-only Laravel migrations with `--force`;
7. activate API, web, worker, scheduler, frontend and edge services;
8. run fail-closed runtime acceptance against the candidate SHA;
9. only after acceptance succeeds, record the new current/previous release state.

## Acceptance

Run standalone acceptance for the recorded current runtime:

```bash
bash deploy/production/acceptance.sh
```

Or prove a specific runtime SHA explicitly:

```bash
ROSTA_ACCEPTANCE_RELEASE_TAG='<FULL_SHA>' bash deploy/production/acceptance.sh
```

Acceptance verifies:

- all required services are running;
- application containers actually use the expected SHA-tagged images;
- Laravel strict readiness is green;
- public HTTPS endpoints answer;
- required security headers exist;
- production robots policy remains indexable;
- canonical API liveness is healthy;
- generated evidence does not contain secret-shaped environment material.

The latest redacted runtime result is written under:

```text
/var/lib/rosta/production/reports/latest.json
```

## Backup

Create an on-demand transaction-consistent database backup:

```bash
bash deploy/production/backup.sh manual
```

Backups are gzip-tested and receive a SHA-256 sidecar. Retention is controlled by `ROSTA_BACKUP_RETENTION_DAYS`.

## Image rollback

Rollback is intentionally image-only and requires the previous release images to still exist locally:

```bash
ROSTA_CONFIRM_PRODUCTION_ROLLBACK=yes bash deploy/production/rollback.sh
```

Rollback does not alter Git history and does not automatically reverse database migrations. Runtime acceptance is executed against the previous SHA before release bookkeeping is swapped.

If a schema change is not backward-compatible with the previous application image, do **not** use image rollback blindly; follow the incident/restore plan appropriate to that release.

## Verified database restore

A destructive restore requires an absolute backup path and its `.sha256` sidecar:

```bash
ROSTA_CONFIRM_PRODUCTION_RESTORE=yes \
  bash deploy/production/restore-backup.sh \
  /var/lib/rosta/production/backups/<backup>.sql.gz
```

The restore procedure validates checksum/gzip integrity, creates a pre-restore backup, stops application writers, recreates/imports the database, restarts the recorded runtime release and performs runtime acceptance.

## SBOM

After the SHA-tagged images exist, generate an SPDX JSON SBOM set:

```bash
bash deploy/production/generate-sbom.sh
```

This requires `syft` on the host and stores checksummed SBOM evidence under the protected state directory.

## Monitoring boundary

PS7 source acceptance proves that strict readiness, API liveness and redacted operational evidence are callable. A real production monitoring/alert route must still be materialized on the server or chosen monitoring provider and tested with a real alert/failure injection before runtime PS7/PS8 infrastructure acceptance can be marked complete.

Do not rewrite this boundary into a claim that monitoring exists when only source code/hosted CI has been tested.

## Final evidence checklist

Before declaring real production deployment accepted, preserve secret-safe evidence for:

- exact Git candidate SHA;
- exact running application image identities;
- successful production preflight;
- successful pre-deploy backup plus checksum;
- successful migration/cutover;
- successful runtime acceptance JSON;
- TLS/domain reachability for site and API;
- worker/scheduler/queue readiness;
- a tested rollback or restore drill appropriate to the release;
- actual monitoring/alert delivery proof.

Only then may the runtime phase be registered as production accepted.
