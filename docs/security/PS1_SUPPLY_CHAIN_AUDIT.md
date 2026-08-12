# PS1 Supply-chain and Secret-scanning Audit

## Scope

PS1 changes the frontend dependency/security gate only. Full Docker image digest pinning and GitHub Actions commit-SHA pinning remain explicitly deferred to PS7, as required by the pre-server ownership contract.

## Dependency gate

- `bun.lock` is the immutable frontend dependency source for CI/release builds.
- `bun run audit:dependencies` executes `bun audit --audit-level=high` against that lock.
- High/Critical findings fail CI.
- Versioned exceptions require advisory ID, reason, owner and an expiry no more than 30 days away.
- The PS1 accepted state uses no exceptions.

## Current supply-chain inventory

The repository still contains tag-based external execution references such as `actions/checkout@v4`, `oven-sh/setup-bun@v2` and tagged Docker images (`mysql:8.4`, `redis:7.4-alpine`, `caddy:2-alpine`). PS1 does not convert these to commit/image digests because that work is owned by PS7. They remain an explicit PS7 supply-chain hardening item, not an undocumented PS1 omission.

## Secret scanning

Source controls already prevent environment files and staging state from entering normal Docker/repository evidence paths, and R4A contains evidence checks for secret-shaped/private material. PS1 found no repository-owned Gitleaks/TruffleHog-style CI scanner by source inspection.

The GitHub Secret Scanning alerts API could not be inspected through the connected integration (`403 Resource not accessible by integration`), so repository-level GitHub Secret Scanning enablement is **unproven**, not PASS.

PS1 does not introduce a second secret-scanning product because the mission restricts policy changes to dependency/CI policy. PS7/administration must verify GitHub Secret Scanning/Push Protection (or the approved equivalent) in repository settings before production release.

## PS1 secret boundary

No provider credential, production environment file, private key, database dump or backup payload is added by PS1. Example environments continue to use empty values or explicit `CHANGE_ME` placeholders only.
