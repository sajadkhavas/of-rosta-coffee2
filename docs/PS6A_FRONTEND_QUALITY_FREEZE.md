# PS6A — Frontend Quality Freeze

Status: acceptance candidate. Final acceptance requires all applicable repository workflows to pass on one exact phase head before merge.

Baseline: `integration/rosta-release-candidate@66a45310a36ee0bfb3d2917e0791cb6c6f53052d`.

Branch: `phase/rosta-ps6a-frontend-quality`.

## Scope boundary

PS6A is a frontend quality freeze. It does not add marketplace business behavior, change backend contracts, recalculate money, alter deployment truth or modify dependency/lock files. Existing accepted APIs and role boundaries remain authoritative.

## Quality contract

The PS6A browser acceptance extends the permanent integrated browser suite with explicit checks for:

- zero serious/critical Axe violations on representative public desktop/mobile surfaces;
- no horizontal overflow at 1440x1000 and 390x844;
- no hydration/page/runtime error evidence on representative public surfaces;
- RTL direction preservation;
- keyboard focus leaving `body` on first Tab navigation;
- no rendered bearer/API-key/client-secret/private-key leakage;
- bounded local integrated TTFB, DOMContentLoaded and load timings;
- deterministic SHA-256 screenshot evidence for the same routes/viewports with animations disabled.

The visual/performance index is attached by Playwright to the browser test result and therefore follows the immutable CI head that produced it. The screenshots are evidence inputs, not product assets, and are not committed to source.

## Existing permanent gates consumed

PS6A intentionally reuses the established repository quality system instead of creating a second dependency or workflow stack:

- `bun run check` — dependency/security audits, route generation, unit tests, typecheck, lint, production SSR build and bundle budget;
- `bun run test:browser` inside the integrated Browser/R3 workflows;
- Browser Acceptance CI role/security journeys for customer, seller and administrator;
- Full-stack Integration CI production SSR/API runtime;
- R3 Final Gate complete frontend/backend/browser acceptance;
- R4 staging package rehearsal as a regression guard.

No `package.json`, `bun.lock`, backend dependency file, deployment source or business API is changed by PS6A.

## Performance and bundle evidence

The existing `bundle:check` remains the authoritative bundle ceiling. PS6A adds route-level runtime evidence rather than weakening that budget. The browser attachment records local integrated TTFB, DOMContentLoaded, load timing, viewport width/overflow and screenshot hash per route.

A timing regression is blocking when local integrated TTFB exceeds 5 seconds, DOMContentLoaded exceeds 15 seconds or load exceeds 20 seconds. These deliberately generous CI ceilings detect major regressions without pretending hosted CI equals real-user field performance.

Real production Core Web Vitals remain a post-deployment observation; PS6A does not fabricate field data.

## Acceptance requirements

1. `bun run check` passes on the exact final head.
2. Integrated Browser Acceptance passes including `ps6a-frontend-quality-freeze.spec.ts`.
3. Zero serious/critical Axe findings on the covered surfaces.
4. Zero covered horizontal-overflow failures.
5. Zero covered hydration/page/runtime error failures.
6. Production SSR build and bundle budget pass without budget suppression.
7. Existing customer/seller/admin role journeys remain green.
8. Full-stack Integration, Browser Acceptance, R3 Final Gate and R4 Staging Package pass on the exact final head together with the standard CI set.
9. No feature, dependency, lockfile, backend or production-deployment scope is introduced.
10. Merge uses a normal merge commit; no rebase, amend, squash or force-push.

## Boundary to PS7 / PS8

PS7 owns `deploy/production`, production environment materialization, backup/restore/rollback and real monitoring wiring. PS8A later performs an independent evidence-only frontend audit on the common Wave 5 candidate and may fail this freeze if the exact shared SHA does not reproduce the required evidence.
