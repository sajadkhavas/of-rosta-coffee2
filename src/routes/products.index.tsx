import { createFileRoute } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { ProductCard } from "@/components/ProductCard";
import { products, roasteries, type RoastLevel } from "@/data/seed";
import { toFa } from "@/lib/persian";

const ORIGINS = Array.from(new Set(products.map((p) => p.origin)));
const ROAST_LEVELS: RoastLevel[] = ["روشن", "متوسط", "تیره"];

export const Route = createFileRoute("/products/")({
  head: () => ({
    meta: [
      { title: "همه محصولات قهوه | رستا" },
      {
        name: "description",
        content:
          "لیست کامل قهوه‌های تازه‌رست از روستری‌های ایران در رستا. فیلتر بر اساس خاستگاه، سطح رست و روستری.",
      },
      { property: "og:title", content: "همه محصولات قهوه | رستا" },
      {
        property: "og:description",
        content: "قهوه تازه‌رست از روستری‌های ایران با فیلتر خاستگاه، سطح رست و روستری.",
      },
      { property: "og:url", content: "/products" },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: "/products" }],
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
  }),
  component: ProductsPage,
});

function ProductsPage() {
  const [origin, setOrigin] = useState<string | null>(null);
  const [level, setLevel] = useState<RoastLevel | null>(null);
  const [roasterySlug, setRoasterySlug] = useState<string | null>(null);

  const filtered = useMemo(
    () =>
      products.filter(
        (p) =>
          (!origin || p.origin === origin) &&
          (!level || p.roastLevel === level) &&
          (!roasterySlug || p.roasterySlug === roasterySlug),
      ),
    [origin, level, roasterySlug],
  );

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
      className={`rounded-full border px-3 py-1 text-xs transition ${
        active
          ? "border-[color:var(--rosta-primary)] bg-[color:var(--rosta-primary)] text-[color:var(--rosta-bg)]"
          : "border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] text-[color:var(--rosta-primary)] hover:border-[color:var(--rosta-accent)]"
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
          <h1 className="text-3xl font-bold">همه محصولات قهوه</h1>
          <p className="mt-2 text-sm text-[color:var(--rosta-secondary-text)]">
            {toFa(filtered.length)} محصول تازه‌رست از {toFa(roasteries.length)} روستری ایرانی
          </p>
        </header>

        <section aria-label="فیلترها" className="mt-6 space-y-3 rounded-xl border border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] p-4">
          <div>
            <h2 className="mb-2 text-xs font-bold text-[color:var(--rosta-secondary-text)]">خاستگاه</h2>
            <div className="flex flex-wrap gap-1.5">
              <Chip active={!origin} onClick={() => setOrigin(null)}>همه</Chip>
              {ORIGINS.map((o) => (
                <Chip key={o} active={origin === o} onClick={() => setOrigin(o)}>{o}</Chip>
              ))}
            </div>
          </div>
          <div>
            <h2 className="mb-2 text-xs font-bold text-[color:var(--rosta-secondary-text)]">سطح رست</h2>
            <div className="flex flex-wrap gap-1.5">
              <Chip active={!level} onClick={() => setLevel(null)}>همه</Chip>
              {ROAST_LEVELS.map((l) => (
                <Chip key={l} active={level === l} onClick={() => setLevel(l)}>{l}</Chip>
              ))}
            </div>
          </div>
          <div>
            <h2 className="mb-2 text-xs font-bold text-[color:var(--rosta-secondary-text)]">روستری</h2>
            <div className="flex flex-wrap gap-1.5">
              <Chip active={!roasterySlug} onClick={() => setRoasterySlug(null)}>همه</Chip>
              {roasteries.map((r) => (
                <Chip key={r.slug} active={roasterySlug === r.slug} onClick={() => setRoasterySlug(r.slug)}>
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
          <p className="mt-12 text-center text-sm text-[color:var(--rosta-secondary-text)]">
            محصولی با این فیلترها پیدا نشد.
          </p>
        )}
      </main>
      <Footer />
    </>
  );
}
