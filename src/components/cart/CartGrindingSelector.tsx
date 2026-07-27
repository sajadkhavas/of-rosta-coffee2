import { useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { publicGrindingCapabilityQueryOptions } from "@/lib/api/grinding-capability";
import { formatIrr } from "@/lib/catalog-format";
import type { CartItem } from "@/lib/cart-context";

interface CartGrindingSelectorProps {
  item: CartItem;
  onChange: (profileId: string | null) => void;
}

export function CartGrindingSelector({ item, onChange }: CartGrindingSelectorProps) {
  const capabilityQuery = useQuery(publicGrindingCapabilityQueryOptions(item.roasterySlug));
  const capability = capabilityQuery.data;
  const supported =
    capability?.isAvailable && capability.supportedWeights.includes(item.weightGrams);
  const selectedProfile = supported
    ? capability.profiles.find((profile) => profile.id === item.grindingProfileId)
    : undefined;

  useEffect(() => {
    if (!capabilityQuery.isSuccess || !item.grindingProfileId) return;
    if (!supported || !selectedProfile) onChange(null);
  }, [capabilityQuery.isSuccess, item.grindingProfileId, onChange, selectedProfile, supported]);

  if (capabilityQuery.isPending) {
    return (
      <p className="mt-3 text-[11px] text-[color:var(--light)]">
        بررسی سرویس آسیاب روستری…
      </p>
    );
  }

  if (capabilityQuery.isError) {
    return (
      <p className="mt-3 text-[11px] text-amber-200">
        وضعیت آسیاب دریافت نشد؛ آیتم به‌صورت دانه کامل باقی می‌ماند.
      </p>
    );
  }

  if (!supported || !capability) {
    return (
      <p className="mt-3 text-[11px] text-[color:var(--light)]">
        این روستری برای وزن انتخاب‌شده سرویس آسیاب فعال ندارد؛ محصول همچنان دانه کامل است.
      </p>
    );
  }

  const feeLabel = capability.isFree
    ? "آسیاب روستری رایگان"
    : `هزینه هر بسته ${formatIrr(capability.feeAmount)}`;
  const preparationLabel = capability.preparationMinutes.toLocaleString("fa-IR");

  return (
    <div className="mt-3 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3">
      <label className="block text-xs font-bold" htmlFor={`grinding-${item.variantId}`}>
        سرویس جداگانه آسیاب
      </label>
      <select
        id={`grinding-${item.variantId}`}
        value={selectedProfile?.id ?? ""}
        onChange={(event) => onChange(event.target.value || null)}
        className="mt-2 min-h-11 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--dark)] px-3 text-sm"
      >
        <option value="">بدون آسیاب — تحویل دانه کامل</option>
        {capability.profiles.map((profile) => (
          <option key={profile.id} value={profile.id}>
            {profile.publicName}
          </option>
        ))}
      </select>
      <p className="mt-2 text-[10px] leading-5 text-[color:var(--light)]">
        {feeLabel} · آماده‌سازی حدود {preparationLabel} دقیقه
      </p>
      <p className="mt-1 text-[10px] leading-5 text-[color:var(--light)]">
        انتخاب آسیاب فقط یک خدمت سفارش است و SKU، وزن و موجودی محصول را تغییر نمی‌دهد.
      </p>
    </div>
  );
}
