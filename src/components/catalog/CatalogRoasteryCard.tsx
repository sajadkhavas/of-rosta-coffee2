import { Link } from "@tanstack/react-router";
import type { RoasterySummary } from "@/lib/api/contracts";
import { bestMediaUrl } from "@/lib/catalog-format";

export function CatalogRoasteryCard({ roastery }: { roastery: RoasterySummary }) {
  const cover = bestMediaUrl(roastery.cover);
  const logo = bestMediaUrl(roastery.logo);
  return (
    <article className="group overflow-hidden rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] transition hover:-translate-y-1 hover:border-[color:var(--roast)]">
      <Link to="/roasteries/$slug" params={{ slug: roastery.slug }} className="block">
        <div className="relative aspect-[16/9] overflow-hidden bg-[color:var(--mid)]">
          {cover ? (
            <img src={cover} alt={`کاور ${roastery.name}`} loading="lazy" className="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
          ) : (
            <div className="grid h-full place-items-center text-sm text-[color:var(--light)]">تصویر روستری</div>
          )}
          {logo ? (
            <img src={logo} alt={`لوگوی ${roastery.name}`} loading="lazy" className="absolute -bottom-7 end-5 size-16 rounded-2xl border-4 border-[color:var(--dark)] bg-white object-cover" />
          ) : null}
        </div>
        <div className="p-5 pt-6">
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-bold text-[color:var(--steam)]">{roastery.name}</h2>
            {roastery.isVerified ? <span title="روستری تأییدشده" className="text-[color:var(--roast)]">✓</span> : null}
          </div>
          <p className="mt-2 text-sm text-[color:var(--light)]">
            {roastery.city || "ایران"}
            {roastery.rating ? ` · امتیاز ${roastery.rating.value.toLocaleString("fa-IR")}` : ""}
          </p>
          {roastery.preparationTime ? (
            <p className="mt-3 text-xs text-[color:var(--roast)]">
              آماده‌سازی {roastery.preparationTime.minHours.toLocaleString("fa-IR")} تا {roastery.preparationTime.maxHours.toLocaleString("fa-IR")} ساعت
            </p>
          ) : null}
        </div>
      </Link>
    </article>
  );
}
