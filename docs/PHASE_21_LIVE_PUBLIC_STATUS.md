# Phase 21 — Live Public Data, SSR and Verified Reviews

## Status

Phase 21 is integrated in the canonical R5 lineage after the completed Phase 20
operational workspaces.

The public website no longer depends on the static product, roastery or blog fixtures. Public catalog, editorial and review surfaces now consume authoritative Laravel API/CMS responses and major indexable routes preload data through TanStack loaders for SSR.

Permanent source gates have passed. Deployed SSR, structured-data, performance
and browser acceptance still require the staging runtime.

## Production seed removal

Removed:

- `src/data/seed.ts`
- `src/data/blog-posts.ts`
- legacy seed-backed `ProductCard`
- legacy seed-backed `RoasteryCard`

Production additionally registers `ProductionSafetyServiceProvider`, which refuses:

- `db:seed`
- `migrate:fresh`

Catalog and content must be created through authenticated seller and administrator workspaces.

## Live SSR homepage

The homepage loader composes:

- published products;
- verified roasteries;
- authoritative totals;
- optional CMS landing-page FAQ blocks.

It renders honest empty states when no records have been published. It never falls back to demo data.

## Live SSR catalog

- product directory uses search-dependent loader data;
- roastery directory uses page-dependent loader data;
- product detail remains loader-backed;
- product and roastery ItemList JSON-LD is generated in the route head from loader results;
- only published catalog records and active variants are returned by the Backend public service.

## Live CMS editorial

- blog index lists only published Article records whose canonical path is under `/blog/`;
- blog entries resolve from the CMS API;
- metadata, canonical URL, author, publication dates and schema come from the CMS record;
- body content renders through typed safe blocks;
- raw HTML and static article fixtures are not used;
- product and roastery relations link to live public entities.

## Live quiz

The quiz loader obtains up to 100 currently available published products.

Ranking uses:

- roast level;
- processing method;
- tasting notes;
- brew-method preference;
- experience/adventure preference;
- authoritative variant availability.

Unavailable or unpublished products cannot appear. Results use the same live product card contract as the catalog.

## Verified-purchase reviews

### Public product page

- approved verified-purchase reviews only;
- aggregate count and average;
- privacy-safe author label;
- loading, error and empty states.

### Delivered order page

- a review form appears per delivered order item;
- submission sends only the authoritative `order_item_id`, rating, title and body;
- Backend rechecks ownership, Delivered status and duplicate review constraints;
- a new review remains Pending until administrator moderation;
- the public review query is invalidated after successful submission.

## SSR coverage

Loader-backed public routes now include:

- `/`
- `/products`
- `/products/$slug`
- `/roasteries`
- `/blog`
- `/blog/$slug`
- `/quiz`

Interactive filters continue using React Query, hydrated from the loader cache.

## Permanent gates

Frontend:

- `audit:phase21`
- confirms static fixtures and imports are absent;
- confirms live loaders and safe CMS blocks;
- confirms live available quiz ranking;
- confirms verified review flow;
- confirms no grind state in public product or inventory identity.

Backend:

- `audit:live-public`
- confirms Production seed command blocking;
- confirms published-only catalog/content;
- confirms verified and moderated reviews;
- confirms whole-bean public boundary.

## Whole-bean boundary

Every public product and review surface remains whole-bean only.

Allowed weights remain:

- 50 g
- 100 g
- 250 g
- 500 g
- 1000 g

Phase 21 did not introduce a grind product variant or inventory state. Later R5
checkout phases represent grinding only as an order-item service.

## Staging gates still open

- run SSR requests against the deployed Laravel API;
- validate cache hydration and error boundaries;
- run verified-review browser E2E with delivered and non-delivered orders;
- run Lighthouse/Core Web Vitals and structured-data validation;
- keep indexing disabled until staging acceptance and explicit launch approval.

No production seed, real payment/refund provider, production money movement or indexing is enabled by this phase.
