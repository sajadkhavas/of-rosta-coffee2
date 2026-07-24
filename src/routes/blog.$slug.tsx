import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { ContentBlocks } from "@/components/content/ContentBlocks";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl } from "@/config/site";
import { isApiError } from "@/lib/api/client";
import { blogEntryQueryOptions, blogIndexQueryOptions, type PublicContentSummary } from "@/lib/api/public-content";
import type { ContentEntry } from "@/lib/api/content";

export const Route = createFileRoute("/blog/$slug")({
  loader: async ({ params, context }) => {
    try {
      const [entry, index] = await Promise.all([
        context.queryClient.ensureQueryData(blogEntryQueryOptions(params.slug)),
        context.queryClient.ensureQueryData(blogIndexQueryOptions()),
      ]);
      if (entry.type !== "article" || !entry.canonical_path.startsWith("/blog/")) throw notFound();
      return {
        entry,
        more: index.filter((item) => item.slug !== params.slug).slice(0, 3),
      };
    } catch (error) {
      if (isApiError(error) && error.status === 404) throw notFound();
      throw error;
    }
  },
  head: ({ loaderData }) => {
    if (!loaderData) return { meta: [{ title: "مقاله پیدا نشد | رستا" }, { name: "robots", content: "noindex" }] };
    const { entry } = loaderData;
    const canonical = absoluteUrl(entry.canonical_path);
    const title = entry.seo.title || `${entry.title} | مجله رستا`;
    const description = entry.seo.description || entry.excerpt || entry.title;
    return {
      meta: [
        { title },
        { name: "description", content: description },
        { name: "keywords", content: entry.keywords.join(", ") },
        { property: "og:title", content: entry.seo.og_title || title },
        { property: "og:description", content: entry.seo.og_description || description },
        { property: "og:url", content: canonical },
        { property: "og:type", content: "article" },
        ...(entry.published_at ? [{ property: "article:published_time", content: entry.published_at }] : []),
        ...(entry.updated_at ? [{ property: "article:modified_time", content: entry.updated_at }] : []),
        { name: "robots", content: entry.seo.robots_index ? "index,follow" : "noindex,nofollow" },
      ],
      links: [{ rel: "canonical", href: canonical }],
      scripts: [
        {
          type: "application/ld+json",
          children: JSON.stringify({
            "@context": "https://schema.org",
            "@type": entry.seo.schema_type,
            headline: entry.title,
            description,
            datePublished: entry.published_at,
            dateModified: entry.updated_at || entry.published_at,
            author: { "@type": "Person", name: entry.author?.name || "تحریریه رستا" },
            publisher: { "@type": "Organization", name: "رستا", url: absoluteUrl("/") },
            mainEntityOfPage: canonical,
          }),
        },
        {
          type: "application/ld+json",
          children: JSON.stringify(breadcrumbJsonLd([
            { label: "خانه", to: "/" },
            { label: "مجله", to: "/blog" },
            { label: entry.title, to: entry.canonical_path },
          ])),
        },
      ],
    };
  },
  notFoundComponent: () => (
    <><Navbar /><main className="mx-auto max-w-3xl px-4 py-20 text-center"><h1 className="font-display text-3xl font-bold">مقاله پیدا نشد</h1><Link to="/blog" className="mt-6 inline-block text-[color:var(--roast)]">بازگشت به مجله</Link></main><Footer /></>
  ),
  component: BlogPostPage,
});

function BlogPostPage() {
  const { entry, more }: { entry: ContentEntry; more: PublicContentSummary[] } = Route.useLoaderData();
  const productRelations = entry.relations.filter((relation) => relation.target_type === "product");
  const roasteryRelations = entry.relations.filter((relation) => relation.target_type === "roastery");
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-3xl px-4 py-10">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "مجله", to: "/blog" }, { label: entry.title }]} />
        <header className="mb-10 border-b border-[color:var(--mid)] pb-8">
          <span className="eyebrow">{entry.author?.name || "تحریریه رستا"}</span>
          <h1 className="mt-3 font-display text-3xl font-bold leading-tight text-[color:var(--steam)] md:text-5xl">{entry.title}</h1>
          {entry.excerpt ? <p className="mt-5 text-base leading-8 text-[color:var(--light)]">{entry.excerpt}</p> : null}
          <div className="mt-4 flex flex-wrap gap-3 text-xs text-[color:var(--muted-gold)]">
            {entry.published_at ? <time dateTime={entry.published_at}>{new Date(entry.published_at).toLocaleDateString("fa-IR")}</time> : null}
            <span>نسخه محتوا: {entry.content_hash.slice(0, 10)}</span>
          </div>
        </header>
        <ContentBlocks blocks={entry.body} />

        {productRelations.length || roasteryRelations.length ? (
          <section className="mt-14 border-t border-[color:var(--mid)] pt-8">
            <h2 className="font-display text-2xl font-bold">پیوندهای مرتبط</h2>
            <div className="mt-5 flex flex-wrap gap-3">
              {productRelations.map((relation) => <Link key={relation.id} to="/products/$slug" params={{ slug: relation.target_key }} className="rounded-xl border border-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--roast)]">{relation.anchor_text || "محصول مرتبط"}</Link>)}
              {roasteryRelations.map((relation) => <Link key={relation.id} to="/roasteries/$slug" params={{ slug: relation.target_key }} className="rounded-xl border border-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--roast)]">{relation.anchor_text || "روستری مرتبط"}</Link>)}
            </div>
          </section>
        ) : null}

        {more.length ? (
          <section className="mt-14 border-t border-[color:var(--mid)] pt-8">
            <h2 className="font-display text-2xl font-bold">مطالب دیگر</h2>
            <div className="mt-5 grid gap-4 sm:grid-cols-3">{more.map((item) => <Link key={item.id} to="/blog/$slug" params={{ slug: item.slug }} className="card-dark rounded-xl p-5"><h3 className="font-bold leading-7">{item.title}</h3><p className="mt-2 text-xs text-[color:var(--light)]">{item.excerpt || "ادامه مطلب"}</p></Link>)}</div>
          </section>
        ) : null}
      </main>
      <Footer />
    </>
  );
}
