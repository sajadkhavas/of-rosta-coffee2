# Dependency Audit Policy

ROSTA release candidates fail closed on frontend dependency vulnerabilities with **High** or **Critical** severity.

## Permanent gate

Run:

```bash
bun run audit:dependencies
```

The gate executes `bun audit --audit-level=high` against the committed `bun.lock`. A High/Critical advisory makes the command fail.

The audit is also executed explicitly by `.github/workflows/ci.yml` and remains part of the default `bun run check` chain.

## Exceptions

Exceptions are not a way to hide audit output. They are permitted only in `security/dependency-audit-exceptions.json` and each entry must contain:

- `advisoryId`: the CVE/advisory identifier accepted by Bun's `--ignore` option;
- `reason`: the concrete reason immediate remediation is not possible;
- `owner`: the accountable owner;
- `expiresAt`: an ISO-8601 expiry no more than 30 days in the future.

Expired, malformed or long-lived exceptions fail before the audit runs. The gate still prints and records the complete non-ignored audit output in `dependency-audit.log` and the GitHub Actions step summary.

An exception requires review in the same PR and must be removed as soon as a compatible fix exists. Permanent allowlists are forbidden.

## Ownership

PS1 owns frontend dependency declarations, `bun.lock`, this policy and the dependency CI gate for the pre-server wave. Composer dependencies are unchanged unless a directly proven advisory requires a coordinated exception to PS1 scope.

Docker image digest pinning and complete GitHub Actions SHA pinning remain PS7 work; PS1 must not broaden this dependency policy change into that scope.
