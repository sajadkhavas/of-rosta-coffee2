import { Link } from "@tanstack/react-router";
import type { Roastery } from "@/data/seed";
import { productsByRoastery } from "@/data/seed";
import { toFa } from "@/lib/persian";

export function RoasteryCard({ roastery }: { roastery: Roastery }) {
  const count = productsByRoastery(roastery.slug).length;
  return (
    <article className="flex h-full flex-col rounded-xl border border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
      <div className="flex items-start gap-3">
        <div
          aria-hidden
          className="grid h-14 w-14 shrink-0 place-items-center rounded-full text-lg font-bold text-white"
          style={{ backgroundColor: roastery.color }}
        >
          {roastery.initials}
        </div>
        <div className="min-w-0 flex-1">
          <h3 className="truncate text-base font-bold text-[color:var(--rosta-primary)]">
            <Link to="/roasteries/$slug" params={{ slug: roastery.slug }} className="hover:text-[color:var(--rosta-accent)]">
              {roastery.name}
            </Link>
          </h3>
          <div className="mt-1 flex items-center gap-2 text-xs text-[color:var(--rosta-secondary-text)]">
            <span className="rounded-full bg-[color:var(--rosta-bg)] px-2 py-0.5">📍 {roastery.city}</span>
            <span className="text-[color:var(--rosta-accent)]">★ {toFa(roastery.rating.toFixed(1))}</span>
          </div>
        </div>
      </div>

      <ul className="mt-3 flex flex-wrap gap-1.5">
        {roastery.specialty.map((s) => (
          <li key={s} className="rounded-full bg-[color:var(--rosta-bg)] px-2 py-0.5 text-[11px] text-[color:var(--rosta-secondary-text)]">
            {s}
          </li>
        ))}
      </ul>

      <p className="mt-3 flex-1 text-sm text-[color:var(--rosta-secondary-text)]">
        {roastery.description}
      </p>

      <div className="mt-4 flex items-center justify-between">
        <span className="text-xs text-[color:var(--rosta-secondary-text)]">
          {toFa(count)} محصول
        </span>
        <Link
          to="/roasteries/$slug"
          params={{ slug: roastery.slug }}
          className="rounded-lg bg-[color:var(--rosta-primary)] px-3 py-1.5 text-xs font-medium text-[color:var(--rosta-bg)] transition hover:bg-[color:var(--rosta-accent)]"
        >
          مشاهده محصولات
        </Link>
      </div>
    </article>
  );
}
