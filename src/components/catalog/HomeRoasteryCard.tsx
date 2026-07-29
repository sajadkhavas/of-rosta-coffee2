import { Link } from "@tanstack/react-router";
import type { RoasterySummary } from "@/lib/api/contracts";
import { bestMediaUrl, mediaSrcSet } from "@/lib/catalog-format";
import { toFa } from "@/lib/persian";

export function HomeRoasteryCard({ roastery }: { roastery: RoasterySummary }) {
  const media = roastery.cover ?? roastery.logo;
  const cover = bestMediaUrl(media);
  return (
    <article className="card-dark card-dark-hover flex h-full flex-col overflow-hidden rounded-2xl">
      <div className="relative aspect-[16/9] overflow-hidden bg-[color:var(--dark)]">
        {cover ? (
          <img
            src={cover}
            srcSet={mediaSrcSet(media)}
            sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
            alt={roastery.cover?.alt || roastery.logo?.alt || roastery.name}
            loading="lazy"
            width={media?.width}
            height={media?.height}
            className="h-full w-full object-cover opacity-85"
          />
        ) : (
          <div className="grid h-full place-items-center font-display text-5xl font-bold text-[color:var(--roast)]">
            {roastery.name.slice(0, 1)}
          </div>
        )}
        <div
          aria-hidden
          className="absolute inset-0 bg-gradient-to-t from-[color:var(--night)] via-transparent to-transparent"
        />
      </div>
      <div className="flex flex-1 flex-col p-5">
        <p className="text-xs text-[color:var(--roast)]">روستری تأییدشده</p>
        <h3 className="mt-2 font-display text-xl font-bold text-[color:var(--steam)]">
          {roastery.name}
        </h3>
        <div className="mt-2 flex flex-wrap gap-2 text-xs text-[color:var(--light)]">
          {roastery.city ? <span>📍 {roastery.city}</span> : null}
          {roastery.rating ? (
            <span>
              ★ {toFa(roastery.rating.value.toFixed(1))} ({toFa(roastery.rating.count)})
            </span>
          ) : null}
        </div>
        {roastery.preparationTime ? (
          <p className="mt-4 text-xs text-[color:var(--muted-gold)]">
            آماده‌سازی {toFa(roastery.preparationTime.minHours)} تا{" "}
            {toFa(roastery.preparationTime.maxHours)} ساعت
          </p>
        ) : null}
        <Link
          to="/roasteries/$slug"
          params={{ slug: roastery.slug }}
          className="mt-5 inline-flex justify-center rounded-xl border border-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--roast)]"
        >
          مشاهده روستری
        </Link>
      </div>
    </article>
  );
}
