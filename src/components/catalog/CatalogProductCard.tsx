import { Link } from "@tanstack/react-router";
import type { ProductSummary } from "@/lib/api/contracts";
import {
  bestMediaUrl,
  formatIrr,
  formatRoastDate,
  minimumPrice,
  roastLevelLabel,
} from "@/lib/catalog-format";

export function CatalogProductCard({ product }: { product: ProductSummary }) {
  const imageUrl = bestMediaUrl(product.primaryImage);
  const price = minimumPrice(product);
  const roastDate = formatRoastDate(product.latestRoastBatch?.roastedAt);

  return (
    <article className="group overflow-hidden rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] transition hover:-translate-y-1 hover:border-[color:var(--roast)]">
      <Link
        to="/products/$slug"
        params={{ slug: product.slug }}
        className="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--roast)]"
      >
        <div className="relative aspect-square overflow-hidden bg-[color:var(--steam)]">
          {imageUrl ? (
            <img
              src={imageUrl}
              alt={product.primaryImage?.alt || product.name}
              loading="lazy"
              width={product.primaryImage?.width}
              height={product.primaryImage?.height}
              className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
            />
          ) : (
            <div className="grid h-full place-items-center text-sm text-[color:var(--mid)]">
              تصویر محصول
            </div>
          )}
          <span className="absolute end-3 top-3 rounded-full bg-[color:var(--night)]/85 px-2.5 py-1 text-[11px] text-[color:var(--steam)]">
            رست {roastLevelLabel(product.roastLevel)}
          </span>
          {roastDate ? (
            <span className="absolute bottom-3 start-3 rounded-full bg-[color:var(--steam)] px-2.5 py-1 text-[11px] font-bold text-[color:var(--night)]">
              رست {roastDate}
            </span>
          ) : null}
        </div>
        <div className="p-4">
          <p className="text-xs text-[color:var(--roast)]">{product.roastery.name}</p>
          <h2 className="mt-1 line-clamp-2 min-h-12 font-bold text-[color:var(--steam)]">
            {product.name}
          </h2>
          <p className="mt-2 text-xs text-[color:var(--light)]">
            {product.origin.name} · {product.tastingNotes.slice(0, 3).join("، ")}
          </p>
          <div className="mt-4 flex items-center justify-between gap-3 border-t border-[color:var(--mid)] pt-3">
            {price === null ? (
              <span className="text-xs text-red-300">ناموجود</span>
            ) : (
              <span className="font-mono text-sm font-bold text-[color:var(--steam)]">
                از {formatIrr(price)}
              </span>
            )}
            <span className="text-xs text-[color:var(--roast)]">مشاهده</span>
          </div>
        </div>
      </Link>
    </article>
  );
}
