# Phase 22 — External Inputs Checklist

The repository package is complete. Runtime execution requires these values on the staging host:

- [ ] Ubuntu staging VPS access
- [ ] public server IPv4
- [ ] `staging.rosta.shop` DNS
- [ ] `api-staging.rosta.shop` DNS
- [ ] `media-staging.rosta.shop` R2 custom domain
- [ ] operational ACME email
- [ ] Laravel `APP_KEY`
- [ ] MySQL database, user and root passwords
- [ ] Redis password
- [ ] Cloudflare R2 bucket name
- [ ] R2 Access Key ID
- [ ] R2 Secret Access Key
- [ ] R2 account endpoint
- [ ] R2 browser CORS policy
- [ ] `/etc/rosta/staging/frontend.env` with mode `600`
- [ ] `/etc/rosta/staging/backend.env` with mode `600`

First execution:

```bash
ROSTA_FRONTEND_ENV_PATH=/etc/rosta/staging/frontend.env \
ROSTA_BACKEND_ENV_PATH=/etc/rosta/staging/backend.env \
ROSTA_STATE_DIR=/var/lib/rosta/staging \
  bash deploy/staging/preflight.sh

ROSTA_FRONTEND_ENV_PATH=/etc/rosta/staging/frontend.env \
ROSTA_BACKEND_ENV_PATH=/etc/rosta/staging/backend.env \
ROSTA_STATE_DIR=/var/lib/rosta/staging \
  bash deploy/staging/deploy.sh
```

Completion evidence:

```text
/var/lib/rosta/staging/reports/latest.json
```

Phase 22 runtime acceptance is complete only when that report contains `"accepted": true`.
