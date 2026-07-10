import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useEffect, useMemo, useRef, useState } from "react";
import { Search as SearchIcon, X } from "lucide-react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { ProductCard } from "@/components/ProductCard";
import { RoasteryCard } from "@/components/RoasteryCard";
import { products, roasteries } from "@/data/seed";
import { z } from "zod";
import { zodValidator, fallback } from "@tanstack/zod-adapter";

const searchSchema = z.object({
  q: fallback(z.string(), "").default(""),
  type: fallback(z.string(), "all").default("all"),
});

const RECENT_KEY = "rosta_recent_searches";

export const Route = createFileRoute("/search")({
  validateSearch: zodValidator(searchSchema),
  head: ({ search }) => {
    const hasQ = !!search.q;
    return {
      meta: [
        { title: hasQ ? `جستجو: ${search.q} | رستا` : "جستجو در رستا | قهوه و روستری" },
        {
          name: "description",
          content: "جستجوی قهوه، روستری، خاستگاه و نوع رست در مارکت‌پلیس رستا.",
        },
        { name: "robots", content: hasQ ? "noindex,follow" : "index,follow" },
      ],
      links: [{ rel: "canonical", href: "/search" }],
    };
  },
  component: SearchPage,
});

function score(text: string, q: string): number {
  const t = text.toLowerCase();
  const s = q.toLowerCase();
  if (!s) return 0;
  if (t === s) return 100;
  if (t.startsWith(s)) return 60;
  if (t.includes(s)) return 30;
  return 0;
}

function SearchPage() {
  const { q, type } = Route.useSearch();
  const navigate = useNavigate({ from: "/search" });
  const inputRef = useRef<HTMLInputElement>(null);
  const [local, setLocal] = useState(q);
  const [recent, setRecent] = useState<string[]>([]);

  useEffect(() => {
    inputRef.current?.focus();
    try {
      const r = localStorage.getItem(RECENT_KEY);
      if (r) setRecent(JSON.parse(r));
    } catch {}
  }, []);

  // Debounce URL update
  useEffect(() => {
    const t = setTimeout(() => {
      navigate({ search: (prev) => ({ ...prev, q: local }), replace: true });
    }, 200);
    return () => clearTimeout(t);
  }, [local, navigate]);

  const results = useMemo(() => {
    if (!q.trim()) return { products: [], roasteries: [] };
    const ps = products
      .map((p) => ({
        item: p,
        s: Math.max(score(p.name, q), score(p.origin, q), score(p.roasterySlug, q)),
      }))
      .filter((x) => x.s > 0)
      .sort((a, b) => b.s - a.s)
      .map((x) => x.item);
    const rs = roasteries
      .map((r) => ({ item: r, s: Math.max(score(r.name, q), score(r.city, q)) }))
      .filter((x) => x.s > 0)
      .sort((a, b) => b.s - a.s)
      .map((x) => x.item);
    return { products: ps, roasteries: rs };
  }, [q]);

  const showProducts = type === "all" || type === "products";
  const showRoasteries = type === "all" || type === "roasteries";

  function commitRecent(term: string) {
    if (!term.trim()) return;
    const next = [term, ...recent.filter((r) => r !== term)].slice(0, 5);
    setRecent(next);
    try {
      localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch {}
  }

  const suggestions = ["اتیوپی", "اسپرسو", "رست تیره", "۲۵۰ گرم"];

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <h1 className="sr-only">جستجو در رستا</h1>
        <div className="sticky top-16 z-30 -mx-4 border-b border-[color:var(--mid)] bg-[color:var(--night)]/95 px-4 py-3 backdrop-blur-lg">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              commitRecent(local);
            }}
            className="relative"
          >
            <SearchIcon
              size={18}
              className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[color:var(--roast)]"
            />
            <input
              ref={inputRef}
              type="search"
              value={local}
              onChange={(e) => setLocal(e.target.value)}
              placeholder="نام قهوه، روستری، خاستگاه…"
              aria-label="جستجو"
              className="w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] py-3 pr-10 pl-10 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]"
            />
            {local && (
              <button
                type="button"
                aria-label="پاک کردن"
                onClick={() => setLocal("")}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-[color:var(--light)]"
              >
                <X size={16} />
              </button>
            )}
          </form>

          <div className="mt-3 flex gap-2 overflow-x-auto">
            {[
              { k: "all", l: "همه" },
              { k: "products", l: "دانه" },
              { k: "roasteries", l: "روستری‌ها" },
            ].map((c) => {
              const active = type === c.k;
              return (
                <button
                  key={c.k}
                  onClick={() =>
                    navigate({ search: (prev) => ({ ...prev, type: c.k }), replace: true })
                  }
                  className={`whitespace-nowrap rounded-full border px-4 py-1.5 text-xs transition ${
                    active
                      ? "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]"
                      : "border-[color:var(--mid)] text-[color:var(--light)]"
                  }`}
                >
                  {c.l}
                </button>
              );
            })}
          </div>
        </div>

        {!q.trim() && (
          <section className="mt-8">
            {recent.length > 0 && (
              <>
                <h2 className="text-sm font-bold text-[color:var(--light)]">جستجوهای اخیر</h2>
                <div className="mt-2 flex flex-wrap gap-2">
                  {recent.map((r) => (
                    <button
                      key={r}
                      onClick={() => setLocal(r)}
                      className="rounded-full border border-[color:var(--mid)] bg-[color:var(--dark)] px-3 py-1 text-xs text-[color:var(--light)]"
                    >
                      {r}
                    </button>
                  ))}
                </div>
              </>
            )}
            <h2 className="mt-6 text-sm font-bold text-[color:var(--light)]">پیشنهاد محبوب</h2>
            <div className="mt-2 flex flex-wrap gap-2">
              {suggestions.map((s) => (
                <button
                  key={s}
                  onClick={() => setLocal(s)}
                  className="rounded-full border border-[color:var(--roast)] px-3 py-1 text-xs text-[color:var(--roast)]"
                >
                  {s}
                </button>
              ))}
            </div>
          </section>
        )}

        {q.trim() &&
          results.products.length === 0 &&
          results.roasteries.length === 0 && (
            <div className="mt-16 text-center">
              <p className="text-lg font-bold text-[color:var(--steam)]">چیزی پیدا نشد</p>
              <p className="mt-2 text-sm text-[color:var(--light)]">
                این‌ها را امتحان کنید:
              </p>
              <div className="mt-3 flex flex-wrap justify-center gap-2">
                {suggestions.map((s) => (
                  <button
                    key={s}
                    onClick={() => setLocal(s)}
                    className="rounded-full border border-[color:var(--roast)] px-3 py-1 text-xs text-[color:var(--roast)]"
                  >
                    {s}
                  </button>
                ))}
              </div>
            </div>
          )}

        {showRoasteries && results.roasteries.length > 0 && (
          <section className="mt-8">
            <h2 className="mb-3 text-lg font-bold text-[color:var(--steam)]">روستری‌ها</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {results.roasteries.map((r) => (
                <RoasteryCard key={r.slug} roastery={r} />
              ))}
            </div>
          </section>
        )}

        {showProducts && results.products.length > 0 && (
          <section className="mt-8">
            <h2 className="mb-3 text-lg font-bold text-[color:var(--steam)]">محصولات</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {results.products.map((p) => (
                <ProductCard key={p.slug} product={p} />
              ))}
            </div>
          </section>
        )}

        <div className="mt-8 text-center text-xs text-[color:var(--light)]">
          <Link to="/products" className="underline">
            مشاهده همه محصولات
          </Link>
        </div>
      </main>
      <Footer />
    </>
  );
}
