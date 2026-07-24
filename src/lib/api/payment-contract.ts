import { z } from "zod";
import { orderStatusSchema } from "./schemas";

const identifierSchema = z
  .string()
  .trim()
  .min(1)
  .max(200)
  .regex(/^[A-Za-z0-9._:-]+$/);

export const verifiedPaymentWireSchema = z
  .object({
    payment_id: identifierSchema,
    status: z.enum(["pending", "paid", "failed", "cancelled", "refunded"]),
    order_id: identifierSchema,
    order_status: orderStatusSchema,
    amount: z.number().int().positive().max(Number.MAX_SAFE_INTEGER),
    currency: z.literal("IRR"),
    verified_at: z
      .string()
      .refine((value) => Number.isFinite(Date.parse(value)), "زمان Verify نامعتبر است.")
      .nullable(),
  })
  .strict()
  .superRefine((value, context) => {
    if (value.status === "paid" && !value.verified_at) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["verified_at"],
        message: "پرداخت paid باید زمان Verify داشته باشد.",
      });
    }
    if (value.status === "refunded" && value.order_status !== "refunded") {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["order_status"],
        message: "پرداخت refunded باید با سفارش refunded سازگار باشد.",
      });
    }
  });

export type VerifiedPaymentWire = z.infer<typeof verifiedPaymentWireSchema>;
