import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract, resourceSchema } from "./schemas";

export const inquiryTypes = [
  "support",
  "order_issue",
  "roastery_onboarding",
  "corporate_purchase",
  "content_correction",
  "privacy_request",
] as const;

export type InquiryType = (typeof inquiryTypes)[number];

export interface CreateInquiryInput {
  type: InquiryType;
  name: string;
  mobile?: string;
  email?: string;
  orderNumber?: string;
  message: string;
  website?: string;
}

export interface InquiryReceipt {
  referenceId: string;
  status: "received";
  replayed: boolean;
}

const inquiryReceiptWireSchema = z
  .object({
    reference_id: z.string().min(1).max(200),
    status: z.literal("received"),
    replayed: z.boolean(),
  })
  .strict();

export async function createInquiry(input: CreateInquiryInput): Promise<InquiryReceipt> {
  const name = input.name.trim();
  const mobile = input.mobile?.trim() || undefined;
  const email = input.email?.trim().toLowerCase() || undefined;
  const orderNumber = input.orderNumber?.trim().toUpperCase() || undefined;
  const message = input.message.trim();

  if (name.length < 2 || name.length > 160) {
    throw new Error("نام باید بین ۲ تا ۱۶۰ کاراکتر باشد.");
  }
  if (!mobile && !email) {
    throw new Error("شماره موبایل یا ایمیل را وارد کنید.");
  }
  if (message.length < 10 || message.length > 5000) {
    throw new Error("متن درخواست باید بین ۱۰ تا ۵۰۰۰ کاراکتر باشد.");
  }
  if (input.type === "order_issue" && !orderNumber) {
    throw new Error("شماره سفارش برای پیگیری سفارش الزامی است.");
  }

  const raw = await apiFetch("/inquiries", {
    method: "POST",
    body: {
      type: input.type,
      name,
      mobile,
      email,
      order_number: orderNumber,
      message,
      website: input.website?.trim() || undefined,
    },
  });
  const response = parseContract(
    resourceSchema(inquiryReceiptWireSchema),
    raw,
    "ارسال درخواست پشتیبانی",
  );

  return {
    referenceId: response.data.reference_id,
    status: response.data.status,
    replayed: response.data.replayed,
  };
}
