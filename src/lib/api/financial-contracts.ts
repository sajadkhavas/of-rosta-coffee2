import { z } from "zod";
import { orderDetailWireSchema, orderSummaryWireSchema, quoteWireSchema } from "./schemas";

const ALLOWED_WHOLE_BEAN_WEIGHTS = new Set([50, 100, 250, 500, 1000]);

function addIssue(context: z.RefinementCtx, path: Array<string | number>, message: string): void {
  context.addIssue({ code: z.ZodIssueCode.custom, path, message });
}

export const authoritativeQuoteWireSchema = quoteWireSchema.superRefine((value, context) => {
  for (const [groupIndex, group] of value.groups.entries()) {
    for (const [itemIndex, item] of group.items.entries()) {
      const expectedLineTotal = item.variant.price * item.quantity;
      if (!Number.isSafeInteger(expectedLineTotal) || item.line_total !== expectedLineTotal) {
        addIssue(
          context,
          ["groups", groupIndex, "items", itemIndex, "line_total"],
          "جمع سطر Quote با قیمت Variant و تعداد سازگار نیست.",
        );
      }
    }
  }
});

function validateOrderFinancials(
  value: z.infer<typeof orderSummaryWireSchema> | z.infer<typeof orderDetailWireSchema>,
  context: z.RefinementCtx,
): void {
  for (const [subOrderIndex, subOrder] of value.sub_orders.entries()) {
    const computedSubtotal = subOrder.items.reduce((sum, item, itemIndex) => {
      if (!ALLOWED_WHOLE_BEAN_WEIGHTS.has(item.variant.weight_grams)) {
        addIssue(
          context,
          ["sub_orders", subOrderIndex, "items", itemIndex, "variant", "weight_grams"],
          "وزن Snapshot سفارش خارج از وزن‌های مجاز دانه کامل است.",
        );
      }
      const expectedLineTotal = item.variant.price * item.quantity;
      if (!Number.isSafeInteger(expectedLineTotal) || item.line_total !== expectedLineTotal) {
        addIssue(
          context,
          ["sub_orders", subOrderIndex, "items", itemIndex, "line_total"],
          "جمع سطر سفارش با قیمت Snapshot و تعداد سازگار نیست.",
        );
      }
      return sum + item.line_total;
    }, 0);
    const packagingTotal = subOrder.items.reduce(
      (sum, item) =>
        sum +
        item.services
          .filter((service) => service.type === "packaging")
          .reduce((serviceSum, service) => serviceSum + service.packaging_fee, 0),
      0,
    );
    const expectedGrand =
      subOrder.subtotal +
      subOrder.packaging_total +
      subOrder.grinding_total +
      subOrder.shipping_total +
      subOrder.tax_total -
      subOrder.discount_total;
    if (computedSubtotal !== subOrder.subtotal) {
      addIssue(context, ["sub_orders", subOrderIndex, "subtotal"], "جمع زیرسفارش ناسازگار است.");
    }
    if (packagingTotal !== subOrder.packaging_total) {
      addIssue(
        context,
        ["sub_orders", subOrderIndex, "packaging_total"],
        "جمع بسته‌بندی زیرسفارش ناسازگار است.",
      );
    }
    if (expectedGrand !== subOrder.grand_total) {
      addIssue(context, ["sub_orders", subOrderIndex, "grand_total"], "جمع زیرسفارش ناسازگار است.");
    }
  }

  const childGrand = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.grand_total, 0);
  if (childGrand !== value.grand_total) {
    addIssue(context, ["grand_total"], "جمع سفارش اصلی با زیرسفارش‌ها سازگار نیست.");
  }

  if ("subtotal" in value) {
    const subtotal = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.subtotal, 0);
    const packaging = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.packaging_total, 0);
    const shipping = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.shipping_total, 0);
    const discount = value.sub_orders.reduce((sum, subOrder) => sum + subOrder.discount_total, 0);
    if (
      subtotal !== value.subtotal ||
      packaging !== value.packaging_total ||
      shipping !== value.shipping_total ||
      discount !== value.discount_total
    ) {
      addIssue(context, ["sub_orders"], "جمع مالی سفارش اصلی با زیرسفارش‌ها سازگار نیست.");
    }
  }
}

export const authoritativeOrderSummaryWireSchema =
  orderSummaryWireSchema.superRefine(validateOrderFinancials);
export const authoritativeOrderDetailWireSchema =
  orderDetailWireSchema.superRefine(validateOrderFinancials);

export type AuthoritativeQuoteWire = z.infer<typeof authoritativeQuoteWireSchema>;
export type AuthoritativeOrderSummaryWire = z.infer<typeof authoritativeOrderSummaryWireSchema>;
export type AuthoritativeOrderDetailWire = z.infer<typeof authoritativeOrderDetailWireSchema>;
