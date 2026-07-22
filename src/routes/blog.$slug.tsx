import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { ProductCard } from "@/components/ProductCard";
import { absoluteUrl } from "@/config/site";
import { getBlogPost, relatedPosts } from "@/data/blog-posts";
import { getProduct } from "@/data/seed";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/blog/$slug")({
  loader: ({ params }) => {
    const post = getBlogPost(params.slug);
    if (!post) throw notFound();
    const related = post.relatedProductSlugs
      .map((slug) => getProduct(slug))
      .filter((product): product is NonNullable<typeof product> =>
        Boolean(product),
      );
    return { post, related, more: relatedPosts(params.slug, 3) };
  },
  head: ({ params, loaderData }) => {
    if (!loaderData) {
      return {
        meta: [
          { title: "مقاله پیدا نشد | رستا" },
          { name: "robots", content: "noindex" },
        ],
      };
    }

    const { post } = loaderData;
    const path = `/blog/${params.slug}`;
    const canonical = absoluteUrl(path);
    const title = `${post.title} | مجله رستا`;

    return {
      meta: [
        { title },
        { name: "description", content: post.excerpt },
        { name: "keywords", content: post.keywords.join(", ") },
        { property: "og:title", content: title },
        { property: "og:description", content: post.excerpt },
        { property: "og:url", content: canonical },
        { property: "og:type", content: "article" },
        { property: "article:published_time", content: post.publishedAt },
      ],
      links: [{ rel: "canonical", href: canonical }],
      scripts: [
        {
          type: "application/ld+json",
          children: JSON.stringify({
            "@context": "https://schema.org",
            "@type": "Article",
            headline: post.title,
            description: post.excerpt,
            datePublished: post.publishedAt,
            dateModified: post.publishedAt,
            author: { "@type": "Organization", name: "رستا" },
            publisher: {
              "@type": "Organization",
              name: "رستا",
              logo: {
                "@type": "ImageObject",
                url: absoluteUrl("/favicon.ico"),
              },
            },
            mainEntityOfPage: canonical,
          }),
        },
        {
          type: "application/ld+json",
          children: JSON.stringify(
            breadcrumbJsonLd([
              { label: "خانه", to: "/" },
              { label: "مجله", to: "/blog" },
              { label: post.title, to: path },
            ]),
          ),
        },
      ],
    };
  },
  notFoundComponent: () => (
    <>
      <Navbar />
      <main className="mx-auto max-w-3xl px-4 py-20 text-center">
        <h1 className="font-display text-3xl font-bold text-[color:var(--steam)]">
          مقاله پیدا نشد
        </h1>
        <Link
          to="/blog"
          className="mt-6 inline-block text-[color:var(--roast)] hover:underline"
        >
          بازگشت به مجله
        </Link>
      </main>
      <Footer />
    </>
  ),
  component: BlogPostPage,
});

function BlogPostPage() {
  const { post, related, more } = Route.useLoaderData();

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-3xl px-4 py-10">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "مجله", to: "/blog" },
            { label: post.title },
          ]}
        />

        <div
          aria-hidden
          className="mb-8 grid aspect-[16/8] place-items-center rounded-2xl text-7xl"
          style={{ background: post.coverGradient }}
        >
          <span>{post.coverEmoji}</span>
        </div>

        <header className="mb-8">
          <h1 className="font-display text-3xl font-bold leading-tight text-[color:var(--steam)] md:text-5xl">
            {post.title}
          </h1>
          <div className="mt-4 flex flex-wrap items-center gap-4 text-xs text-[color:var(--muted-gold)]">
            <time dateTime={post.publishedAt}>{toFa(post.publishedAt)}</time>
            <span aria-hidden>·</span>
            <span>{toFa(post.readMinutes)} دقیقه مطالعه</span>
            <span aria-hidden>·</span>
            <span>نویسنده: رستا</span>
          </div>
        </header>

        <div
          className="blog-content space-y-4 text-[color:var(--light)] [&_h2]:mt-10 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-[color:var(--steam)] [&_h3]:mt-6 [&_h3]:text-lg [&_h3]:font-bold [&_h3]:text-[color:var(--steam)] [&_p]:mt-4 [&_p]:leading-8 [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pr-6 [&_ol]:mt-4 [&_ol]:list-decimal [&_ol]:pr-6 [&_li]:mt-2 [&_li]:leading-8 [&_a]:text-[color:var(--roast)] [&_a]:underline [&_strong]:text-[color:var(--steam)]"
          dangerouslySetInnerHTML={{ __html: post.content }}
        />

        {related.length > 0 ? (
          <section className="mt-16">
            <h2 className="font-display text-2xl font-bold text-[color:var(--steam)]">
              محصولات مرتبط
            </h2>
            <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {related.map((product: import("@/data/seed").Product) => (
                <ProductCard key={product.slug} product={product} />
              ))}
            </div>
          </section>
        ) : null}

        <section className="mt-16 border-t border-[color:var(--mid)] pt-10">
          <h2 className="font-display text-2xl font-bold text-[color:var(--steam)]">
            مقالات مرتبط
          </h2>
          <div className="mt-6 grid gap-4 sm:grid-cols-3">
            {more.map((item: import("@/data/blog-posts").BlogPost) => (
              <Link
                key={item.slug}
                to="/blog/$slug"
                params={{ slug: item.slug }}
                className="card-dark card-dark-hover rounded-xl p-5"
              >
                <div className="text-2xl">{item.coverEmoji}</div>
                <h3 className="mt-3 font-display text-base font-bold leading-snug text-[color:var(--steam)]">
                  {item.title}
                </h3>
                <p className="mt-2 text-xs text-[color:var(--light)]">
                  {toFa(item.readMinutes)} دقیقه
                </p>
              </Link>
            ))}
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
