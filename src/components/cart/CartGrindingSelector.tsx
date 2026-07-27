import { useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  grindingProfilesQueryOptions,
  publicGrindingCapabilityQueryOptions,
} from "@/lib/api/grinding-capability";
import { formatIrr } from "@/lib/catalog-format";
import type { CartItem } from "@/lib/cart-context";

interface CartGrindingSelectorProps {
  item: CartItem;
  onChange: (profileId: string | null) => void;
}

export function CartGrindingSelector({ item, onChange }: CartGrindingSelectorProps) {
  const capabilityQuery = useQuery(publicGrindingCapabilityQueryOptions(item.roasterySlug));
  const profilesQuery = useQuery(grindingProfilesQueryOptions());
  const capability = capabilityQuery.data;
  const roasterySupported =
    capability?.isAvailable && capability.supportedWeights.includes(item.weightGrams);
  const hubCandidate = Boolean(
    capability &&
    capability.isActive &&
    capability.availability === "unavailable" &&
    profilesQuery.data?.length,
  );
  const selectableProfiles = roasterySupported
    ? capability.profiles
    : hubCandidate
      ? (profilesQuery.data ?? [])
      : [];
  const selectedProfile = selectableProfiles.find(
    (profile) => profile.id === item.grindingProfileId,
  );

  useEffect(() => {
    if (!capabilityQuery.isSuccess || !profilesQuery.isSuccess || !item.grindingProfileId) return;
    if (!selectedProfile) onChange(null);
  }, [
    capabilityQuery.isSuccess,
    item.grindingProfileId,
    onChange,
    profilesQuery.isSuccess,
    selectedProfile,
  ]);

  if (capabilityQuery.isPending || profilesQuery.isPending) {
    return <p className="mt-3 text-[11px] text-[color:var(--light)]">بررسی سرویس آسیاب…</p>;
  }

  if (capabilityQuery.isError || profilesQuery.isError) {
    return (
      <p className="mt-3 text-[11px] text-amber-200">
        وضعیت آسیاب دریافت نشد؛ آیتم به‌صورت دانه کامل باقی می‌ماند.
      </p>
    );
  }

  if (!roasterySupported && !hubCandidate) {
    return (
      <p className="mt-3 text-[11px] text-[color:var(--light)]">
        برای این وزن سرویس آسیاب فعال نیست؛ محصول همچنان دانه کامل است.
      </p>
    );
  }

  const feeLabel = roasterySupported
    ? capability!.isFree
      ? "آسیاب روستری رایگان"
      : `هزینه هر بسته ${formatIrr(capability!.feeAmount)}`
    : "مبلغ هاب و مسیر ارسال پس از انتخاب آدرس محاسبه می‌شود";
  const preparationLabel = roasterySupported
    ? `آماده‌سازی حدود ${capability!.preparationMinutes.toLocaleString("fa-IR")} دقیقه`
    : "فقط برای مناطق فعال تهران یا کرج";

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
        {selectableProfiles.map((profile) => (
          <option key={profile.id} value={profile.id}>
            {profile.publicName}
          </option>
        ))}
      </select>
      <p className="mt-2 text-[10px] leading-5 text-[color:var(--light)]">
        {roasterySupported ? "ارائه‌دهنده: روستری" : "ارائه‌دهنده احتمالی: هاب رستا"} · {feeLabel} ·{" "}
        {preparationLabel}
      </p>
      {hubCandidate ? (
        <p className="mt-1 text-[10px] leading-5 text-amber-200">
          Laravel پس از انتخاب آدرس، منطقه، ظرفیت، پروفایل، وزن، مبلغ و مسیر هاب را دوباره کنترل
          می‌کند.
        </p>
      ) : null}
      <p className="mt-1 text-[10px] leading-5 text-[color:var(--light)]">
        انتخاب آسیاب فقط یک خدمت سفارش است و SKU، وزن و موجودی محصول را تغییر نمی‌دهد.
      </p>
    </div>
  );
}
