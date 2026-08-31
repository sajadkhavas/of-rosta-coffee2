# ROSTA PS8A — Frontend Acceptance Audit

Status: IMPLEMENTED_AWAITING_ACCEPTANCE

Baseline: `integration/rosta-release-candidate@e5953d77ae44fe21e435335ecb23a8bf1b235705`

Canonical branch: `phase/rosta-ps8a-frontend-acceptance`

## Purpose

PS8A is an independent acceptance audit of the frozen frontend quality and production SSR policy. It is not a product feature phase. It must reproduce PS6A evidence on the shared candidate and add production-indexing/SEO boundary evidence that the ordinary browser acceptance suite intentionally runs with indexing disabled.

If PS6A evidence cannot be reproduced or the production SSR contract fails, PS8A fails closed. A discovered frontend-owned defect may be corrected in PS8A, but unrelated business/backend scope must return to its owning phase.

## Acceptance surface

PS8A requires all of the following on one exact PR head:

1. frozen Bun and Composer dependency identities;
2. frontend permanent checks;
3. a real Node production SSR build (`NODE_ENV=production`, `NITRO_PRESET=node-server`);
4. production site identity `https://rosta.shop` with `VITE_ALLOW_INDEXING=true`;
5. integrated Laravel fixture runtime for data-backed public routes;
6. independent replay of `ps6a-frontend-quality-freeze.spec.ts`;
7. PS8A SEO/indexing browser acceptance for public canonical/title/description policy;
8. filtered catalog and private routes remain `noindex`;
9. production `robots.txt` allows public crawling while disallowing private/transactional surfaces and advertising the production sitemap;
10. 404 responses are crawl-safe with an HTTP `X-Robots-Tag` noindex policy;
11. SSR security headers remain present;
12. PWA manifest remains reachable and structurally valid;
13. runtime evidence is secret-safe and uploaded as a CI artifact;
14. the repository returns clean after generated route-tree handling;
15. all other applicable repository gates pass on the exact same head.

## Defect found by the audit

The pre-PS8A frontend already emitted route-level `noindex` metadata for private routes and the production edge also carried noindex policy, but the SSR server itself did not emit a defensive `X-Robots-Tag` for private routes or generic 404 responses.

PS8A closes that gap in `src/server.ts` so crawl safety is preserved even when the Node SSR origin is evaluated independently of Caddy. This is defense-in-depth; it does not remove the production edge policy.

## Permanent gate

`.github/workflows/ps8a-frontend-acceptance.yml` boots a disposable integrated runtime and produces secret-safe evidence. It intentionally uses the production indexing policy while keeping test-only backend providers and local network endpoints isolated from real production.

The workflow must emit:

`ROSTA_PS8A_FRONTEND_ACCEPTANCE_COMPLETE`

before this phase can be accepted.

## Runtime boundary

PS8A does not claim that `rosta.shop` is deployed, reachable, indexed, or serving the tested build on a real VPS. Real DNS/TLS/server activation remains after PS9 freeze.
