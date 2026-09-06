# ROSTA PS12 Phase Register — Cafe B2B Wholesale Marketplace

## Registration state

**SOURCE ACCEPTANCE CANDIDATE — NOT YET MERGED OR FROZEN**

This register is intentionally truthful before release administration completes. It becomes the PS12 source register only together with the merged PR, immutable tag and non-draft GitHub release evidence.

## Lineage

- Baseline branch: `integration/rosta-release-candidate`
- Baseline/frozen PS11 SHA: `af7a871d48ca4865a9509b8271b66ed380c57d24`
- Baseline tag: `rosta-pre-server-2026-09-03`
- PS12 branch: `phase/rosta-ps12-b2b-cafes-wholesale`
- PS12 PR: `#112`
- Implementation checkpoint before closure docs: `8adcb0dce494e6d4f2d0a70ed92c8bd4e822d4b6`
- Intended immutable PS12 tag after accepted merge: `rosta-pre-server-2026-09-05`
- Final merge SHA: must be taken from the actual merge result and immutable tag; never from GitHub's prospective pre-merge `merge_commit_sha` field.

## Registered source capabilities

1. Cafe application and explicit verification lifecycle.
2. Cafe owner/manager scoped membership.
3. Public verified-cafe directory and public cafe profile.
4. City and request-scoped proximity search.
5. Seller-authored wholesale tier pricing at 5/10/20/50 kg.
6. Verified-cafe-only wholesale entitlement.
7. Persisted Variant weight as authoritative wholesale-weight source.
8. Wholesale resolution before Quote financial truth.
9. Quote pricing snapshot plus Quote-to-Order revalidation.
10. Bulk quantity contract compatible with 50 kg ordering while stock remains server-authoritative.
11. Cafe/admin/roastery frontend workspaces.
12. Product-detail wholesale tier visibility in IRR with explicit Quote authority.
13. Whole-bean product identity preserved.

## Required closure evidence

PS12 is registered as final only when:

- all 15 exact-head PR workflows are successful;
- PR #112 is ready/non-draft and has no unresolved blocking review state;
- PR #112 is merged using a normal merge commit without rewriting accepted source history;
- `PS12 Final Source Release` succeeds on the actual merge SHA;
- `rosta-pre-server-2026-09-05` resolves to that actual merge SHA;
- the GitHub release for that tag is non-draft and contains sealed workflow/acceptance/checksum/SBOM evidence;
- `rosta-pre-server-2026-09-03` remains exactly `af7a871d48ca4865a9509b8271b66ed380c57d24`.

## Runtime separation

This phase register records source acceptance only. Real VPS deployment, environment configuration, DNS cutover, provider enablement, production cafe onboarding and live traffic require separate deployment/runtime evidence.