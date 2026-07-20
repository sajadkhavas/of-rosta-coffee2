import { Link } from "@tanstack/react-router";
import { memo } from "react";
import type { Product } from "@/data/seed";
import { getRoastery } from "@/data/seed";
import { formatToman, toFa, roastDateLabel } from "@/lib/persian";
import { RoastLevelBadge } from "./RoastLevelBadge";
import { productImage } from "@/lib/product-images";

function ProductCardImpl({ product }: { product: Product }) {
  const roastery = getRoastery(product.roasterySlug);
  const minimum = Math.min(...Object.values(product.prices));

  return (
    <article className="card-dark card-dark-hover group flex h-full flex-col overflow-hidden rounded-2xl">
      <Link
        to="/products/$slug"
        params={{ slug: product.slug }}
        className="flex h-full flex-col focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--roast)]"
      >
        <div className="relative aspect-[4/3] overflow-hidden bg-[color:var(--dark)]">
          <img
            src={productImage(product.slug, 500)}
            alt={`${product.name} از ${roastery?.name ?? "روستری"} — قهوه ${product.origin}`}
            loading="lazy"
            width={500}
            height={375}
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
          />
          <div aria-hidden className="absolute inset-0 bg-gradient-to-t from-[color:var(--night)]/70 via-transparent to-transparent" />
          <span className="absolute bottom-3 left-3 inline-flex items-center gap-1 rounded-full bg-[color:var(--roast)] px-3 py-1 text-[11px] font-bold text-[color:var(--night)] shadow-lg" title="تاریخ برشته‌کاری">
            <span aria-hidden>🔥</span>
            {roastDateLabel(product.roastDaysAgo)}
          </span>
        </div>

        <div className="flex flex-1 flex-col p-4">
          <p className="eyebrow">{roastery?.name ?? "روستری"}</p>
          <h3 className="mt-2 font-display text-lg font-bold text-[color:var(--steam)] transition group-hover:text-[color:var(--roast)]">
            {product.name}
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
          <div className="mt-auto flex items-end justify-between gap-3 pt-5">
            <div>
              <p className="text-[10px] text-[color:var(--light)]">شروع قیمت</p>
              <p className="font-mono-num text-lg font-bold text-[color:var(--roast)]">{formatToman(minimum)}</p>
            </div>
            <span className="rounded-lg border border-[color:var(--roast)] px-3 py-2 text-xs font-bold text-[color:var(--roast)]">
              انتخاب وزن
            </span>
          </div>
        </div>
      </Link>
    </article>
  );
}

export const ProductCard = memo(ProductCardImpl);
