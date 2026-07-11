import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useMemo } from "react";
import { z } from "zod";
import { zodValidator, fallback } from "@tanstack/zod-adapter";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { ProductCard } from "@/components/ProductCard";
import { products, roasteries, GRINDS, type RoastLevel, type Grind } from "@/data/seed";
import { toFa } from "@/lib/persian";

const ORIGINS = Array.from(new Set(products.map((p) => p.origin)));
const ROAST_LEVELS: RoastLevel[] = ["روشن", "متوسط", "تیره"];

const productsSearchSchema = z.object({
  origin: fallback(z.string(), "").default(""),
  roast: fallback(z.string(), "").default(""),
  grind: fallback(z.string(), "").default(""),
  roastery: fallback(z.string(), "").default(""),
});

type ProductsSearch = z.infer<typeof productsSearchSchema>;

function buildCanonical(search: ProductsSearch): string {
  const params = new URLSearchParams();
  if (search.origin) params.set("origin", search.origin);
  if (search.roast) params.set("roast", search.roast);
  if (search.grind) params.set("grind", search.grind);
  if (search.roastery) params.set("roastery", search.roastery);
  const qs = params.toString();
  return "/products" + (qs ? `?${qs}` : "");
}

export const Route = createFileRoute("/products/")({
  validateSearch: zodValidator(productsSearchSchema),
  head: ({ search }) => {
    const s = (search ?? { origin: "", roast: "", grind: "", roastery: "" }) as ProductsSearch;
    let title = "خرید دانه قهوه و قهوه اسپشیالیتی | رستا";
    let description =
      "خرید دانه قهوه تک‌خاستگاه از اتیوپی، کلمبیا، برزیل و کنیا. رست تازه از روستری‌های ایران با انتخاب آسیاب و ارسال سریع.";

    if (s.origin) {
      title = `قهوه تک خاستگاه ${s.origin} | خرید آنلاین | رستا`;
      description = `خرید قهوه تک‌خاستگاه ${s.origin} از بهترین روستری‌های ایران. رست تازه، ارسال سریع.`;
    } else if (s.roast) {
      title = `قهوه رست ${s.roast} | خرید آنلاین | رستا`;
      description = `خرید قهوه با رست ${s.roast} از روستری‌های منتخب رستا.`;
    } else if (s.grind) {
      title = `قهوه آسیاب ${s.grind} | خرید آنلاین | رستا`;
      description = `خرید قهوه آماده برای ${s.grind} با آسیاب دقیق و تازه.`;
    }

    const canonical = buildCanonical(s);
    return {
      meta: [
        { title },
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:url", content: canonical },
        { property: "og:type", content: "website" },
      ],
      links: [{ rel: "canonical", href: canonical }],
      scripts: [
        {
          type: "application/ld+json",
          children: JSON.stringify(
            breadcrumbJsonLd([
              { label: "خانه", to: "/" },
              { label: "محصولات", to: "/products" },
            ]),
          ),
        },
      ],
    };
  },
  component: ProductsPage,
});

function ProductsPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/products/" });

  const setParam = (key: keyof ProductsSearch, value: string) => {
    navigate({
      search: (prev) => ({ ...(prev as ProductsSearch), [key]: value }),
      replace: true,
    });
  };

  const clearAll = () =>
    navigate({ search: { origin: "", roast: "", grind: "", roastery: "" }, replace: true });

  const filtered = useMemo(
    () =>
      products.filter(
        (p) =>
          (!search.origin || p.origin === search.origin) &&
          (!search.roast || p.roastLevel === search.roast) &&
          (!search.roastery || p.roasterySlug === search.roastery),
      ),
    [search.origin, search.roast, search.roastery],
  );

  const h1 = search.origin
    ? `قهوه تک خاستگاه ${search.origin}`
    : search.roast
    ? `قهوه رست ${search.roast}`
    : search.grind
    ? `قهوه برای آسیاب ${search.grind}`
    : "همه محصولات";

  const Chip = ({
    active,
    onClick,
    children,
  }: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
  }) => (
    <button
      type="button"
      onClick={onClick}
      className={`whitespace-nowrap rounded-full border px-3 py-1 text-xs transition ${
        active
          ? "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]"
          : "border-[color:var(--mid)] bg-[color:var(--dark)] text-[color:var(--light)] hover:border-[color:var(--roast)]"
      }`}
    >
      {children}
    </button>
  );

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "محصولات" },
          ]}
        />
        <header>
          <h1 className="text-3xl font-bold text-[color:var(--steam)]">{h1}</h1>
          <p className="mt-2 text-sm text-[color:var(--light)]">
            {toFa(filtered.length)} محصول تازه‌رست از {toFa(roasteries.length)} روستری ایرانی
          </p>
        </header>

        <section
          aria-label="فیلترها"
          className="mt-6 space-y-3 rounded-xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4"
        >
          <div>
            <h2 className="mb-2 text-xs font-bold text-[color:var(--light)]">خاستگاه</h2>
            <div className="flex flex-wrap gap-1.5">
              <Chip active={!search.origin} onClick={() => setParam("origin", "")}>همه</Chip>
              {ORIGINS.map((o) => (
                <Chip key={o} active={search.origin === o} onClick={() => setParam("origin", o)}>
                  {o}
                </Chip>
              ))}
            </div>
          </div>
          <div>
            <h2 className="mb-2 text-xs font-bold text-[color:var(--light)]">سطح رست</h2>
            <div className="flex flex-wrap gap-1.5">
              <Chip active={!search.roast} onClick={() => setParam("roast", "")}>همه</Chip>
              {ROAST_LEVELS.map((l) => (
                <Chip key={l} active={search.roast === l} onClick={() => setParam("roast", l)}>
                  {l}
                </Chip>
              ))}
            </div>
          </div>
          <div>
            <h2 className="mb-2 text-xs font-bold text-[color:var(--light)]">آسیاب</h2>
            <div className="flex flex-wrap gap-1.5">
              <Chip active={!search.grind} onClick={() => setParam("grind", "")}>همه</Chip>
              {GRINDS.map((g: Grind) => (
                <Chip key={g} active={search.grind === g} onClick={() => setParam("grind", g)}>
                  {g}
                </Chip>
              ))}
            </div>
          </div>
          <div>
            <h2 className="mb-2 text-xs font-bold text-[color:var(--light)]">روستری</h2>
            <div className="flex flex-wrap gap-1.5">
              <Chip active={!search.roastery} onClick={() => setParam("roastery", "")}>همه</Chip>
              {roasteries.map((r) => (
                <Chip
                  key={r.slug}
                  active={search.roastery === r.slug}
                  onClick={() => setParam("roastery", r.slug)}
                >
                  {r.name}
                </Chip>
              ))}
            </div>
          </div>
        </section>

        <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {filtered.map((p) => (
            <ProductCard key={p.slug} product={p} />
          ))}
        </div>

        {filtered.length === 0 && (
          <div className="mt-12 text-center">
            <p className="text-sm text-[color:var(--light)]">محصولی با این فیلتر یافت نشد.</p>
            <button
              type="button"
              onClick={clearAll}
              className="mt-4 rounded-lg border border-[color:var(--roast)] px-4 py-2 text-sm text-[color:var(--roast)] hover:bg-[color:var(--roast)] hover:text-[color:var(--night)]"
            >
              پاک کردن فیلترها
            </button>
          </div>
        )}
      </main>
      <Footer />
    </>
  );
}
