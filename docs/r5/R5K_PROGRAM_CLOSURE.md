# R5K — Program closure and canonical pre-server path

Status: **implementation complete on product branch; formal gates pending**

Program branch: `integration/rosta-r5-marketplace`

Release branch: `integration/rosta-release-candidate`

Product branch: `program/r5k-program-closure`

## Purpose

R5K closes the source-code program before staging. It does not add a new product feature. It
removes contradictory release paths, fixes the remaining R5J seller/Hub visibility gap and records
one ten-phase execution model.

## Delivered closure

- Seller order responses expose only the roastery-to-Hub leg and Hub receipt.
- Internal Hub processing states, completion timestamps and events are excluded from seller
  responses.
- `docs/PHASES.md` maps all legacy Phase 1–22 and R1–R5 work to C0–C9.
- `deploy/staging` is the only deployment, acceptance, backup, rollback and restore path.
- Staging deploy accepts only an exact SHA frozen on
  `integration/rosta-release-candidate`.
- Legacy deployment scripts, obsolete audits and the unused mock order fixture are removed.
- `audit:r5k` permanently guards phase count, release lineage, provider boundaries and retired
  paths.

## Release order

```text
program/r5k-program-closure
  -> integration/rosta-r5-marketplace
  -> integration/rosta-release-candidate
  -> immutable staging SHA
```

No merge, rebase, squash, amend or force-push may rewrite published Lovable history.

## Boundaries

R5K keeps all external production actions disabled:

- real Zarinpal payment
- real refund execution
- real SMS
- production settlement movement
- Google indexing
- production credentials

Cloudflare R2 is enabled only for controlled staging media acceptance.

## Exit conditions

1. `audit:r5k` and all historical permanent audits pass.
2. Frontend unit tests, TypeScript, ESLint, build and bundle budget pass.
3. Backend audits, PHPUnit, Larastan and Pint pass.
4. All six formal GitHub workflows pass on one R5K head.
5. R5K merges into the marketplace integration branch.
6. The release-candidate branch fast-forwards to the exact verified merge SHA.
7. Superseded Draft PRs are closed with a pointer to `docs/PHASES.md`.

Runtime staging completion is intentionally separate and requires signed acceptance evidence with
`"accepted": true`.
