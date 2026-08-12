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
  const fallback = asset.sources.filter((source) => ["jpeg", "png"].includes(source.format));
  return (
    [...(fallback.length ? fallback : asset.sources)].sort((a, b) => b.width - a.width)[0]?.url ??
    null
  );
}

export function mediaSrcSet(asset?: MediaAsset | null): string | undefined {
  if (!asset?.sources.length) return undefined;
  const preferred = asset.sources.filter((source) => source.format === "webp");
  const widest = [...(preferred.length ? preferred : asset.sources)].sort(
    (a, b) => b.width - a.width,
  )[0];
  if (!widest) return undefined;

  const byWidth = new Map<number, string>();
  for (const source of asset.sources) {
    if (source.format === widest.format) byWidth.set(source.width, source.url);
  }

  const candidates = [...byWidth.entries()]
    .sort(([left], [right]) => left - right)
    .map(([width, url]) => `${url} ${width}w`);
  return candidates.length ? candidates.join(", ") : undefined;
}

export function availableVariants(product: ProductSummary): ProductVariant[] {
  return product.variants.filter((variant) => variant.isAvailable);
}

export function minimumPrice(product: ProductSummary): number | null {
  const prices = availableVariants(product).map((variant) => variant.price);
  return prices.length ? Math.min(...prices) : null;
}
