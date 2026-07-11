// Maps between english URL slugs and persian origin names used in seed data.
export const ORIGIN_SLUG_TO_FA: Record<string, string> = {
  ethiopia: "اتیوپی",
  colombia: "کلمبیا",
  brazil: "برزیل",
  kenya: "کنیا",
  guatemala: "گواتمالا",
  indonesia: "اندونزی",
};

export const ORIGIN_FA_TO_SLUG: Record<string, string> = Object.fromEntries(
  Object.entries(ORIGIN_SLUG_TO_FA).map(([s, fa]) => [fa, s]),
);

export const HIGH_VALUE_ORIGINS = ["ethiopia", "colombia", "brazil", "kenya"] as const;
export const HIGH_VALUE_ROASTS = ["روشن", "متوسط", "تیره"] as const;

export function originFaFromSlug(slug?: string | null): string | null {
  if (!slug) return null;
  return ORIGIN_SLUG_TO_FA[slug] ?? null;
}