import { z } from "zod";
import { orderDetailWireSchema, orderSummaryWireSchema, quoteWireSchema } from "./schemas";

const ALLOWED_WHOLE_BEAN_WEIGHTS = new Set([50, 100, 250, 500, 1000]);

function addIssue(context: z.RefinementCtx, path: Array<string | number>, message: string): void {
  context.addIssue({ code: z.ZodIssueCode.custom, path, message });
}

export const authoritativeQuoteWireSchema = quoteWireSchema.superRefine((value, context) => {
  const group = value.groups[0];
  const computedGroupSubtotal = group.items.reduce((sum, item, index) => {
    const expectedLineTotal = item.variant.price * item.quantity;
    if (!Number.isSafeInteger(expectedLineTotal) || item.line_total !== expectedLineTotal) {
      addIssue(
        context,
        ["groups", 0, "items", index, "line_total"],
        "جمع سطر Quote با قیمت Variant و تعداد سازگار نیست.",
      );
    }
    return sum + item.line_total;
  }, 0);

  if (group.subtotal !== computedGroupSubtotal) {
    addIssue(context, ["groups", 0, "subtotal"], "جمع اقلام گروه Quote ناسازگار است.");
  }
  if (value.subtotal !== group.subtotal) {
    addIssue(context, ["subtotal"], "جمع اقلام Quote با گروه تک‌روستری سازگار نیست.");
  }

  const groupShipping = group.shipping_cost ?? group.shipping_total;
  if (group.shipping_cost !== undefined && group.shipping_total !== undefined) {
    if (group.shipping_cost !== group.shipping_total) {
      addIssue(
        context,
        ["groups", 0, "shipping_total"],
        "دو مقدار هزینه ارسال گروه Quote با هم سازگار نیستند.",
      );
    }
  }
  if (groupShipping === null || groupShipping === undefined) {
    addIssue(context, ["groups", 0], "هزینه ارسال گروه Quote باید مشخص باشد.");
  } else if (groupShipping !== value.shipping_total) {
    addIssue(context, ["shipping_total"], "هزینه ارسال Quote با گروه تک‌روستری سازگار نیست.");
  }
});

function validateOrderFinancials(
  value: z.infer<typeof orderSummaryWireSchema> | z.infer<typeof orderDetailWireSchema>,
  context: z.RefinementCtx,
): void {
  const subOrder = value.sub_orders[0];
  const computedSubtotal = subOrder.items.reduce((sum, item, index) => {
    if (!ALLOWED_WHOLE_BEAN_WEIGHTS.has(item.variant.weight_grams)) {
      addIssue(
        context,
        ["sub_orders", 0, "items", index, "variant", "weight_grams"],
        "وزن Snapshot سفارش خارج از وزن‌های مجاز دانه کامل است.",
      );
    }

    const expectedLineTotal = item.variant.price * item.quantity;
    if (!Number.isSafeInteger(expectedLineTotal) || item.line_total !== expectedLineTotal) {
      addIssue(
        context,
        ["sub_orders", 0, "items", index, "line_total"],
        "جمع سطر سفارش با قیمت Snapshot و تعداد سازگار نیست.",
      );
    }
    return sum + item.line_total;
  }, 0);

  if (subOrder.subtotal !== computedSubtotal) {
    addIssue(context, ["sub_orders", 0, "subtotal"], "جمع زیرسفارش با اقلام Snapshot سازگار نیست.");
  }

  if ("subtotal" in value) {
    if (value.subtotal !== subOrder.subtotal) {
      addIssue(context, ["subtotal"], "جمع سفارش با زیرسفارش تک‌روستری سازگار نیست.");
    }
    if (value.shipping_total !== subOrder.shipping_total) {
      addIssue(context, ["shipping_total"], "هزینه ارسال سفارش با زیرسفارش تک‌روستری سازگار نیست.");
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
