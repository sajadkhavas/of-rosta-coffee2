import { describe, expect, test } from "bun:test";
import {
  ApiContractError,
  parseContract,
  parseOptionalMedia,
  paymentRequestWireSchema,
  productSummaryWireSchema,
  publicProductSummaryWireSchema,
  quoteWireSchema,
  resourceSchema,
} from "../../src/lib/api/schemas";

const media = {
  id: "media-1",
  alt: "قهوه",
  width: 1200,
  height: 1200,
  sources: [{ url: "https://cdn.rosta.shop/coffee.webp", width: 1200, format: "webp" }],
};

const roastery = {
  id: "roastery-1",
  name: "روستری نمونه",
  slug: "sample-roastery",
  city: "تهران",
  is_verified: true,
  logo: media,
  cover: null,
  preparation_time: { min_hours: 12, max_hours: 36 },
  rating: { value: 4.8, count: 24 },
};

const variant = {
  id: "variant-250",
  sku: "SAMPLE-250",
  weight_grams: 250,
  price: 500_000,
  compare_at_price: 550_000,
  currency: "IRR",
  is_available: true,
  available_quantity: 8,
};

const product = {
  id: "product-1",
  name: "قهوه اتیوپی",
  slug: "ethiopia-sample",
  short_description: "دانه کامل تازه‌رست",
  origin: { id: "origin-1", name: "اتیوپی", country_code: "ET" },
  processing_method: "washed",
  roast_level: "light",
  arabica_percentage: 100,
  tasting_notes: ["مرکبات", "گل"],
  primary_image: media,
  roastery,
  variants: [variant],
  latest_roast_batch: {
    id: "batch-1",
    batch_code: "B-001",
    roasted_at: "2026-07-20T12:00:00Z",
    available_from: "2026-07-22T12:00:00Z",
  },
  status: "published",
};

function quoteFixture() {
  return {
    id: "quote-1",
    expires_at: "2026-07-21T12:00:00Z",
    roastery_id: roastery.id,
    groups: [
      {
        roastery,
        items: [
          {
            id: "line-1",
            product,
            variant,
            quantity: 2,
            line_total: 1_000_000,
          },
        ],
        subtotal: 1_000_000,
        shipping_cost: 80_000,
      },
    ],
    subtotal: 1_000_000,
    shipping_total: 80_000,
    discount_total: 50_000,
    grand_total: 1_030_000,
    currency: "IRR",
    warnings: [],
  };
}

describe("Rosta runtime API contracts", () => {
  test("accepts a valid published whole-bean product", () => {
    expect(publicProductSummaryWireSchema.parse(product).id).toBe("product-1");
  });

  test("rejects grind state anywhere in a product contract", () => {
    expect(() => productSummaryWireSchema.parse({ ...product, grind: "espresso" })).toThrow();
    expect(() =>
      productSummaryWireSchema.parse({
        ...product,
        variants: [{ ...variant, grind_type: "fine" }],
      }),
    ).toThrow();
  });

  test("rejects fabricated or incomplete Variant truth", () => {
    const { currency: _currency, ...withoutCurrency } = variant;
    expect(() =>
      productSummaryWireSchema.parse({ ...product, variants: [withoutCurrency] }),
    ).toThrow();
    expect(() =>
      productSummaryWireSchema.parse({
        ...product,
        variants: [{ ...variant, is_available: true, available_quantity: 0 }],
      }),
    ).toThrow();
  });

  test("rejects unpublished products on public endpoints", () => {
    expect(() => publicProductSummaryWireSchema.parse({ ...product, status: "draft" })).toThrow();
  });

  test("downgrades invalid optional media without inventing media truth", () => {
    expect(parseOptionalMedia({ ...media, sources: [{ ...media.sources[0], url: "javascript:alert(1)" }] })).toBeNull();
    expect(parseOptionalMedia(media)?.sources[0]?.url).toBe("https://cdn.rosta.shop/coffee.webp");
  });

  test("rejects cross-roastery and inconsistent quote totals", () => {
    const crossRoastery = quoteFixture();
    crossRoastery.groups[0].items[0].product = {
      ...product,
      roastery: { ...roastery, id: "roastery-2", slug: "other-roastery" },
    };
    expect(() => quoteWireSchema.parse(crossRoastery)).toThrow();

    const invalidTotal = quoteFixture();
    invalidTotal.grand_total += 1;
    expect(() => quoteWireSchema.parse(invalidTotal)).toThrow();
  });

  test("rejects insecure payment redirects at the contract layer", () => {
    expect(() =>
      paymentRequestWireSchema.parse({ payment_id: "payment-1", redirect_url: "not a url" }),
    ).toThrow();
  });

  test("turns malformed envelopes into an explicit contract error", () => {
    expect(() =>
      parseContract(resourceSchema(publicProductSummaryWireSchema), { data: { id: "only-id" } }, "محصول"),
    ).toThrow(ApiContractError);
  });
});
