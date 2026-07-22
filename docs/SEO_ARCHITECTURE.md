# Rosta SEO and Content Architecture

## Status

- Branch: `agent/phase-15-seo-content-foundation`
- Dependency: stacked on Phase 10 transactional checkout
- Source of truth:
  - Laravel owns publication, canonical paths, indexability, redirects and content relations.
  - TanStack Start owns SSR rendering, metadata serialization and crawl-facing routes.
  - Product price, stock, variants, reviews and payment facts remain authoritative in Laravel.

## Goal

Rosta is designed as a content-commerce marketplace rather than a product-only storefront. Public entities must reinforce each other through stable URLs and explicit relationships:

```text
Product
├── Roastery
├── Origin
├── Roast level
├── Processing method
├── Tasting notes
├── Brew methods
├── Roast batch
├── Structured guide content
└── Verified reviews (later phase)
```

## Permanent URL policy

| Entity | Canonical pattern |
| --- | --- |
| Product | `/products/{slug}` |
| Roastery | `/roasteries/{slug}` |
| Guide | `/guides/{slug}` |
| Origin landing | `/origins/{slug}` |
| Brew method | `/brew/{slug}` |
| Taste landing | `/tastes/{slug}` |
| Curated collection | `/collections/{slug}` |
| Comparison | `/compare/{slug}` |
| Legacy article | `/blog/{slug}` until migrated |

Canonical paths:

- are internal paths beginning with `/`;
- never contain a host, query string or fragment;
- reject plain, encoded and repeatedly encoded path traversal;
- cannot use transactional or private prefixes;
- are unique in the database;
- require an explicit permanent redirect when changed.

Reserved prefixes include `/api`, `/admin`, `/panel`, `/auth`, `/checkout`, `/cart`, `/orders`, `/profile` and `/search`.

## Indexability policy

| Page class | Default policy | Sitemap |
| --- | --- | --- |
| Published product | index, follow | yes |
| Verified roastery | index, follow | yes |
| Published content with `robots_index=true` | index, follow | yes |
| Published content with `robots_index=false` | noindex, configurable follow | no |
| Draft, review or archived content | unavailable publicly | no |
| Search results | noindex, follow | no |
| Quiz | noindex, follow | no |
| Cart and checkout | noindex | no |
| Orders, profile and auth | noindex | no |
| Admin and seller panel | noindex | no |
| Design system and forbidden pages | noindex | no |

The Laravel endpoint `/api/v1/seo/indexable` is the authoritative feed for structured content URLs. The TanStack sitemap also paginates the complete product and roastery catalogs instead of stopping at the first page.

`robots_index` expresses the desired policy after publication. Draft and Review records may preserve that choice for editorial workflow, but they remain unavailable publicly and cannot enter the indexable URL feed until their status is Published.

## Publication workflow

```text
Draft
  ↓ editor submits
Review
  ↓ administrator reviews author, content, canonical and SEO fields
Published
  ↓ any editorial edit
Review (desired robots policy is preserved, public access is removed)
```

Direct Draft-to-Published transitions are rejected. Publishing requires:

- the current status to be Review;
- an active author;
- at least two safe content blocks;
- a title and SEO description;
- an administrator reviewer;
- a valid canonical path.

Published content edits automatically clear the publication date and return the entry to Review. Because public queries require Published status, the entry immediately disappears from public rendering and the sitemap even when its desired `robots_index` setting remains true.

## Structured content blocks

Raw editorial HTML is not accepted by the new content domain. Supported blocks are:

- paragraph;
- heading (`h2` or `h3`);
- ordered or unordered list;
- quote;
- callout;
- FAQ;
- product grid;
- roastery spotlight;
- comparison table.

Both Laravel and the TypeScript client validate the block discriminant, fields, count and text limits. The React renderer uses semantic elements and never uses `dangerouslySetInnerHTML` for this content.

## Content relationships

Relations are stored separately from body blocks so they can support internal-link reporting and future recommendation systems.

Supported relation intents:

- `related`;
- `mentions`;
- `recommends`;
- `compares`;
- `primary_topic`.

Supported targets:

- content;
- product;
- roastery;
- origin;
- brew method;
- taste.

Target keys use bounded slug syntax. Arbitrary URLs and control characters are not accepted as relationship targets.

## Metadata ownership

### Laravel

Laravel returns:

- SEO title and description;
- canonical path;
- robots index/follow flags;
- Open Graph title, description and image;
- schema type;
- author and reviewer;
- publication and modification timestamps;
- structured content and relationships.

### TanStack Start

TanStack Start:

- fetches critical public data inside route loaders;
- renders title, description and canonical in the initial HTML;
- creates Open Graph and Twitter metadata;
- serializes JSON-LD safely;
- renders product and roastery metadata from Laravel data;
- exposes `robots.txt` and `sitemap.xml`.

## Structured data

- Product pages use `ProductGroup` and `hasVariant` for whole-bean weights.
- Product offers use server-owned price, currency and availability.
- Roastery pages use `Organization`; ratings are emitted only when a non-zero verified count exists.
- Editorial pages use their controlled schema type, publisher, author and timestamps.
- FAQ structured data is generated only from validated FAQ blocks that are visibly rendered.
- Breadcrumb structured data uses canonical public URLs.

No structured product price, inventory, rating or payment fact may be entered manually in SEO fields.

## Redirect policy

Redirects:

- support only 301 and 308;
- have an internal public source and destination;
- cannot originate from private or transactional routes;
- reject self-redirects;
- reject encoded traversal;
- reject loops;
- reject chains longer than 12 hops;
- record hit count and last hit time;
- are administered behind the administrator role boundary.

Launch acceptance should reduce all known redirects to a single hop.

## Administration interface

An initial administrator dashboard is available at `/admin/content` and is protected by both the frontend role guard and Laravel administrator middleware.

The current MVP supports:

- listing content and its status/index policy;
- creating an active author;
- creating a structured Guide from safe paragraph blocks;
- sending Draft content to Review;
- publishing only reviewed content;
- archiving content;
- creating internal 301/308 redirects;
- viewing redirect hit counts.

This is an operational MVP, not the final editor. The advanced editor still needs block reordering, FAQ/table/product selectors, editing existing entries, relation management, previews and field-level validation summaries.

## Legacy blog migration

`src/data/blog-posts.ts` is legacy static content containing HTML strings. It remains readable during migration but is not the target content model.

Migration sequence:

1. Parse each legacy article in a controlled migration script.
2. Convert paragraphs, headings and lists to validated blocks.
3. Map product and roastery mentions to `content_relations`.
4. Assign a real author and reviewer.
5. Preserve the existing `/blog/{slug}` canonical path or create a one-hop redirect.
6. Compare rendered text and metadata before publication.
7. Remove the static record only after the Laravel entry is published and crawl-tested.

Do not bulk-publish migrated content without human review.

## Crawl endpoints

- `/robots.txt`
- `/sitemap.xml`
- `/api/v1/seo/indexable`
- `/api/v1/seo/redirects/resolve`

The sitemap remains valid when one upstream dataset is temporarily unavailable; successful datasets are still emitted. Monitoring must alert when a dataset repeatedly fails.

## Quality gates

Frontend:

```bash
bun run audit:seo
bun run check
```

Backend:

```bash
composer audit:seo
composer check
```

Permanent audits protect:

- path-aware canonicals;
- private-route noindex policy;
- quiz exclusion from sitemap;
- full catalog pagination;
- SSR loaders for public entities;
- ProductGroup variants;
- raw HTML prohibition;
- reviewed publishing;
- redirect loop prevention;
- encoded traversal rejection;
- authoritative indexable URL feed.

## Launch checklist

- Production sets `VITE_ALLOW_INDEXING=true` only after acceptance.
- Staging keeps global noindex and a disallow-all robots policy.
- Every indexable URL returns HTTP 200 and self-canonical metadata.
- Every sitemap URL is public, published and indexable.
- Search, quiz, checkout and private pages are absent from sitemap.
- Product metadata is present in initial server-rendered HTML.
- Product prices and availability match Laravel responses.
- Redirects are one hop and contain no loops.
- Structured data validation reports no critical errors.
- Search Console and merchant tooling are connected after domain verification.
- Legacy blog pages are migrated gradually, not replaced in bulk.

## Known remaining work

- Complete the advanced administration editor for all block types and existing-entry edits.
- Add relation management and internal-link reporting to the admin interface.
- Migrate legacy static blog articles.
- Add public author pages before emitting author profile URLs.
- Add verified-review structured data after the review domain is implemented.
- Split the sitemap into an index when URL volume approaches operational limits.
- Regenerate and commit the TanStack route tree through the real build toolchain.
- Run full frontend and backend gates after the GitHub runner and Composer lock issues are resolved.
