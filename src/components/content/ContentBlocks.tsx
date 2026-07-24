import { Link } from "@tanstack/react-router";
import type { ContentBlock } from "@/lib/api/content";

export function ContentBlocks({ blocks }: { blocks: ContentBlock[] }) {
  return (
    <div className="space-y-6 text-[color:var(--light)]">
      {blocks.map((block, index) => {
        const key = `${block.type}-${index}`;
        if (block.type === "paragraph")
          return (
            <p key={key} className="leading-8">
              {block.text}
            </p>
          );
        if (block.type === "heading") {
          return block.level === 2 ? (
            <h2
              key={key}
              className="mt-10 font-display text-2xl font-bold text-[color:var(--steam)]"
            >
              {block.text}
            </h2>
          ) : (
            <h3 key={key} className="mt-8 text-xl font-bold text-[color:var(--steam)]">
              {block.text}
            </h3>
          );
        }
        if (block.type === "list") {
          const Tag = block.style === "ordered" ? "ol" : "ul";
          return (
            <Tag
              key={key}
              className={`${block.style === "ordered" ? "list-decimal" : "list-disc"} space-y-2 pe-6 leading-8`}
            >
              {block.items.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </Tag>
          );
        }
        if (block.type === "quote")
          return (
            <blockquote
              key={key}
              className="border-e-4 border-[color:var(--roast)] bg-[color:var(--dark)] p-5 leading-8"
            >
              <p>{block.text}</p>
              {block.citation ? (
                <cite className="mt-3 block text-xs text-[color:var(--muted-gold)]">
                  {block.citation}
                </cite>
              ) : null}
            </blockquote>
          );
        if (block.type === "callout")
          return (
            <aside
              key={key}
              className="rounded-2xl border border-[color:var(--roast)]/40 bg-[color:var(--dark)] p-5 leading-8"
            >
              <strong className="text-[color:var(--roast)]">
                {block.tone === "warning" ? "توجه" : block.tone === "tip" ? "نکته" : "اطلاعات"}
              </strong>
              <p className="mt-2">{block.text}</p>
            </aside>
          );
        if (block.type === "faq")
          return (
            <section key={key} className="space-y-3">
              {block.items.map((item) => (
                <details
                  key={item.question}
                  className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4"
                >
                  <summary className="cursor-pointer font-bold text-[color:var(--steam)]">
                    {item.question}
                  </summary>
                  <p className="mt-3 leading-8">{item.answer}</p>
                </details>
              ))}
            </section>
          );
        if (block.type === "product_grid")
          return (
            <section key={key} className="rounded-2xl border border-[color:var(--mid)] p-5">
              <h3 className="font-bold text-[color:var(--steam)]">محصولات مرتبط</h3>
              <div className="mt-4 flex flex-wrap gap-3">
                {block.product_slugs.map((slug) => (
                  <Link
                    key={slug}
                    to="/products/$slug"
                    params={{ slug }}
                    className="rounded-xl border border-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--roast)]"
                  >
                    مشاهده {slug}
                  </Link>
                ))}
              </div>
            </section>
          );
        if (block.type === "roastery_spotlight")
          return (
            <Link
              key={key}
              to="/roasteries/$slug"
              params={{ slug: block.roastery_slug }}
              className="block rounded-2xl border border-[color:var(--roast)] bg-[color:var(--dark)] p-5 font-bold text-[color:var(--roast)]"
            >
              مشاهده روستری مرتبط ←
            </Link>
          );
        if (block.type === "comparison_table")
          return (
            <div key={key} className="overflow-x-auto">
              <table className="w-full min-w-[560px] border-collapse text-sm">
                <thead>
                  <tr>
                    {block.columns.map((column) => (
                      <th
                        key={column}
                        className="border border-[color:var(--mid)] bg-[color:var(--dark)] p-3 text-start text-[color:var(--steam)]"
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
                          className="border border-[color:var(--mid)] p-3"
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
        return null;
      })}
    </div>
  );
}
