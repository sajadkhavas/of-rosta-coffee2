import type { ProductSummary } from "@/lib/api/contracts";

export type BrewMethod =
  | "اسپرسوساز خانگی"
  | "موکاپات"
  | "فرنچ‌پرس"
  | "V60 یا دریپ"
  | "کلدبرو"
  | "هنوز مشخص نیست";
export type RoastPref = "روشن" | "متوسط" | "تیره" | "پیشنهاد بده";
export type Adventure = "safe" | "balanced" | "adventurous";
export type Experience = "beginner" | "some" | "pro";
export interface TasteProfile {
  brewMethod: BrewMethod | null;
  roast: RoastPref | null;
  adventure: Adventure | null;
  flavors: string[];
  decaf: boolean | null;
  experience: Experience | null;
}

export const EMPTY_PROFILE: TasteProfile = {
  brewMethod: null,
  roast: null,
  adventure: null,
  flavors: [],
  decaf: null,
  experience: null,
};

const FLAVOR_TO_NOTES: Record<string, string[]> = {
  "میوه‌ای و ترش": ["توت", "لیمو", "پرتقال", "هلو", "شراب", "بری"],
  "شکلاتی و کارامل": ["شکلات", "کارامل", "کاکائو"],
  "گلی و عطری": ["یاس", "گل", "عطری"],
  "آجیلی و خاکی": ["بادام", "فندق", "آجیلی", "ادویه"],
  مرکباتی: ["لیمو", "پرتقال", "گریپ", "برگاموت"],
  "شیرین و عسلی": ["عسل", "کارامل", "شیرین"],
};
const roastMap: Record<Exclude<RoastPref, "پیشنهاد بده">, ProductSummary["roastLevel"]> = {
  روشن: "light",
  متوسط: "medium",
  تیره: "dark",
};

export function matchProducts(
  profile: TasteProfile,
  products: ProductSummary[],
  take = 4,
): ProductSummary[] {
  return products
    .filter((product) => product.status === "published")
    .filter((product) => product.variants.some((variant) => variant.isAvailable))
    .map((product) => {
      let score = 0;
      if (
        profile.roast &&
        profile.roast !== "پیشنهاد بده" &&
        product.roastLevel === roastMap[profile.roast]
      )
        score += 4;
      if (
        ["اسپرسوساز خانگی", "موکاپات"].includes(profile.brewMethod || "") &&
        ["medium", "dark"].includes(product.roastLevel)
      )
        score += 2;
      if (
        ["V60 یا دریپ", "کلدبرو"].includes(profile.brewMethod || "") &&
        ["light", "medium"].includes(product.roastLevel)
      )
        score += 2;
      if (
        profile.adventure === "adventurous" &&
        ["natural", "honey", "other"].includes(product.processingMethod)
      )
        score += 2;
      if (profile.adventure === "safe" && product.processingMethod === "washed") score += 2;
      for (const flavor of profile.flavors) {
        const notes = FLAVOR_TO_NOTES[flavor] ?? [];
        score +=
          product.tastingNotes.filter((note) =>
            notes.some((target) => note.includes(target) || target.includes(note)),
          ).length * 2;
      }
      if (profile.experience === "beginner" && product.roastLevel === "medium") score += 1;
      if (profile.experience === "pro" && product.processingMethod !== "washed") score += 1;
      return { product, score };
    })
    .sort(
      (a, b) =>
        b.score - a.score ||
        b.product.variants.filter((item) => item.isAvailable).length -
          a.product.variants.filter((item) => item.isAvailable).length,
    )
    .slice(0, Math.max(1, Math.min(12, take)))
    .map((item) => item.product);
}

export const STORAGE_KEY = "rosta:taste-profile";
export function saveProfile(profile: TasteProfile) {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
  } catch {
    /* storage is optional */
  }
}
export function loadProfile(): TasteProfile | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as TasteProfile) : null;
  } catch {
    return null;
  }
}
