import { Link } from "@tanstack/react-router";
import { memo, useState } from "react";
import type { Product, Weight } from "@/data/seed";
import { getRoastery } from "@/data/seed";
import { formatToman, toFa } from "@/lib/persian";
import { RoastLevelBadge } from "./RoastLevelBadge";
import { WeightSelector } from "./WeightSelector";
import { roastDateLabel } from "@/lib/persian";
import { productImage } from "@/lib/product-images";
import { useCart } from "@/lib/cart-context";

function ProductCardImpl({ product }: { product: Product }) {

  const [weight, setWeight] = useState<Weight>(250);
  const roastery = getRoastery(product.roasterySlug);
  const { addItem } = useCart();
  const [added, setAdded] = useState(false);
  const handleAdd = () => {
    addItem(product.slug, weight, "دانه", 1);
    setAdded(true);
    window.setTimeout(() => setAdded(false), 1500);
  };

  return (
    <article className="card-dark card-dark-hover group flex h-full flex-col overflow-hidden rounded-2xl">
      {/* Image area */}
      <div className="relative aspect-[4/3] overflow-hidden bg-[color:var(--dark)]">
        <img
          src={productImage(product.slug, 500)}
          alt={`${product.name} از ${roastery?.name ?? "روستری"} — قهوه ${product.origin}`}
          loading="lazy"
          width={500}
          height={375}
          className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
        />
        <div
          aria-hidden
          className="absolute inset-0 bg-gradient-to-t from-[color:var(--night)]/70 via-transparent to-transparent"
        />
        <span
          className="absolute bottom-3 left-3 inline-flex items-center gap-1 rounded-full bg-[color:var(--roast)] px-3 py-1 text-[11px] font-bold text-[color:var(--night)] shadow-lg"
          title="تاریخ برشته‌کاری"
        >
          <span aria-hidden>🔥</span>
          {roastDateLabel(product.roastDaysAgo)}
        </span>
      </div>


      <div className="flex flex-1 flex-col p-4">
        <div className="flex items-center justify-between text-xs">
          {roastery && (
            <Link
              to="/roasteries/$slug"
              params={{ slug: roastery.slug }}
              className="eyebrow hover:text-[color:var(--steam)]"
            >
              {roastery.name}
            </Link>
          )}
        </div>

        <h3 className="mt-2 font-display text-lg font-bold text-[color:var(--steam)]">
          <Link
            to="/products/$slug"
            params={{ slug: product.slug }}
            className="transition hover:text-[color:var(--roast)]"
          >
            {product.name}
          </Link>
        </h3>

        <ul className="mt-2 flex flex-wrap gap-1.5">
          <li className="inline-flex items-center gap-1 rounded-full border border-[color:var(--mid)] bg-[color:var(--night)] px-2 py-0.5 text-[11px] text-[color:var(--light)]">
            <span aria-hidden>{product.originFlag}</span>
            {product.origin}
          </li>
          <li><RoastLevelBadge level={product.roastLevel} /></li>
          <li className="rounded-full border border-[color:var(--mid)] bg-[color:var(--night)] px-2 py-0.5 text-[11px] text-[color:var(--light)]">
            {toFa(product.arabicaPct)}٪ عربیکا
          </li>
        </ul>

        <div className="mt-3">
          <WeightSelector value={weight} onChange={setWeight} size="sm" />
        </div>

        <div className="mt-auto pt-4">
          <div className="font-mono-num text-xl font-bold text-[color:var(--roast)]">
            {formatToman(product.prices[weight])}
          </div>
          <button
            type="button"
            onClick={handleAdd}
            className="mt-3 w-full rounded-lg border border-[color:var(--roast)] bg-[color:var(--roast)] py-2 text-sm font-bold text-[color:var(--night)] transition hover:bg-transparent hover:text-[color:var(--roast)]"
          >
            {added ? "افزوده شد ✓" : "افزودن به سبد"}
          </button>
        </div>
      </div>
    </article>
  );
}


export const ProductCard = memo(ProductCardImpl);
