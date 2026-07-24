import { createFileRoute, Link } from "@tanstack/react-router";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { EmptyState } from "@/components/system";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { absoluteUrl } from "@/config/site";
import { blogIndexQueryOptions, type PublicContentSummary } from "@/lib/api/public-content";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/blog/")({
  loader: ({ context }) => context.queryClient.ensureQueryData(blogIndexQueryOptions()),
  head: () => ({
    meta: [
      { title: "مجله رستا — راهنمای قهوه، دم‌آوری و رست" },
      {
        name: "description",
        content: "مقالات منتشرشده تحریریه رستا درباره دانه کامل، تازگی، روستری و دم‌آوری.",
      },
      { property: "og:title", content: "مجله رستا — راهنمای قهوه" },
      { property: "og:url", content: absoluteUrl("/blog") },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/blog") }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify(
          breadcrumbJsonLd([
            { label: "خانه", to: "/" },
            { label: "مجله", to: "/blog" },
          ]),
        ),
      },
    ],
  }),
  component: BlogIndex,
});

function BlogIndex() {
  const entries: PublicContentSummary[] = Route.useLoaderData();
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-10">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "مجله" }]} />
        <header className="mb-10">
          <span className="eyebrow">مجله رستا</span>
          <h1 className="mt-3 font-display text-4xl font-bold text-[color:var(--steam)] md:text-5xl">
            راهنمای زنده قهوه و دم‌آوری
          </h1>
          <p className="mt-3 max-w-2xl text-sm leading-7 text-[color:var(--light)]">
            فقط محتوای منتشرشده و تأییدشده CMS نمایش داده می‌شود؛ هیچ مقاله Seed در Production وجود
            ندارد.
          </p>
        </header>
        {!entries.length ? (
          <EmptyState
            title="مقاله منتشرشده‌ای وجود ندارد"
            description="پس از انتشار نخستین مقاله در فضای تحریریه، این صفحه خودکار به‌روزرسانی می‌شود."
          />
        ) : (
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {entries.map((entry, index) => (
              <article
                key={entry.id}
                className="card-dark card-dark-hover group flex flex-col overflow-hidden rounded-2xl"
              >
                <Link
                  to="/blog/$slug"
                  params={{ slug: entry.slug }}
                  className="flex flex-1 flex-col"
                >
                  <div className="grid aspect-[16/9] place-items-center bg-[radial-gradient(circle_at_top,rgba(200,150,90,0.25),transparent_65%)] font-display text-6xl font-bold text-[color:var(--roast)]">
                    {toFa(index + 1)}
                  </div>
                  <div className="flex flex-1 flex-col p-5">
                    <div className="flex items-center justify-between gap-3 text-[11px] text-[color:var(--muted-gold)]">
                      <span>{entry.type === "guide" ? "راهنما" : "مقاله"}</span>
                      {entry.published_at ? (
                        <time dateTime={entry.published_at}>
                          {new Date(entry.published_at).toLocaleDateString("fa-IR")}
                        </time>
                      ) : null}
                    </div>
                    <h2 className="mt-3 font-display text-lg font-bold leading-snug text-[color:var(--steam)] group-hover:text-[color:var(--roast)]">
                      {entry.title}
                    </h2>
                    <p className="mt-3 line-clamp-4 text-sm leading-7 text-[color:var(--light)]">
                      {entry.excerpt || entry.seo.description || "مطلب تحریریه رستا"}
                    </p>
                    <p className="mt-auto pt-5 text-xs text-[color:var(--muted-gold)]">
                      {entry.author?.name || "تحریریه رستا"}
                    </p>
                  </div>
                </Link>
              </article>
            ))}
          </div>
        )}
      </main>
      <Footer />
    </>
  );
}
