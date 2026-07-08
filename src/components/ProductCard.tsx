import { Link } from "@tanstack/react-router";
import { useState } from "react";
import type { Product, Weight } from "@/data/seed";
import { getRoastery } from "@/data/seed";
import { formatToman, toFa } from "@/lib/persian";
import { RoastDateBadge } from "./RoastDateBadge";
import { RoastLevelBadge } from "./RoastLevelBadge";
import { WeightSelector } from "./WeightSelector";

export function ProductCard({ product }: { product: Product }) {
  const [weight, setWeight] = useState<Weight>(250);
  const roastery = getRoastery(product.roasterySlug);

  return (
    <article className="flex h-full flex-col rounded-xl border border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] p-4 transition hover:-translate-y-0.5 hover:shadow-lg">
      <div className="flex items-center justify-between text-xs">
        {roastery && (
          <Link
            to="/roasteries/$slug"
            params={{ slug: roastery.slug }}
            className="text-[color:var(--rosta-accent)] hover:underline"
          >
            {roastery.name}
          </Link>
        )}
        <RoastDateBadge daysAgo={product.roastDaysAgo} />
      </div>

      <h3 className="mt-2 text-base font-bold text-[color:var(--rosta-primary)]">
        <Link to="/products/$slug" params={{ slug: product.slug }} className="hover:text-[color:var(--rosta-accent)]">
          {product.name}
        </Link>
      </h3>

      <ul className="mt-2 flex flex-wrap gap-1.5">
        <li className="inline-flex items-center gap-1 rounded-full bg-[color:var(--rosta-bg)] px-2 py-0.5 text-[11px] text-[color:var(--rosta-secondary-text)]">
          <span aria-hidden>{product.originFlag}</span>
          {product.origin}
        </li>
        <li><RoastLevelBadge level={product.roastLevel} /></li>
        <li className="rounded-full bg-[color:var(--rosta-bg)] px-2 py-0.5 text-[11px] text-[color:var(--rosta-secondary-text)]">
          {toFa(product.arabicaPct)}٪ عربیکا
        </li>
      </ul>

      <div className="mt-3">
        <WeightSelector value={weight} onChange={setWeight} size="sm" />
      </div>

      <div className="mt-auto pt-3">
        <div className="text-lg font-bold text-[color:var(--rosta-primary)]">
          {formatToman(product.prices[weight])}
        </div>
        <button
          type="button"
          className="mt-2 w-full rounded-lg bg-[color:var(--rosta-primary)] py-2 text-sm font-medium text-[color:var(--rosta-bg)] transition hover:bg-[color:var(--rosta-accent)]"
        >
          افزودن به سبد
        </button>
      </div>
    </article>
  );
}
