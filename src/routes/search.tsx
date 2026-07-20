import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { fallback, zodValidator } from "@tanstack/zod-adapter";
import { Search as SearchIcon, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { z } from "zod";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { CatalogProductCard } from "@/components/catalog/CatalogProductCard";
import { CatalogRoasteryCard } from "@/components/catalog/CatalogRoasteryCard";
import { searchCatalogQueryOptions } from "@/lib/api/catalog";
import { isApiError } from "@/lib/api/client";
import type { SearchCatalogType } from "@/lib/api/contracts";
import { absoluteUrl } from "@/config/site";

const searchSchema = z.object({
  q: fallback(z.string(), "").default(""),
  type: fallback(z.enum(["all", "products", "roasteries", "content"]), "all").default("all"),
});

type SearchParams = z.infer<typeof searchSchema>;
const RECENT_KEY = "rosta_recent_searches";

export const Route = createFileRoute("/search")({
  validateSearch: zodValidator(searchSchema),
  head: () => ({
    meta: [
      { title: "جستجو در رستا | دانه قهوه و روستری" },
      { name: "description", content: "جستجوی دانه کامل قهوه، روستری و خاستگاه در رستا." },
      { name: "robots", content: "noindex,follow" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/search") }],
  }),
  component: SearchPage,
});

function readRecent(): string[] {
  if (typeof window === "undefined") return [];
  try {
    const parsed = JSON.parse(localStorage.getItem(RECENT_KEY) || "[]");
    return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === "string").slice(0, 5) : [];
  } catch {
    return [];
  }
}

function SearchPage() {
  const { q, type } = Route.useSearch();
  const navigate = useNavigate({ from: "/search" });
  const inputRef = useRef<HTMLInputElement>(null);
  const [localQuery, setLocalQuery] = useState(q);
  const [recent, setRecent] = useState<string[]>([]);
  const normalized = q.trim();
  const query = useQuery(searchCatalogQueryOptions(normalized, type as SearchCatalogType));

  useEffect(() => {
    inputRef.current?.focus();
    setRecent(readRecent());
  }, []);

  useEffect(() => {
    setLocalQuery(q);
  }, [q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (localQuery === q) return;
      navigate({
        search: (previous: SearchParams) => ({ ...previous, q: localQuery }),
        replace: true,
      });
    }, 200);
    return () => window.clearTimeout(timer);
  }, [localQuery, navigate, q]);

  const saveRecent = (term: string) => {
    const value = term.trim();
    if (!value) return;
    const next = [value, ...recent.filter((item) => item !== value)].slice(0, 5);
    setRecent(next);
    try {
      localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch {
      // Recent searches are an optional local-only UX enhancement.
    }
  };

  const setType = (nextType: SearchCatalogType) =>
    navigate({
      search: (previous: SearchParams) => ({ ...previous, type: nextType }),
      replace: true,
    });

  const fallbackSuggestions = ["اتیوپی", "کلمبیا", "رست روشن", "اسپرسو"];
  const suggestions = query.data?.suggestions.length ? query.data.suggestions : fallbackSuggestions;
  const products = query.data?.products ?? [];
  const roasteries = query.data?.roasteries ?? [];
  const showProducts = type === "all" || type === "products";
  const showRoasteries = type === "all" || type === "roasteries";
  const hasResult = (showProducts && products.length > 0) || (showRoasteries && roasteries.length > 0);

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <h1 className="sr-only">جستجو در رستا</h1>
        <div className="sticky top-16 z-30 -mx-4 border-b border-[color:var(--mid)] bg-[color:var(--night)]/95 px-4 py-3 backdrop-blur-lg">
          <form
            onSubmit={(event) => {
              event.preventDefault();
              saveRecent(localQuery);
            }}
            className="relative"
          >
            <SearchIcon size={18} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[color:var(--roast)]" />
            <input
              ref={inputRef}
              type="search"
              value={localQuery}
              onChange={(event) => setLocalQuery(event.target.value)}
              placeholder="نام دانه، روستری یا خاستگاه…"
              aria-label="جستجو"
              className="w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] py-3 pl-10 pr-10 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]"
            />
            {localQuery ? (
              <button
                type="button"
                aria-label="پاک کردن جستجو"
                onClick={() => setLocalQuery("")}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-[color:var(--light)]"
              >
                <X size={16} />
              </button>
            ) : null}
          </form>
          <div className="mt-3 flex gap-2 overflow-x-auto" role="group" aria-label="نوع نتیجه">
            {[
              { value: "all", label: "همه" },
              { value: "products", label: "دانه‌ها" },
              { value: "roasteries", label: "روستری‌ها" },
            ].map((item) => (
              <button
                key={item.value}
                type="button"
                onClick={() => setType(item.value as SearchCatalogType)}
                className={`whitespace-nowrap rounded-full border px-4 py-1.5 text-xs transition ${
                  type === item.value
                    ? "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]"
                    : "border-[color:var(--mid)] text-[color:var(--light)]"
                }`}
              >
                {item.label}
              </button>
            ))}
          </div>
        </div>

        {!normalized ? (
          <section className="mt-8">
            {recent.length ? (
              <div>
                <div className="flex items-center justify-between gap-4">
                  <h2 className="text-sm font-bold text-[color:var(--light)]">جستجوهای اخیر</h2>
                  <button
                    type="button"
                    onClick={() => {
                      setRecent([]);
                      localStorage.removeItem(RECENT_KEY);
                    }}
                    className="text-xs text-[color:var(--roast)]"
                  >
                    پاک‌کردن
                  </button>
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                  {recent.map((item) => (
                    <button key={item} type="button" onClick={() => setLocalQuery(item)} className="rounded-full border border-[color:var(--mid)] bg-[color:var(--dark)] px-3 py-1 text-xs text-[color:var(--light)]">
                      {item}
                    </button>
                  ))}
                </div>
              </div>
            ) : null}
            <h2 className="mt-7 text-sm font-bold text-[color:var(--light)]">پیشنهادها</h2>
            <div className="mt-3 flex flex-wrap gap-2">
              {fallbackSuggestions.map((item) => (
                <button key={item} type="button" onClick={() => setLocalQuery(item)} className="rounded-full border border-[color:var(--roast)] px-3 py-1 text-xs text-[color:var(--roast)]">
                  {item}
                </button>
              ))}
            </div>
          </section>
        ) : query.isPending ? (
          <div className="mt-16 text-center" role="status">
            <div className="mx-auto size-8 animate-spin rounded-full border-2 border-[color:var(--roast)] border-t-transparent" />
            <p className="mt-3 text-sm text-[color:var(--light)]">در حال جستجو…</p>
          </div>
        ) : query.isError ? (
          <section className="mt-12 rounded-2xl border border-red-400/40 p-6 text-center">
            <h2 className="font-bold">جستجو انجام نشد</h2>
            <p className="mt-2 text-sm text-[color:var(--light)]">
              {isApiError(query.error) ? query.error.message : "ارتباط با سرویس جستجو برقرار نشد."}
            </p>
            <button type="button" onClick={() => query.refetch()} className="mt-4 text-sm text-[color:var(--roast)]">تلاش مجدد</button>
          </section>
        ) : !hasResult ? (
          <section className="mt-16 text-center">
            <h2 className="text-lg font-bold">نتیجه‌ای برای «{normalized}» پیدا نشد</h2>
            <p className="mt-2 text-sm text-[color:var(--light)]">یکی از پیشنهادهای زیر را امتحان کنید.</p>
            <div className="mt-4 flex flex-wrap justify-center gap-2">
              {suggestions.map((item) => (
                <button key={item} type="button" onClick={() => setLocalQuery(item)} className="rounded-full border border-[color:var(--roast)] px-3 py-1 text-xs text-[color:var(--roast)]">{item}</button>
              ))}
            </div>
          </section>
        ) : (
          <>
            {showRoasteries && roasteries.length ? (
              <section className="mt-8">
                <h2 className="mb-4 text-xl font-bold">روستری‌ها</h2>
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                  {roasteries.map((roastery) => <CatalogRoasteryCard key={roastery.id} roastery={roastery} />)}
                </div>
              </section>
            ) : null}
            {showProducts && products.length ? (
              <section className="mt-10">
                <h2 className="mb-4 text-xl font-bold">دانه‌های قهوه</h2>
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                  {products.map((product) => <CatalogProductCard key={product.id} product={product} />)}
                </div>
              </section>
            ) : null}
          </>
        )}

        <div className="mt-10 text-center text-xs text-[color:var(--light)]">
          <Link to="/products" className="underline underline-offset-4">مشاهده همه محصولات</Link>
        </div>
      </main>
      <Footer />
    </>
  );
}
