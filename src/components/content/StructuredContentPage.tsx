import { Link } from "@tanstack/react-router";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import type { ContentBlock, ContentEntry } from "@/lib/api/content";

function ContentBlockView({ block }: { block: ContentBlock }) {
  switch (block.type) {
    case "paragraph":
      return <p className="text-base leading-9 text-[color:var(--light)]">{block.text}</p>;
    case "heading":
      return block.level === 2 ? (
        <h2 className="pt-5 text-2xl font-bold text-[color:var(--steam)]">{block.text}</h2>
      ) : (
        <h3 className="pt-3 text-xl font-bold text-[color:var(--steam)]">{block.text}</h3>
      );
    case "list": {
      const Tag = block.style === "ordered" ? "ol" : "ul";
      return (
        <Tag className={`space-y-2 pe-6 text-sm leading-8 text-[color:var(--light)] ${block.style === "ordered" ? "list-decimal" : "list-disc"}`}>
          {block.items.map((item, index) => <li key={`${index}-${item}`}>{item}</li>)}
        </Tag>
      );
    }
    case "quote":
      return (
        <figure className="rounded-2xl border border-[color:var(--mid)] bg-black/10 p-5">
          <blockquote className="text-lg leading-9 text-[color:var(--steam)]">{block.text}</blockquote>
          {block.citation ? <figcaption className="mt-3 text-xs text-[color:var(--roast)]">— {block.citation}</figcaption> : null}
        </figure>
      );
    case "callout":
      return (
        <aside className="rounded-2xl border border-[color:var(--roast)]/40 bg-[color:var(--roast)]/10 p-5 text-sm leading-8 text-[color:var(--steam)]" data-tone={block.tone}>
          {block.text}
        </aside>
      );
    case "faq":
      return (
        <section aria-labelledby="content-faq-title" className="space-y-3">
          <h2 id="content-faq-title" className="text-2xl font-bold text-[color:var(--steam)]">پرسش‌های متداول</h2>
          {block.items.map((item) => (
            <details key={item.question} className="rounded-xl border border-[color:var(--mid)] p-4">
              <summary className="cursor-pointer font-bold text-[color:var(--steam)]">{item.question}</summary>
              <p className="mt-3 text-sm leading-8 text-[color:var(--light)]">{item.answer}</p>
            </details>
          ))}
        </section>
      );
    case "product_grid":
      return (
        <section aria-labelledby="recommended-products-title">
          <h2 id="recommended-products-title" className="text-2xl font-bold text-[color:var(--steam)]">محصولات مرتبط</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            {block.product_slugs.map((slug) => (
              <Link
                key={slug}
                to="/products/$slug"
                params={{ slug }}
                className="rounded-xl border border-[color:var(--mid)] p-4 font-bold text-[color:var(--roast)] transition hover:border-[color:var(--roast)]"
              >
                مشاهده محصول {slug}
              </Link>
            ))}
          </div>
        </section>
      );
    case "roastery_spotlight":
      return (
        <Link
          to="/roasteries/$slug"
          params={{ slug: block.roastery_slug }}
          className="block rounded-2xl border border-[color:var(--mid)] p-5 text-[color:var(--roast)] transition hover:border-[color:var(--roast)]"
        >
          مشاهده روستری {block.roastery_slug}
        </Link>
      );
    case "comparison_table":
      return (
        <div className="overflow-x-auto rounded-2xl border border-[color:var(--mid)]">
          <table className="w-full min-w-[640px] border-collapse text-sm">
            <thead className="bg-black/20 text-[color:var(--steam)]">
              <tr>{block.columns.map((column) => <th key={column} scope="col" className="border-b border-[color:var(--mid)] p-3 text-start">{column}</th>)}</tr>
            </thead>
            <tbody>
              {block.rows.map((row, rowIndex) => (
                <tr key={rowIndex}>
                  {row.map((cell, cellIndex) => <td key={`${rowIndex}-${cellIndex}`} className="border-b border-[color:var(--mid)]/60 p-3 leading-7 text-[color:var(--light)]">{cell}</td>)}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      );
  }
}

export function StructuredContentPage({ entry }: { entry: ContentEntry }) {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: entry.title }]} />
        <article className="mt-8">
          <header className="border-b border-[color:var(--mid)] pb-8">
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-[color:var(--roast)]">{entry.type}</p>
            <h1 className="mt-3 text-3xl font-bold leading-tight text-[color:var(--steam)] sm:text-5xl">{entry.title}</h1>
            {entry.excerpt ? <p className="mt-5 max-w-3xl text-base leading-9 text-[color:var(--light)]">{entry.excerpt}</p> : null}
            <div className="mt-5 flex flex-wrap gap-x-4 gap-y-2 text-xs text-[color:var(--light)]">
              {entry.author ? <span>نویسنده: {entry.author.name}</span> : null}
              {entry.published_at ? <time dateTime={entry.published_at}>{new Date(entry.published_at).toLocaleDateString("fa-IR")}</time> : null}
              {entry.updated_at ? <span>به‌روزرسانی: {new Date(entry.updated_at).toLocaleDateString("fa-IR")}</span> : null}
            </div>
          </header>

          <div className="mt-8 space-y-7">
            {entry.body.map((block, index) => <ContentBlockView key={`${entry.content_hash}-${index}`} block={block} />)}
          </div>

          {entry.author ? (
            <aside className="mt-12 rounded-2xl border border-[color:var(--mid)] p-5">
              <h2 className="font-bold text-[color:var(--steam)]">درباره {entry.author.name}</h2>
              {entry.author.bio ? <p className="mt-3 text-sm leading-8 text-[color:var(--light)]">{entry.author.bio}</p> : null}
              {entry.author.credentials.length ? (
                <ul className="mt-3 flex flex-wrap gap-2">
                  {entry.author.credentials.map((credential) => <li key={credential} className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-xs text-[color:var(--roast)]">{credential}</li>)}
                </ul>
              ) : null}
            </aside>
          ) : null}
        </article>
      </main>
      <Footer />
    </>
  );
}
