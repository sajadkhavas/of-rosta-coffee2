# Rosta SEO and Content Architecture

## Status

- Integrated in the canonical R5 lineage.
- The SEO foundation, live inventory-aware quiz and verified-review flow are implemented.
- Production crawl/index activation remains disabled until staging acceptance and launch approval.
- Laravel owns publication, canonical paths, indexability, redirects and content relationships.
- TanStack Start owns SSR rendering, metadata serialization and crawl-facing routes.
- Product price, stock, variants, reviews, orders and payment facts remain authoritative in Laravel.

Legal copy approval, reviewed legacy-content migration and production indexing
remain operational launch work; they are not missing source features.

## Goal

Rosta is a content-commerce marketplace. Products, roasteries, origins, brew methods, tastes, guides and collections reinforce each other through stable URLs and explicit relations.

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
└── Verified reviews (review phase)
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

- begin with `/` and never contain a host, query or fragment;
- reject plain, encoded and repeatedly encoded traversal;
- cannot use private or transactional prefixes;
- are unique in the database;
- require an explicit permanent redirect when changed.

Reserved prefixes include `/api`, `/admin`, `/panel`, `/auth`, `/checkout`, `/cart`, `/orders`, `/profile` and `/search`.

## Indexability policy

| Page class | Default policy | Sitemap |
| --- | --- | --- |
| Published product | index, follow | yes |
| Verified roastery | index, follow | yes |
| Published content with `robots_index=true` | index, follow | yes |
| Published content with `robots_index=false` | noindex | no |
| Draft, Review or Archived content | unavailable publicly | no |
| Search and Quiz | noindex | no |
| Cart, Checkout, Orders, Profile and Auth | noindex | no |
| Admin, Seller panel and Design System | noindex | no |

`/api/v1/seo/indexable` is the authoritative structured-content URL feed. The sitemap paginates the complete product and roastery catalogs and does not trust the browser.

`robots_index` stores the desired policy after publication. Draft and Review records may preserve it, but they cannot become public or enter the sitemap until Published.

## Publication workflow

```text
Draft
  ↓ submit for review
Review
  ↓ administrator validates author, blocks, canonical and SEO
Published
  ↓ any editorial edit
Review + public access removed
```

Direct Draft-to-Published transitions are rejected. Publishing requires:

- Review status;
- an active author;
- at least two safe blocks;
- title and SEO description;
- administrator reviewer;
- valid public canonical path.

A Published edit clears `published_at`, returns the entry to Review and removes it from public queries and the sitemap.

## Concurrent editing

Every edit response includes `content_hash`. PATCH requests must send `expected_content_hash`.

- matching hash: the update may proceed under a database row lock;
- stale or missing hash: the update is rejected;
- stale version: API returns `409 content.edit_conflict`;
- the editor offers “دریافت نسخه جدید” instead of silently overwriting another administrator’s work.

## Structured content blocks

Raw editorial HTML is forbidden. Supported blocks are:

- paragraph;
- heading (`h2` or `h3`);
- ordered or unordered list;
- quote;
- callout;
- FAQ;
- product grid;
- roastery spotlight;
- comparison table.

Laravel and TypeScript validate discriminants, fields, limits and table dimensions. Public rendering and admin Preview share `StructuredContentBlocks`, so Preview cannot drift into a separate renderer. `dangerouslySetInnerHTML` is not used for this domain.

## Content relationships

Relations are stored separately from body blocks.

Supported intents:

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

Target keys use bounded slug syntax. Arbitrary URLs and control characters are rejected.

## Internal-link health

Administrator endpoint:

- `GET /api/v1/admin/content-link-report`

Administrator routes:

- `/admin/content-links` — responsive report with Loading, Error and Empty states;
- `/admin/content-edit/{entryId}` — direct safe editor for an issue source.

The report identifies:

- broken relations with missing targets;
- targets that exist but are not publicly available;
- brew/taste relations pointing to the wrong content type;
- Published content with no incoming content relation;
- Published content with fewer than two outgoing relations.

The report is bounded to protect operations: at most 5,000 relations are scanned and at most 250 issues are returned per category. A truncation warning is rendered when the bound is reached.

## Metadata ownership

Laravel returns controlled SEO fields, author/reviewer, timestamps, blocks, relationships and canonical policy.

TanStack Start:

- fetches critical public data in route loaders;
- renders title, description, canonical and robots in initial HTML;
- serializes JSON-LD with `<` escaped;
- renders product and roastery schema from Laravel facts;
- exposes `robots.txt` and `sitemap.xml`.

No product price, inventory, rating or payment fact can be manually invented in SEO fields.

## Structured data

- Product pages use `ProductGroup` and `hasVariant` for whole-bean weights.
- Offers use server-owned price, currency and availability.
- Roastery pages use `Organization`; ratings are emitted only when a non-zero count exists.
- Editorial pages use controlled Article/BlogPosting/Collection/Page schema.
- FAQ schema is generated only from visible validated FAQ blocks.
- Breadcrumb schema uses canonical public URLs.

## Redirect policy

Redirects:

- support 301 and 308 only;
- require internal public source and destination;
- cannot originate from private or transactional routes;
- reject self-redirects, traversal, loops and chains longer than 12 hops;
- record hit count and last hit time;
- are restricted to administrators.

Launch acceptance should reduce known redirects to one hop.

## Administration interface

`/admin/content` is protected by the frontend Administrator guard and Laravel Administrator middleware. It supports:

- author creation;
- quick Draft creation for every supported content type;
- safe editing of existing entries;
- all nine structured block types;
- adding, deleting and reordering blocks;
- relation management;
- public-renderer Preview;
- SEO, Open Graph, robots and schema controls;
- unsaved-change confirmation;
- optimistic conflict detection;
- Draft → Review → Published workflow;
- Archive action;
- internal 301/308 redirects and hit counts.

`/admin/content-links` provides the internal-link report and direct edit actions.

## SSR routes

Critical loader-driven routes include:

- `/products/{slug}`;
- `/roasteries/{slug}`;
- `/guides/{slug}`;
- `/origins/{slug}`;
- `/brew/{slug}`;
- `/tastes/{slug}`;
- `/collections/{slug}`;
- `/compare/{slug}`.

The code is implemented on this branch. Real server build, generated route-tree verification and View Source acceptance remain part of full integration.

## Legacy blog migration

The former `src/data/blog-posts.ts` production fixture has been removed. Any
legacy article imported from an external archive must still follow this
controlled migration sequence.

Migration sequence:

1. parse each article in a controlled migration script;
2. convert headings, paragraphs and lists to validated blocks;
3. map product and roastery mentions to relations;
4. assign a real author and reviewer;
5. preserve `/blog/{slug}` or create one-hop redirect;
6. compare rendered text and metadata;
7. remove the static record only after the Laravel entry is Published and crawl-tested.

Bulk publishing without human review is forbidden.

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

- path-aware canonical and private-route noindex policy;
- quiz exclusion and complete sitemap pagination;
- SSR loader and metadata boundaries;
- ProductGroup whole-bean variants;
- safe structured rendering without raw HTML;
- Draft → Review → Published workflow;
- optimistic edit locking and conflict recovery;
- relation editor and internal-link reporting;
- Loading, Error and Empty report states;
- direct issue-to-editor navigation;
- redirect loop/traversal prevention;
- authoritative indexable URL feed.

## Pre-server acceptance

Before server entry, source-level acceptance requires:

- no new feature scope in this PR;
- no raw HTML editor path;
- no silent concurrent overwrite;
- no admin page without role guard and noindex;
- no report action pointing to a dead route;
- responsive grids and bounded overflow lists;
- explicit Loading, Error and Empty states;
- frontend/backend audits wired into permanent checks.

## Server acceptance still required

Source-level gates cannot prove runtime behavior. On Staging we must still run:

- frozen frontend install, TypeScript, ESLint, tests and production build;
- route-tree generation and SSR View Source checks;
- install from the committed Composer lock;
- migrations, seeders, PHPUnit, Larastan and Pint;
- Sanctum/CORS/CSRF acceptance;
- database-specific link-report queries;
- browser tests at mobile, tablet and desktop sizes.

Production indexing remains disabled until those runtime gates are green.

## Remaining product work outside this foundation

- migrate legacy static articles with human review;
- create public author pages before emitting author profile URLs;
- approve legal, trust, support and partnership content;
- split sitemap into an index when URL volume requires it.
