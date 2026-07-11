export type RoastLevel = "روشن" | "متوسط" | "تیره";
export type Processing = "طبیعی" | "شسته" | "عسل";
export type Weight = 50 | 100 | 250 | 500 | 1000;

export const WEIGHTS: Weight[] = [50, 100, 250, 500, 1000];

export interface Roastery {
  slug: string;
  name: string;
  city: string;
  rating: number;
  specialty: string[];
  description: string;
  initials: string;
  color: string;
}

export interface Product {
  slug: string;
  name: string;
  roasterySlug: string;
  origin: string;
  originFlag: string;
  roastLevel: RoastLevel;
  arabicaPct: number;
  processing: Processing;
  tastingNotes: string[];
  roastDaysAgo: number;
  prices: Record<Weight, number>;
  description: string;
}

export const roasteries: Roastery[] = [
  {
    slug: "emkan",
    name: "روستری امکان",
    city: "تهران",
    rating: 4.9,
    specialty: ["اسپشیالیتی", "تک‌خاستگاه"],
    description: "برشته‌کاری پس از سفارش، ارسال ۲۴ ساعته",
    initials: "ام",
    color: "#3D1A00",
  },
  {
    slug: "moa",
    name: "قهوه موآ",
    city: "تهران",
    rating: 5.0,
    specialty: ["اسپشیالیتی", "فیلتر"],
    description: "قهوه‌های فیلتر با پروفایل رست روشن و اسیدیته درخشان.",
    initials: "مو",
    color: "#C07A00",
  },
  {
    slug: "skaro",
    name: "اسکارو",
    city: "تهران",
    rating: 4.8,
    specialty: ["میکسولوژی", "ترکیب اختصاصی"],
    description: "ترکیب‌های اختصاصی برای اسپرسو و شیرقهوه.",
    initials: "اس",
    color: "#6B4226",
  },
  {
    slug: "houfer",
    name: "هوفر کافی",
    city: "تهران",
    rating: 4.7,
    specialty: ["اسپشیالیتی", "عربیکا"],
    description: "قهوه‌های ۱۰۰٪ عربیکا از خاستگاه‌های منتخب.",
    initials: "هو",
    color: "#8B3A00",
  },
  {
    slug: "luya",
    name: "قهوه لویا",
    city: "تهران/اصفهان",
    rating: 4.8,
    specialty: ["تک‌خاستگاه", "عمده‌فروشی"],
    description: "قهوه تک‌خاستگاه با قابلیت سفارش عمده برای کافه‌ها.",
    initials: "لو",
    color: "#5C2C0C",
  },
];

const p = (
  base50: number,
  base100: number,
  base250: number,
  base500: number,
  base1000: number,
): Record<Weight, number> => ({
  50: base50,
  100: base100,
  250: base250,
  500: base500,
  1000: base1000,
});

export const products: Product[] = [
  // امکان
  {
    slug: "emkan-yirgacheffe",
    name: "یرگاچف اتیوپی",
    roasterySlug: "emkan",
    origin: "اتیوپی",
    originFlag: "🇪🇹",
    roastLevel: "روشن",
    arabicaPct: 100,
    processing: "شسته",
    tastingNotes: ["یاس", "لیمو", "عسل"],
    roastDaysAgo: 3,
    prices: p(180000, 240000, 520000, 980000, 1850000),
    description:
      "یرگاچف کلاسیک با پروفایل رست روشن، مناسب برای دم‌آوری‌های دستی و V60.",
  },
  {
    slug: "emkan-colombia-huila",
    name: "کلمبیا هوئیلا",
    roasterySlug: "emkan",
    origin: "کلمبیا",
    originFlag: "🇨🇴",
    roastLevel: "متوسط",
    arabicaPct: 100,
    processing: "شسته",
    tastingNotes: ["شکلات شیری", "کارامل", "پرتقال"],
    roastDaysAgo: 4,
    prices: p(150000, 210000, 460000, 860000, 1650000),
    description: "کلمبیا هوئیلا با شیرینی متعادل و بدنه گرد، مناسب اسپرسو و دمی.",
  },
  {
    slug: "emkan-brazil-cerrado",
    name: "برزیل سرادو",
    roasterySlug: "emkan",
    origin: "برزیل",
    originFlag: "🇧🇷",
    roastLevel: "تیره",
    arabicaPct: 100,
    processing: "طبیعی",
    tastingNotes: ["شکلات تلخ", "بادام", "کارامل"],
    roastDaysAgo: 5,
    prices: p(130000, 190000, 420000, 780000, 1450000),
    description: "پایه اسپرسوی حرفه‌ای با کرمای غنی و شیرینی طبیعی.",
  },

  // موآ
  {
    slug: "moa-kenya-aa",
    name: "کنیا AA",
    roasterySlug: "moa",
    origin: "کنیا",
    originFlag: "🇰🇪",
    roastLevel: "روشن",
    arabicaPct: 100,
    processing: "شسته",
    tastingNotes: ["توت سیاه", "گریپ‌فروت", "شراب"],
    roastDaysAgo: 2,
    prices: p(240000, 320000, 780000, 1450000, 2750000),
    description: "کنیا AA درجه یک، با اسیدیته درخشان و نوت‌های میوه‌ای عمیق.",
  },
  {
    slug: "moa-ethiopia-guji",
    name: "گوجی اتیوپی طبیعی",
    roasterySlug: "moa",
    origin: "اتیوپی",
    originFlag: "🇪🇹",
    roastLevel: "روشن",
    arabicaPct: 100,
    processing: "طبیعی",
    tastingNotes: ["توت‌فرنگی", "شکلات", "شراب قرمز"],
    roastDaysAgo: 3,
    prices: p(220000, 300000, 720000, 1350000, 2550000),
    description: "پروسه طبیعی با شیرینی میوه‌ای شدید و بدنه سیراپی.",
  },
  {
    slug: "moa-guatemala-huehuetenango",
    name: "گواتمالا هوهوتنانگو",
    roasterySlug: "moa",
    origin: "گواتمالا",
    originFlag: "🇬🇹",
    roastLevel: "متوسط",
    arabicaPct: 100,
    processing: "شسته",
    tastingNotes: ["کاکائو", "پرتقال خونی", "عسل"],
    roastDaysAgo: 4,
    prices: p(190000, 260000, 610000, 1140000, 2150000),
    description: "قهوه ارتفاعات گواتمالا با تعادل عالی بین اسیدیته و بدنه.",
  },

  // اسکارو
  {
    slug: "skaro-espresso-blend",
    name: "بلند اسپرسو اسکارو",
    roasterySlug: "skaro",
    origin: "برزیل/کلمبیا",
    originFlag: "🇧🇷",
    roastLevel: "تیره",
    arabicaPct: 90,
    processing: "طبیعی",
    tastingNotes: ["شکلات", "فندق", "کارامل"],
    roastDaysAgo: 5,
    prices: p(140000, 200000, 440000, 820000, 1520000),
    description: "ترکیب اختصاصی اسپرسو با کرمای پایدار و شیرینی طولانی.",
  },
  {
    slug: "skaro-milk-blend",
    name: "بلند شیرقهوه",
    roasterySlug: "skaro",
    origin: "برزیل/هند",
    originFlag: "🇧🇷",
    roastLevel: "تیره",
    arabicaPct: 80,
    processing: "طبیعی",
    tastingNotes: ["شکلات شیری", "کارامل", "بادام سوخته"],
    roastDaysAgo: 6,
    prices: p(125000, 180000, 400000, 750000, 1380000),
    description: "طراحی‌شده برای لاته و کاپوچینو؛ در شیر خودنمایی می‌کند.",
  },
  {
    slug: "skaro-colombia-narino",
    name: "کلمبیا نارینو",
    roasterySlug: "skaro",
    origin: "کلمبیا",
    originFlag: "🇨🇴",
    roastLevel: "متوسط",
    arabicaPct: 100,
    processing: "شسته",
    tastingNotes: ["شکلات", "پرتقال", "عسل"],
    roastDaysAgo: 4,
    prices: p(170000, 235000, 540000, 1010000, 1900000),
    description: "تک‌خاستگاه کلمبیا با شیرینی متعادل و بدنه متوسط.",
  },

  // هوفر
  {
    slug: "houfer-ethiopia-sidamo",
    name: "سیدامو اتیوپی",
    roasterySlug: "houfer",
    origin: "اتیوپی",
    originFlag: "🇪🇹",
    roastLevel: "روشن",
    arabicaPct: 100,
    processing: "شسته",
    tastingNotes: ["گل", "لیمو", "چای سیاه"],
    roastDaysAgo: 3,
    prices: p(200000, 275000, 640000, 1200000, 2280000),
    description: "سیدامو ظریف با نوت‌های گلی و پایان تمیز.",
  },
  {
    slug: "houfer-kenya-nyeri",
    name: "کنیا نایری",
    roasterySlug: "houfer",
    origin: "کنیا",
    originFlag: "🇰🇪",
    roastLevel: "متوسط",
    arabicaPct: 100,
    processing: "شسته",
    tastingNotes: ["توت", "گریپ‌فروت", "کارامل"],
    roastDaysAgo: 4,
    prices: p(230000, 310000, 750000, 1400000, 2650000),
    description: "کنیای درخشان با اسیدیته زنده و شیرینی عمیق.",
  },
  {
    slug: "houfer-indonesia-sumatra",
    name: "سوماترا اندونزی",
    roasterySlug: "houfer",
    origin: "اندونزی",
    originFlag: "🇮🇩",
    roastLevel: "تیره",
    arabicaPct: 100,
    processing: "طبیعی",
    tastingNotes: ["ادویه", "کاکائو", "تنباکو"],
    roastDaysAgo: 6,
    prices: p(160000, 220000, 500000, 940000, 1780000),
    description: "بدنه سنگین و طعم خاکی، ایده‌آل برای اسپرسو و موکاپات.",
  },

  // لویا
  {
    slug: "luya-brazil-santos",
    name: "برزیل سانتوس",
    roasterySlug: "luya",
    origin: "برزیل",
    originFlag: "🇧🇷",
    roastLevel: "متوسط",
    arabicaPct: 100,
    processing: "طبیعی",
    tastingNotes: ["کاکائو", "بادام", "کارامل"],
    roastDaysAgo: 5,
    prices: p(120000, 175000, 385000, 720000, 1320000),
    description: "برزیل سانتوس اقتصادی و متعادل، مناسب مصرف روزانه.",
  },
  {
    slug: "luya-guatemala-antigua",
    name: "گواتمالا آنتیگوا",
    roasterySlug: "luya",
    origin: "گواتمالا",
    originFlag: "🇬🇹",
    roastLevel: "متوسط",
    arabicaPct: 100,
    processing: "شسته",
    tastingNotes: ["شکلات", "پرتقال", "دارچین"],
    roastDaysAgo: 4,
    prices: p(185000, 255000, 590000, 1100000, 2080000),
    description: "آنتیگوا با پروفایل کلاسیک و پایان شیرین.",
  },
  {
    slug: "luya-ethiopia-limu",
    name: "لیمو اتیوپی",
    roasterySlug: "luya",
    origin: "اتیوپی",
    originFlag: "🇪🇹",
    roastLevel: "روشن",
    arabicaPct: 100,
    processing: "عسل",
    tastingNotes: ["هلو", "عسل", "یاس"],
    roastDaysAgo: 3,
    prices: p(210000, 290000, 680000, 1270000, 2400000),
    description: "پروسه عسل با شیرینی سیراپی و اسیدیته ملایم.",
  },
];

export const faqs: { q: string; a: string }[] = [
  {
    q: "قهوه تازه‌رست یعنی چه؟",
    a: "قهوه‌ای که حداکثر ۱۴ روز پیش برشته شده باشد. در رستا تاریخ دقیق رست روی هر محصول نمایش داده می‌شود.",
  },
  {
    q: "چطور مستقیم از روستری بخرم؟",
    a: "روستری مورد نظر را انتخاب کنید، محصول را با وزن دلخواه سفارش دهید. روستری پس از ثبت سفارش قهوه را آماده و ارسال می‌کند.",
  },
  {
    q: "چرا قهوه رستا فقط به‌صورت دانه کامل ارائه می‌شود؟",
    a: "چون آسیاب کردن، تازگی قهوه را در عرض چند روز از بین می‌برد. قهوه‌ی از پیش آسیاب‌شده تا ۷۰٪ عطرش را ظرف چند دقیقه از دست می‌دهد، در حالی که دانه کامل هفته‌ها تازگی خود را حفظ می‌کند. با آسیاب در لحظه مصرف، بیشترین رایحه و طعم را تجربه می‌کنید.",
  },
  {
    q: "چند روز پس از رست قهوه بهترین طعم را دارد؟",
    a: "برای اسپرسو ۷ تا ۱۴ روز پس از رست، و برای قهوه دمی ۳ تا ۱۰ روز پس از رست بهترین زمان مصرف است.",
  },
];

export function getRoastery(slug: string): Roastery | undefined {
  return roasteries.find((r) => r.slug === slug);
}
export function getProduct(slug: string): Product | undefined {
  return products.find((p) => p.slug === slug);
}
export function productsByRoastery(slug: string): Product[] {
  return products.filter((p) => p.roasterySlug === slug);
}
