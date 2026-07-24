import { Link } from "@tanstack/react-router";
import type { ContentBlock } from "@/lib/api/content";

export function StructuredContentBlock({ block }: { block: ContentBlock }) {
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
        <Tag
          className={`space-y-2 pe-6 text-sm leading-8 text-[color:var(--light)] ${block.style === "ordered" ? "list-decimal" : "list-disc"}`}
        >
          {block.items.map((item, index) => (
            <li key={`${index}-${item}`}>{item}</li>
          ))}
        </Tag>
      );
    }
    case "quote":
      return (
        <figure className="rounded-2xl border border-[color:var(--mid)] bg-black/10 p-5">
          <blockquote className="text-lg leading-9 text-[color:var(--steam)]">
            {block.text}
          </blockquote>
          {block.citation ? (
            <figcaption className="mt-3 text-xs text-[color:var(--roast)]">
              — {block.citation}
            </figcaption>
          ) : null}
        </figure>
      );
    case "callout":
      return (
        <aside
          className="rounded-2xl border border-[color:var(--roast)]/40 bg-[color:var(--roast)]/10 p-5 text-sm leading-8 text-[color:var(--steam)]"
          data-tone={block.tone}
        >
          {block.text}
        </aside>
      );
    case "faq":
      return (
        <section className="space-y-3">
          <h2 className="text-2xl font-bold text-[color:var(--steam)]">پرسش‌های متداول</h2>
          {block.items.map((item) => (
            <details
              key={item.question}
              className="rounded-xl border border-[color:var(--mid)] p-4"
            >
              <summary className="cursor-pointer font-bold text-[color:var(--steam)]">
                {item.question}
              </summary>
              <p className="mt-3 text-sm leading-8 text-[color:var(--light)]">{item.answer}</p>
            </details>
          ))}
        </section>
      );
    case "product_grid":
      return (
        <section>
          <h2 className="text-2xl font-bold text-[color:var(--steam)]">محصولات مرتبط</h2>
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
              <tr>
                {block.columns.map((column) => (
                  <th
                    key={column}
                    scope="col"
                    className="border-b border-[color:var(--mid)] p-3 text-start"
                  >
                    {column}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {block.rows.map((row, rowIndex) => (
                <tr key={rowIndex}>
                  {row.map((cell, cellIndex) => (
                    <td
                      key={`${rowIndex}-${cellIndex}`}
                      className="border-b border-[color:var(--mid)]/60 p-3 leading-7 text-[color:var(--light)]"
                    >
                      {cell}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      );
  }
}

export function StructuredContentBlocks({
  blocks,
  contentHash = "preview",
}: {
  blocks: readonly ContentBlock[];
  contentHash?: string;
}) {
  return (
    <div className="space-y-7">
      {blocks.map((block, index) => (
        <StructuredContentBlock key={`${contentHash}-${index}`} block={block} />
      ))}
    </div>
  );
}
