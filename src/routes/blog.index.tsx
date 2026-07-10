import { createFileRoute, Link } from "@tanstack/react-router";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { blogPosts } from "@/data/blog-posts";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/blog/")({
  head: () => ({
    meta: [
      { title: "مجله رستا — راهنمای قهوه، دم‌آوری و رست" },
      {
        name: "description",
        content:
          "مقالات تخصصی درباره قهوه، روش‌های دم‌آوری، انتخاب آسیاب و رازهای رست تازه در مجله رستا.",
      },
      { property: "og:title", content: "مجله رستا — راهنمای قهوه" },
      { property: "og:description", content: "مقالات تخصصی درباره قهوه، دم‌آوری و رست تازه." },
      { property: "og:url", content: "/blog" },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: "/blog" }],
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
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-10">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "مجله" }]} />
        <header className="mb-10">
          <span className="eyebrow">مجله رستا</span>
          <h1 className="mt-3 font-display text-4xl font-bold text-[color:var(--steam)] md:text-5xl">
            راهنمای قهوه، دم‌آوری و رست
          </h1>
          <p className="mt-3 max-w-2xl text-sm leading-7 text-[color:var(--light)]">
            مقاله‌های تخصصی درباره تازگی قهوه، روش‌های دم‌آوری و انتخاب دقیق‌ترین آسیاب برای دستگاهت.
          </p>
        </header>

        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {blogPosts.map((post) => (
            <article
              key={post.slug}
              className="card-dark card-dark-hover group flex flex-col overflow-hidden rounded-2xl"
            >
              <Link
                to="/blog/$slug"
                params={{ slug: post.slug }}
                className="flex flex-1 flex-col"
              >
                <div
                  aria-hidden
                  className="grid aspect-[16/10] place-items-center text-6xl"
                  style={{ background: post.coverGradient }}
                >
                  <span>{post.coverEmoji}</span>
                </div>
                <div className="flex flex-1 flex-col gap-3 p-5">
                  <div className="flex items-center justify-between text-[11px] text-[color:var(--muted-gold)]">
                    <span>{toFa(post.readMinutes)} دقیقه مطالعه</span>
                    <time dateTime={post.publishedAt}>{toFa(post.publishedAt)}</time>
                  </div>
                  <h2 className="font-display text-lg font-bold leading-snug text-[color:var(--steam)] transition group-hover:text-[color:var(--roast)]">
                    {post.title}
                  </h2>
                  <p className="text-sm leading-7 text-[color:var(--light)]">{post.excerpt}</p>
                  <span className="mt-auto text-sm font-bold text-[color:var(--roast)]">
                    ادامه مطلب ←
                  </span>
                </div>
              </Link>
            </article>
          ))}
        </div>
      </main>
      <Footer />
    </>
  );
}
