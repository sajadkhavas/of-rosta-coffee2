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

const packaging = {
  mode: "free",
  fee_amount: 0,
  currency: "IRR",
  is_free: true,
  label: "بسته‌بندی روستری رایگان",
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
  packaging,
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

const quotePackagingService = {
  id: "quote-packaging-1",
  type: "packaging",
  provider_type: "roastery",
  grinding_profile: null,
  service_fee: 0,
  packaging_fee: 0,
  tax_amount: 0,
  total_amount: 0,
  currency: "IRR",
  is_free: true,
  label: "بسته‌بندی روستری رایگان",
};

const orderPackagingService = {
  id: "order-packaging-1",
  type: "packaging",
  provider_type: "roastery",
  status: "requested",
  grinding_profile: null,
  service_fee: 0,
  packaging_fee: 0,
  shipping_fee: 0,
  tax_amount: 0,
  total_amount: 0,
  currency: "IRR",
  is_free: true,
  label: "بسته‌بندی روستری رایگان",
};

function quoteFixture() {
  return {
    id: "quote-1",
    expires_at: "2026-07-21T12:00:00Z",
    roastery_id: roastery.id,
    groups: [
      {
        id: "quote-group-1",
        roastery,
        items: [
          {
            id: "line-1",
            product,
            variant,
            quantity: 2,
            line_total: 1_000_000,
            services: [quotePackagingService],
          },
        ],
        subtotal: 1_000_000,
        packaging_total: 0,
        grinding_total: 0,
        shipping_total: 100_000,
        discount_total: 50_000,
        tax_total: 0,
        grand_total: 1_050_000,
        currency: "IRR",
      },
    ],
    subtotal: 1_000_000,
    packaging_total: 0,
    grinding_total: 0,
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
    packaging_total: 0,
    grinding_total: 0,
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
        acceptance_status: "accepted",
        customer_cancellable: false,
        fulfillment: {
          acceptance_mode: "automatic_contractual",
          committed_at: "2026-07-21T10:01:00Z",
          preparation_due_at: "2026-07-22T10:01:00Z",
          handoff_due_at: "2026-07-23T10:01:00Z",
          sla_status: "preparing",
          is_breached: false,
        },
        incidents: [],
        delivery: {
          confirmed_at: null,
          dispute_window_ends_at: null,
          customer_can_confirm: false,
          settlement_state: "not_delivered",
          settlement_hold_code: null,
          settlement_released_at: null,
        },
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
            services: [orderPackagingService],
          },
        ],
        subtotal: 1_000_000,
        packaging_total: 0,
        grinding_total: 0,
        shipping_total: 100_000,
        discount_total: 50_000,
        tax_total: 0,
        grand_total: 1_050_000,
        currency: "IRR",
        shipment: null,
        shipment_legs: [],
      },
    ],
    events: [],
  };
}

describe("authoritative financial contracts", () => {
  test("accepts internally consistent marketplace quote and order", () => {
    expect(authoritativeQuoteWireSchema.parse(quoteFixture()).id).toBe("quote-1");
    expect(authoritativeOrderDetailWireSchema.parse(orderFixture()).id).toBe("order-1");
  });

  test("rejects a quote whose line total or shipping does not reconcile", () => {
    const invalidLine = quoteFixture();
    invalidLine.groups[0].items[0].line_total += 1;
    expect(() => authoritativeQuoteWireSchema.parse(invalidLine)).toThrow();

    const invalidShipping = quoteFixture();
    invalidShipping.groups[0].shipping_total += 1;
    expect(() => authoritativeQuoteWireSchema.parse(invalidShipping)).toThrow();
  });

  test("rejects an order with invalid whole-bean weight or inconsistent snapshot totals", () => {
    const invalidWeight = orderFixture();
    invalidWeight.sub_orders[0].items[0].variant.weight_grams = 333;
    expect(() => authoritativeOrderDetailWireSchema.parse(invalidWeight)).toThrow();

    const invalidSubtotal = orderFixture();
    invalidSubtotal.sub_orders[0].subtotal += 1;
    expect(() => authoritativeOrderDetailWireSchema.parse(invalidSubtotal)).toThrow();
  });
});
