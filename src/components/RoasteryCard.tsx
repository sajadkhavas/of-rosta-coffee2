import { Link } from "@tanstack/react-router";
import type { Roastery } from "@/data/seed";
import { productsByRoastery } from "@/data/seed";
import { toFa } from "@/lib/persian";

export function RoasteryCard({ roastery }: { roastery: Roastery }) {
  const count = productsByRoastery(roastery.slug).length;
  return (
    <article className="card-dark card-dark-hover flex h-full flex-col rounded-2xl p-6">
      <div className="flex items-start gap-4">
        <div
          aria-hidden
          className="grid h-14 w-14 shrink-0 place-items-center rounded-full font-display text-lg font-bold text-[color:var(--night)] shadow-[0_0_24px_-6px_rgba(200,150,90,0.5)]"
          style={{
            background: `linear-gradient(135deg, ${roastery.color}, var(--roast))`,
          }}
        >
          {roastery.initials}
        </div>
        <div className="min-w-0 flex-1">
          <h3 className="truncate font-display text-lg font-bold text-[color:var(--steam)]">
            <Link
              to="/roasteries/$slug"
              params={{ slug: roastery.slug }}
              className="transition hover:text-[color:var(--roast)]"
            >
              {roastery.name}
            </Link>
          </h3>
          <div className="mt-1 flex items-center gap-2 text-xs text-[color:var(--light)]">
            <span className="rounded-full border border-[color:var(--mid)] bg-[color:var(--night)] px-2 py-0.5">
              📍 {roastery.city}
            </span>
            <span className="font-mono-num text-[color:var(--roast)]">
              ★ {toFa(roastery.rating.toFixed(1))}
            </span>
          </div>
        </div>
      </div>

      <ul className="mt-4 flex flex-wrap gap-1.5">
        {roastery.specialty.map((s) => (
          <li
            key={s}
            className="rounded-full border border-[color:var(--mid)] bg-[color:var(--night)] px-2.5 py-0.5 text-[11px] text-[color:var(--light)]"
          >
            {s}
          </li>
        ))}
      </ul>

      <p className="mt-4 flex-1 text-sm leading-6 text-[color:var(--light)]">
        {roastery.description}
      </p>

      <div className="mt-5 flex items-center justify-between border-t border-[color:var(--mid)] pt-4">
        <span className="font-mono-num text-xs text-[color:var(--muted-gold)]">
          {toFa(count)} محصول
        </span>
        <Link
          to="/roasteries/$slug"
          params={{ slug: roastery.slug }}
          className="rounded-lg border border-[color:var(--roast)] px-3 py-1.5 text-xs font-bold text-[color:var(--roast)] transition hover:bg-[color:var(--roast)] hover:text-[color:var(--night)]"
        >
          مشاهده محصولات
        </Link>
      </div>
    </article>
  );
}
