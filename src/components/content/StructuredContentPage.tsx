import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { StructuredContentBlocks } from "@/components/content/StructuredContentBlocks";
import type { ContentEntry } from "@/lib/api/content";

export function StructuredContentPage({ entry }: { entry: ContentEntry }) {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-4 py-8">
        <Breadcrumb
          items={[{ label: "خانه", to: "/" }, { label: entry.title }]}
        />
        <article className="mt-8">
          <header className="border-b border-[color:var(--mid)] pb-8">
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-[color:var(--roast)]">
              {entry.type}
            </p>
            <h1 className="mt-3 text-3xl font-bold leading-tight text-[color:var(--steam)] sm:text-5xl">
              {entry.title}
            </h1>
            {entry.excerpt ? (
              <p className="mt-5 max-w-3xl text-base leading-9 text-[color:var(--light)]">
                {entry.excerpt}
              </p>
            ) : null}
            <div className="mt-5 flex flex-wrap gap-x-4 gap-y-2 text-xs text-[color:var(--light)]">
              {entry.author ? <span>نویسنده: {entry.author.name}</span> : null}
              {entry.published_at ? (
                <time dateTime={entry.published_at}>
                  {new Date(entry.published_at).toLocaleDateString("fa-IR")}
                </time>
              ) : null}
              {entry.updated_at ? (
                <span>
                  به‌روزرسانی: {new Date(entry.updated_at).toLocaleDateString("fa-IR")}
                </span>
              ) : null}
            </div>
          </header>

          <div className="mt-8">
            <StructuredContentBlocks
              blocks={entry.body}
              contentHash={entry.content_hash}
            />
          </div>

          {entry.author ? (
            <aside className="mt-12 rounded-2xl border border-[color:var(--mid)] p-5">
              <h2 className="font-bold text-[color:var(--steam)]">
                درباره {entry.author.name}
              </h2>
              {entry.author.bio ? (
                <p className="mt-3 text-sm leading-8 text-[color:var(--light)]">
                  {entry.author.bio}
                </p>
              ) : null}
              {entry.author.credentials.length ? (
                <ul className="mt-3 flex flex-wrap gap-2">
                  {entry.author.credentials.map((credential) => (
                    <li
                      key={credential}
                      className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-xs text-[color:var(--roast)]"
                    >
                      {credential}
                    </li>
                  ))}
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
