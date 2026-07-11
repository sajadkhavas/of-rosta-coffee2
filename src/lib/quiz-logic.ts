import { products, type Product } from "@/data/seed";

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
  "میوه‌ای و ترش": ["توت", "توت‌فرنگی", "توت سیاه", "لیمو", "پرتقال", "گریپ‌فروت", "هلو", "شراب"],
  "شکلاتی و کارامل": ["شکلات", "شکلات تلخ", "شکلات شیری", "کارامل", "کاکائو"],
  "گلی و عطری": ["یاس", "گل"],
  "آجیلی و خاکی": ["بادام", "فندق", "بادام سوخته", "تنباکو", "ادویه"],
  "مرکباتی": ["لیمو", "پرتقال", "پرتقال خونی", "گریپ‌فروت"],
  "شیرین و عسلی": ["عسل", "کارامل"],
};

export interface ScoredProduct {
  product: Product;
  score: number;
}

export function matchProducts(profile: TasteProfile, take = 4): Product[] {
  const scored: ScoredProduct[] = products.map((product) => {
    let score = 0;

    // Roast preference
    if (profile.roast && profile.roast !== "پیشنهاد بده") {
      if (product.roastLevel === profile.roast) score += 3;
    }

    // Brew method → roast bias
    if (profile.brewMethod === "اسپرسوساز خانگی" || profile.brewMethod === "موکاپات") {
      if (product.roastLevel === "تیره" || product.roastLevel === "متوسط") score += 2;
    }
    if (profile.brewMethod === "V60 یا دریپ" || profile.brewMethod === "کلدبرو") {
      if (product.roastLevel === "روشن" || product.roastLevel === "متوسط") score += 2;
    }

    // Adventure
    if (profile.adventure === "adventurous") {
      if (product.processing === "طبیعی" || product.origin === "اتیوپی" || product.origin === "کنیا") score += 2;
    }
    if (profile.adventure === "safe") {
      if (product.processing === "شسته" && (product.origin === "برزیل" || product.origin === "کلمبیا")) score += 2;
    }

    // Flavor notes
    for (const flavor of profile.flavors) {
      const notes = FLAVOR_TO_NOTES[flavor] ?? [];
      const overlap = product.tastingNotes.filter((n) => notes.some((x) => n.includes(x) || x.includes(n)));
      score += overlap.length * 2;
    }

    // Experience level tweaks
    if (profile.experience === "beginner") {
      if (product.roastLevel === "متوسط" || product.roastLevel === "تیره") score += 1;
    }
    if (profile.experience === "pro") {
      if (product.processing !== "شسته" || product.origin === "اتیوپی" || product.origin === "کنیا") score += 1;
    }

    return { product, score };
  });

  return scored
    .sort((a, b) => b.score - a.score)
    .slice(0, take)
    .map((s) => s.product);
}

export const STORAGE_KEY = "rosta:taste-profile";

export function saveProfile(profile: TasteProfile) {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
  } catch {
    // ignore
  }
}

export function loadProfile(): TasteProfile | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw) as TasteProfile;
  } catch {
    return null;
  }
}