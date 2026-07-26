import { useQuery } from "@tanstack/react-query";
import { Coffee, Gauge, Timer } from "lucide-react";
import { publicGrindingCapabilityQueryOptions } from "@/lib/api/grinding-capability";
import { toFa } from "@/lib/persian";

export function RoasteryGrindingCapability({ roasterySlug }: { roasterySlug: string }) {
  const query = useQuery(publicGrindingCapabilityQueryOptions(roasterySlug));

  if (query.isPending) {
    return (
      <aside className="animate-pulse rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
        <div className="h-5 w-32 rounded bg-[color:var(--mid)]" />
        <div className="mt-4 h-16 rounded bg-[color:var(--dark)]" />
      </aside>
    );
  }

  if (query.isError) {
    return (
      <aside className="rounded-2xl border border-amber-400/35 bg-[color:var(--night)] p-4 text-xs leading-7 text-[color:var(--light)]">
        وضعیت سرویس آسیاب این روستری فعلاً قابل دریافت نیست.
      </aside>
    );
  }

  const capability = query.data;
  if (!capability?.isAvailable) {
    return (
      <aside className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
        <div className="flex items-center gap-2">
          <Coffee size={17} className="text-[color:var(--roast)]" />
          <h2 className="text-sm font-bold">سرویس آسیاب</h2>
        </div>
        <p className="mt-3 text-xs leading-7 text-[color:var(--light)]">
          این روستری در حال حاضر سرویس آسیاب فعال ارائه نمی‌کند؛ محصولات همچنان به‌صورت دانه کامل
          عرضه می‌شوند.
        </p>
      </aside>
    );
  }

  return (
    <aside className="rounded-2xl border border-[color:var(--roast)]/45 bg-[color:var(--night)] p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-2">
          <span className="grid size-9 place-items-center rounded-xl bg-[color:var(--roast)] text-[color:var(--night)]">
            <Coffee size={18} />
          </span>
          <div>
            <h2 className="text-sm font-bold">سرویس آسیاب روستری</h2>
            <p className="mt-1 text-[11px] text-[color:var(--light)]">{capability.label}</p>
          </div>
        </div>
        <span className="rounded-full border border-[color:var(--roast)]/40 px-3 py-1 text-[11px] font-bold text-[color:var(--roast)]">
          {capability.isFree ? "رایگان" : formatIrr(capability.feeAmount)}
        </span>
      </div>

      <div className="mt-4 grid gap-2 text-xs text-[color:var(--light)]">
        <p className="flex items-center gap-2">
          <Timer size={14} />
          زمان افزوده آماده‌سازی: {toFa(capability.preparationMinutes)} دقیقه
        </p>
        {capability.capacityPerDay ? (
          <p className="flex items-center gap-2">
            <Gauge size={14} />
            ظرفیت اعلام‌شده: {toFa(capability.capacityPerDay)} بسته در روز
          </p>
        ) : null}
        <p>
          وزن‌های پشتیبانی‌شده: {capability.supportedWeights.map((value) => `${toFa(value)} گرم`).join("، ")}
        </p>
      </div>

      <div className="mt-4 border-t border-[color:var(--mid)] pt-4">
        <p className="text-[11px] font-bold text-[color:var(--roast)]">پروفایل‌های قابل ارائه</p>
        <div className="mt-2 flex flex-wrap gap-2">
          {capability.profiles.map((profile) => (
            <span
              key={profile.id}
              className="rounded-full border border-[color:var(--mid)] bg-[color:var(--dark)] px-3 py-1.5 text-[11px] text-[color:var(--steam)]"
            >
              {profile.publicName}
            </span>
          ))}
        </div>
      </div>

      <p className="mt-4 text-[10px] leading-6 text-[color:var(--light)]">
        آسیاب یک سرویس سفارش است و نوع محصول یا موجودی را تغییر نمی‌دهد؛ مبنای فروش همچنان دانه کامل
        است.
      </p>
    </aside>
  );
}

function formatIrr(value: number): string {
  return `${new Intl.NumberFormat("fa-IR").format(value)} ریال`;
}
