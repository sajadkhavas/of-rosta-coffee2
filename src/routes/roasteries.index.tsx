import { createFileRoute } from "@tanstack/react-router";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb, breadcrumbJsonLd } from "@/components/Breadcrumb";
import { RoasteryCard } from "@/components/RoasteryCard";
import { roasteries } from "@/data/seed";

export const Route = createFileRoute("/roasteries/")({
  head: () => ({
    meta: [
      { title: "روستری‌های ایران | خرید مستقیم قهوه تازه | رستا" },
      {
        name: "description",
        content:
          "لیست روستری‌های اسپشیالیتی ایران در رستا. قهوه تازه‌رست را مستقیم از روستری خریداری کنید.",
      },
      { property: "og:title", content: "روستری‌های ایران | رستا" },
      { property: "og:description", content: "خرید مستقیم قهوه تازه از روستری‌های اسپشیالیتی ایران." },
      { property: "og:url", content: "/roasteries" },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: "/roasteries" }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify(
          breadcrumbJsonLd([
            { label: "خانه", to: "/" },
            { label: "روستری‌ها", to: "/roasteries" },
          ]),
        ),
      },
    ],
  }),
  component: RoasteriesIndex,
});

function RoasteriesIndex() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "روستری‌ها" },
          ]}
        />
        <header>
          <h1 className="text-3xl font-bold">روستری‌های ایران</h1>
          <p className="mt-2 text-sm text-[color:var(--rosta-secondary-text)]">
            بهترین روستری‌های اسپشیالیتی ایران را کشف کنید و مستقیم از آن‌ها قهوه تازه بخرید.
          </p>
        </header>
        <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {roasteries.map((r) => (
            <RoasteryCard key={r.slug} roastery={r} />
          ))}
        </div>
      </main>
      <Footer />
    </>
  );
}
