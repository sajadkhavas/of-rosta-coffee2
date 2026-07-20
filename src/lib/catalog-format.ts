import type { MediaAsset, ProductSummary, ProductVariant, RoastLevel } from "@/lib/api/contracts";

const priceFormatter = new Intl.NumberFormat("fa-IR");
const dateFormatter = new Intl.DateTimeFormat("fa-IR", {
  year: "numeric",
  month: "long",
  day: "numeric",
});

export function formatIrr(value: number): string {
  return `${priceFormatter.format(value)} ریال`;
}

export function formatWeight(value: number): string {
  return `${priceFormatter.format(value)} گرم`;
}

export function formatRoastDate(value?: string | null): string | null {
  if (!value) return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : dateFormatter.format(date);
}

export function roastLevelLabel(level: RoastLevel): string {
  return { light: "روشن", medium: "متوسط", dark: "تیره" }[level];
}

export function processingLabel(value: ProductSummary["processingMethod"]): string {
  return { washed: "شسته", natural: "طبیعی", honey: "هانی", other: "سایر" }[value];
}

export function bestMediaUrl(asset?: MediaAsset | null): string | null {
  if (!asset?.sources.length) return null;
  return [...asset.sources].sort((a, b) => b.width - a.width)[0]?.url ?? null;
}

export function availableVariants(product: ProductSummary): ProductVariant[] {
  return product.variants.filter((variant) => variant.isAvailable);
}

export function minimumPrice(product: ProductSummary): number | null {
  const prices = availableVariants(product).map((variant) => variant.price);
  return prices.length ? Math.min(...prices) : null;
}
