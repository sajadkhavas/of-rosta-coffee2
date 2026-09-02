# ROSTA PS10 — Release Publication Recovery

## Status

The first PS10 source merge, `fd55c99e80d8be3b130bde5ce172f5b3066a28bd`, was created by a normal two-parent merge after all required workflows passed on exact source head `9d466b787ac5b602d0417a6d124cbd438a12557d`.

Its post-merge `PS10 Final Source Release` run successfully re-verified the PS9 lineage, both merge parents, source/merge tree equality, and the exact-head workflow matrix. It then reproduced the production frontend artifact and SPDX SBOM.

Publication stopped during the release-evidence workspace-cleanliness assertion. The release tag and GitHub release steps were skipped. Therefore **no PS10 tag was created** and the historical PS9 tag remained unchanged.

## Corrective rule

The publication defect is corrected only through the dedicated branch `fix/rosta-ps10-release-evidence-cleanliness`. There is no direct edit to `integration/rosta-release-candidate`, no force-push, no tag move, and no reuse of stale workflow evidence.

The corrective source must:

1. remain descended from the tag-less pre-release merge `fd55c99e80d8be3b130bde5ce172f5b3066a28bd`;
2. preserve proof that that merge itself was a normal merge from historical PS9 `ab96dd2280f9e1870455cadc10297ee0fa20a308` and original PS10 source head `9d466b787ac5b602d0417a6d124cbd438a12557d`;
3. treat only the build-generated `src/routeTree.gen.ts` mutation as an allowed cleanup target and fail on every other workspace mutation;
4. retain a named workspace-status evidence file rather than hiding the generated-file mutation;
5. pass the full exact-head workflow matrix again before merge;
6. merge normally with the corrective head locked by expected SHA;
7. create `rosta-pre-server-2026-09-02` only after the corrective merge passes post-merge lineage, tree-equivalence and exact-head workflow verification;
8. publish the GitHub release only after SBOM, manifest, checksums, workspace evidence and machine-readable acceptance evidence are sealed.

## Boundary

This recovery changes release plumbing only. It does not activate the real server, DNS, TLS, Kavenegar, Zarinpal, Cloudflare R2, production credentials, public cutover, or real money movement. The final source verdict remains `PRE-SERVER GO`; real production activation remains a separate server/runtime acceptance program.
