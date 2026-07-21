import { describe, expect, test } from "bun:test";
import {
  authoritativeOrderDetailWireSchema,
  authoritativeQuoteWireSchema,
} from "../../src/lib/api/financial-contracts";

const roastery = {
  id: "roastery-1",
  name: "روستری نمونه",
  slug: "sample-roastery",
  city: "تهران",
  is_verified: true,
  logo: null,
  cover: null,
  preparation_time: { min_hours: 12, max_hours: 24 },
  rating: null,
};

const variant = {
  id: "variant-250",
  sku: "SAMPLE-250",
  weight_grams: 250,
  price: 500_000,
  compare_at_price: null,
  currency: "IRR",
  is_available: true,
  available_quantity: 10,
};

const product = {
  id: "product-1",
  name: "قهوه نمونه",
  slug: "sample-coffee",
  short_description: "دانه کامل",
  origin: { id: "origin-1", name: "اتیوپی", country_code: "ET" },
  processing_method: "washed",
  roast_level: "light",
  arabica_percentage: 100,
  tasting_notes: ["مرکبات"],
  primary_image: null,
  roastery,
  variants: [variant],
  latest_roast_batch: {
    id: "batch-1",
    batch_code: "B-001",
    roasted_at: "2026-07-20T08:00:00Z",
    available_from: null,
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
        shipping_cost: 100_000,
      },
    ],
    subtotal: 1_000_000,
    shipping_total: 100_000,
    discount_total: 50_000,
    grand_total: 1_050_000,
    currency: "IRR",
    warnings: [],
  };
}

function orderFixture() {
  return {
    id: "order-1",
    order_number: "R-0001",
    status: "processing",
    placed_at: "2026-07-21T10:00:00Z",
    subtotal: 1_000_000,
    shipping_total: 100_000,
    discount_total: 50_000,
    grand_total: 1_050_000,
    currency: "IRR",
    address: {
      id: "address-1",
      title: "خانه",
      recipient_name: "سجاد",
      recipient_mobile: "09123456789",
      province: "تهران",
      city: "تهران",
      address_line: "خیابان نمونه، پلاک ۱",
      postal_code: "1234567890",
      is_default: true,
    },
    sub_orders: [
      {
        id: "sub-order-1",
        status: "preparing",
        roastery: { id: roastery.id, name: roastery.name, slug: roastery.slug },
        items: [
          {
            id: "order-line-1",
            product: {
              id: product.id,
              name: product.name,
              slug: product.slug,
              primary_image: null,
            },
            variant: {
              id: variant.id,
              sku: variant.sku,
              weight_grams: variant.weight_grams,
              price: variant.price,
              currency: variant.currency,
            },
            quantity: 2,
            line_total: 1_000_000,
          },
        ],
        subtotal: 1_000_000,
        shipping_total: 100_000,
        shipment: null,
      },
    ],
  };
}

describe("authoritative financial contracts", () => {
  test("accepts internally consistent single-roastery quote and order", () => {
    expect(authoritativeQuoteWireSchema.parse(quoteFixture()).id).toBe(
      "quote-1",
    );
    expect(authoritativeOrderDetailWireSchema.parse(orderFixture()).id).toBe(
      "order-1",
    );
  });

  test("rejects a quote whose line total or shipping does not reconcile", () => {
    const invalidLine = quoteFixture();
    invalidLine.groups[0].items[0].line_total += 1;
    expect(() => authoritativeQuoteWireSchema.parse(invalidLine)).toThrow();

    const invalidShipping = quoteFixture();
    invalidShipping.groups[0].shipping_cost += 1;
    expect(() => authoritativeQuoteWireSchema.parse(invalidShipping)).toThrow();
  });

  test("rejects an order with invalid whole-bean weight or inconsistent snapshot totals", () => {
    const invalidWeight = orderFixture();
    invalidWeight.sub_orders[0].items[0].variant.weight_grams = 333;
    expect(() =>
      authoritativeOrderDetailWireSchema.parse(invalidWeight),
    ).toThrow();

    const invalidSubtotal = orderFixture();
    invalidSubtotal.sub_orders[0].subtotal += 1;
    expect(() =>
      authoritativeOrderDetailWireSchema.parse(invalidSubtotal),
    ).toThrow();
  });
});
