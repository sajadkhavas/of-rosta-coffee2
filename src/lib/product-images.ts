// Curated stable Unsplash photo IDs for product & roastery imagery.
// Deterministic per slug so the same item always resolves to the same photo.

const PRODUCT_PHOTOS = [
  "1495474472287-4d71bcdd2085",
  "1447933601403-0c6688de566e",
  "1559056199-641a0ac8b55e",
  "1504630083234-14187a9df0f5",
  "1497935586351-b67a49e012bf",
  "1610889556528-9a770e32642f",
];

const ROASTERY_PHOTO = "1442512595331-e89e73853f31";
const HERO_PHOTO = "1447933601403-0c6688de566e";

const BLOG_PHOTOS = [
  "1442512595331-e89e73853f31",
  "1442550528053-c431ecb55509",
  "1509785307050-d4066910ec1e",
  "1521302200778-33500795e128",
];

function hashSlug(slug: string): number {
  let h = 0;
  for (let i = 0; i < slug.length; i++) h = (h * 31 + slug.charCodeAt(i)) >>> 0;
  return h;
}

export function unsplash(id: string, w = 800): string {
  return `https://images.unsplash.com/photo-${id}?w=${w}&q=80&auto=format&fit=crop`;
}

export function productImage(slug: string, w = 800): string {
  const idx = hashSlug(slug) % PRODUCT_PHOTOS.length;
  return unsplash(PRODUCT_PHOTOS[idx], w);
}

export function productThumbnails(slug: string, w = 200): string[] {
  const base = hashSlug(slug);
  return [1, 2, 3].map((k) =>
    unsplash(PRODUCT_PHOTOS[(base + k) % PRODUCT_PHOTOS.length], w),
  );
}

export function roasteryImage(_slug: string, w = 800): string {
  return unsplash(ROASTERY_PHOTO, w);
}

export function heroImage(w = 1600): string {
  return unsplash(HERO_PHOTO, w);
}

export function blogImage(slug: string, w = 800): string {
  const idx = hashSlug(slug) % BLOG_PHOTOS.length;
  return unsplash(BLOG_PHOTOS[idx], w);
}
